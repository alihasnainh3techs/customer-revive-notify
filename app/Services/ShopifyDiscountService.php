<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ShopifyDiscountService
{
    // -----------------------------------------------------------------
    // GraphQL — queries
    // -----------------------------------------------------------------

    private const QUERY_DISCOUNT_BY_ID = <<<'GQL'
        query GetDiscountById($id: ID!) {
            codeDiscountNode(id: $id) {
                id
                codeDiscount {
                    ... on DiscountCodeBasic {
                        codes(first: 1) { edges { node { code } } }
                    }
                    ... on DiscountCodeFreeShipping {
                        codes(first: 1) { edges { node { code } } }
                    }
                }
            }
        }
    GQL;

    // -----------------------------------------------------------------
    // GraphQL — mutations: segment
    // -----------------------------------------------------------------

    private const MUTATION_SEGMENT_CREATE = <<<'GQL'
        mutation segmentCreate($name: String!, $query: String!) {
            segmentCreate(name: $name, query: $query) {
                segment { id }
                userErrors { field message }
            }
        }
    GQL;

    private const MUTATION_SEGMENT_DELETE = <<<'GQL'
        mutation segmentDelete($id: ID!) {
            segmentDelete(id: $id) {
                deletedSegmentId
                userErrors { field message }
            }
        }
    GQL;

    // -----------------------------------------------------------------
    // GraphQL — mutations: discount create
    // -----------------------------------------------------------------

    private const MUTATION_BASIC_CREATE = <<<'GQL'
        mutation CreateBasicDiscountCode($basicCodeDiscount: DiscountCodeBasicInput!) {
            discountCodeBasicCreate(basicCodeDiscount: $basicCodeDiscount) {
                codeDiscountNode { id }
                userErrors { field message }
            }
        }
    GQL;

    private const MUTATION_SHIPPING_CREATE = <<<'GQL'
        mutation CreateFreeShippingCode($freeShippingCodeDiscount: DiscountCodeFreeShippingInput!) {
            discountCodeFreeShippingCreate(freeShippingCodeDiscount: $freeShippingCodeDiscount) {
                codeDiscountNode { id }
                userErrors { field message }
            }
        }
    GQL;

    // -----------------------------------------------------------------
    // GraphQL — mutations: discount update
    // -----------------------------------------------------------------

    private const MUTATION_BASIC_UPDATE = <<<'GQL'
        mutation UpdateBasicDiscountCode($id: ID!, $basicCodeDiscount: DiscountCodeBasicInput!) {
            discountCodeBasicUpdate(id: $id, basicCodeDiscount: $basicCodeDiscount) {
                codeDiscountNode { id }
                userErrors { field message }
            }
        }
    GQL;

    private const MUTATION_SHIPPING_UPDATE = <<<'GQL'
        mutation UpdateFreeShippingCode($id: ID!, $freeShippingCodeDiscount: DiscountCodeFreeShippingInput!) {
            discountCodeFreeShippingUpdate(id: $id, freeShippingCodeDiscount: $freeShippingCodeDiscount) {
                codeDiscountNode { id }
                userErrors { field message }
            }
        }
    GQL;

    // -----------------------------------------------------------------
    // GraphQL — mutations: discount delete
    // -----------------------------------------------------------------

    private const MUTATION_DELETE = <<<'GQL'
        mutation DeleteDiscountCode($id: ID!) {
            discountCodeDelete(id: $id) {
                deletedCodeDiscountId
                userErrors { field message }
            }
        }
    GQL;

    // -----------------------------------------------------------------
    // Public entry point
    // -----------------------------------------------------------------

    /**
     * Sync the Shopify discount code for the given campaign.
     *
     * Decision tree:
     *   shopify_discount_id is null                        → create fresh
     *   shopify_discount_id present, not found in Shopify  → create fresh (manually deleted)
     *   shopify_discount_id present, code changed          → delete old + create new
     *   shopify_discount_id present, code unchanged        → update existing
     *
     * Segment decision tree (runs before every create/update):
     *   No active filters       → all: true, clean up old segment if any
     *   Has active filters      → delete old segment, create fresh one, use customerSegments
     *
     * @throws \App\Exceptions\NoCustomersMatchedException
     */
    public function syncDiscount(Campaign $campaign): void
    {
        $user        = $campaign->user;
        $shopDomain  = $user->name;
        $accessToken = $user->password;

        if (empty($campaign->shopify_discount_id)) {
            $this->runCreate($campaign, $shopDomain, $accessToken);
            return;
        }

        $existing = $this->fetchDiscountById($campaign->shopify_discount_id, $shopDomain, $accessToken);

        if ($existing === null) {
            Log::warning("Campaign [{$campaign->id}]: discount ID {$campaign->shopify_discount_id} not found in Shopify (deleted). Re-creating.");
            $this->runCreate($campaign, $shopDomain, $accessToken);
            return;
        }

        $shopifyCode = $this->extractCodeFromNode($existing);

        if ($shopifyCode !== $campaign->discount_code) {
            Log::info("Campaign [{$campaign->id}]: code renamed '{$shopifyCode}' → '{$campaign->discount_code}'. Deleting old and re-creating.");
            $this->deleteDiscount($campaign->shopify_discount_id, $shopDomain, $accessToken);
            $this->runCreate($campaign, $shopDomain, $accessToken);
            return;
        }

        Log::info("Campaign [{$campaign->id}]: updating existing discount {$campaign->shopify_discount_id}.");
        $this->runUpdate($campaign, $shopDomain, $accessToken);
    }

    // -----------------------------------------------------------------
    // Create
    // -----------------------------------------------------------------

    private function runCreate(Campaign $campaign, string $shopDomain, string $accessToken): void
    {
        [$startsAt, $endsAt] = $this->resolveDates($campaign);
        $customerSel         = $this->resolveCustomerSelection($campaign, $shopDomain, $accessToken);

        if ($campaign->discount_type === 'shipping_discount') {
            $response    = $this->doShippingCreate($campaign, $shopDomain, $accessToken, $startsAt, $endsAt, $customerSel);
            $mutationKey = 'discountCodeFreeShippingCreate';
        } else {
            $response    = $this->doBasicCreate($campaign, $shopDomain, $accessToken, $startsAt, $endsAt, $customerSel);
            $mutationKey = 'discountCodeBasicCreate';
        }

        $this->checkErrors($response, $mutationKey);

        $newId = $response['data'][$mutationKey]['codeDiscountNode']['id'];
        $campaign->update(['shopify_discount_id' => $newId]);

        Log::info("Campaign [{$campaign->id}]: discount created. Shopify ID: {$newId}");
    }

    private function doBasicCreate(
        Campaign $campaign,
        string   $shopDomain,
        string   $accessToken,
        string   $startsAt,
        string   $endsAt,
        array    $customerSel
    ): array {
        $rules     = $campaign->discount_rules;
        $variables = [
            'basicCodeDiscount' => array_filter([
                'title'                  => $campaign->campaign_name,
                'code'                   => $campaign->discount_code,
                'startsAt'               => $startsAt,
                'endsAt'                 => $endsAt,
                'customerSelection'      => $customerSel,
                'customerGets'           => $this->buildCustomerGets($campaign, $rules),
                'appliesOncePerCustomer' => true,
                'minimumRequirement'     => $this->buildMinimumRequirement($rules),
            ], fn($v) => $v !== null),
        ];

        return $this->graphql($shopDomain, $accessToken, self::MUTATION_BASIC_CREATE, $variables);
    }

    private function doShippingCreate(
        Campaign $campaign,
        string   $shopDomain,
        string   $accessToken,
        string   $startsAt,
        string   $endsAt,
        array    $customerSel
    ): array {
        $rules    = $campaign->discount_rules;
        $shipping = $rules['shipping'];

        $variables = [
            'freeShippingCodeDiscount' => array_filter([
                'title'                  => $campaign->campaign_name,
                'code'                   => $campaign->discount_code,
                'startsAt'               => $startsAt,
                'endsAt'                 => $endsAt,
                'customerSelection'      => $customerSel,
                'minimumRequirement'     => $this->buildMinimumRequirement($rules),
                'destination'            => ['all' => true],
                'maximumShippingPrice'   => $shipping['value'] !== null ? (string) $shipping['value'] : null,
                'appliesOncePerCustomer' => true,
            ], fn($v) => $v !== null),
        ];

        return $this->graphql($shopDomain, $accessToken, self::MUTATION_SHIPPING_CREATE, $variables);
    }

    // -----------------------------------------------------------------
    // Update
    // -----------------------------------------------------------------

    private function runUpdate(Campaign $campaign, string $shopDomain, string $accessToken): void
    {
        [$startsAt, $endsAt] = $this->resolveDates($campaign);
        $customerSel         = $this->resolveCustomerSelection($campaign, $shopDomain, $accessToken);
        $id                  = $campaign->shopify_discount_id;

        if ($campaign->discount_type === 'shipping_discount') {
            $response    = $this->doShippingUpdate($campaign, $id, $shopDomain, $accessToken, $startsAt, $endsAt, $customerSel);
            $mutationKey = 'discountCodeFreeShippingUpdate';
        } else {
            $response    = $this->doBasicUpdate($campaign, $id, $shopDomain, $accessToken, $startsAt, $endsAt, $customerSel);
            $mutationKey = 'discountCodeBasicUpdate';
        }

        $this->checkErrors($response, $mutationKey);

        Log::info("Campaign [{$campaign->id}]: discount updated successfully.");
    }

    private function doBasicUpdate(
        Campaign $campaign,
        string   $id,
        string   $shopDomain,
        string   $accessToken,
        string   $startsAt,
        string   $endsAt,
        array    $customerSel
    ): array {
        $rules     = $campaign->discount_rules;
        $variables = [
            'id' => $id,
            'basicCodeDiscount' => array_filter([
                'title'                  => $campaign->campaign_name,
                'code'                   => $campaign->discount_code,
                'startsAt'               => $startsAt,
                'endsAt'                 => $endsAt,
                'customerSelection'      => $customerSel,
                'customerGets'           => $this->buildCustomerGets($campaign, $rules),
                'appliesOncePerCustomer' => true,
                'minimumRequirement'     => $this->buildMinimumRequirement($rules),
            ], fn($v) => $v !== null),
        ];

        return $this->graphql($shopDomain, $accessToken, self::MUTATION_BASIC_UPDATE, $variables);
    }

    private function doShippingUpdate(
        Campaign $campaign,
        string   $id,
        string   $shopDomain,
        string   $accessToken,
        string   $startsAt,
        string   $endsAt,
        array    $customerSel
    ): array {
        $rules    = $campaign->discount_rules;
        $shipping = $rules['shipping'];

        $variables = [
            'id' => $id,
            'freeShippingCodeDiscount' => array_filter([
                'title'                  => $campaign->campaign_name,
                'code'                   => $campaign->discount_code,
                'startsAt'               => $startsAt,
                'endsAt'                 => $endsAt,
                'customerSelection'      => $customerSel,
                'minimumRequirement'     => $this->buildMinimumRequirement($rules),
                'destination'            => ['all' => true],
                'maximumShippingPrice'   => $shipping['value'] !== null ? (string) $shipping['value'] : null,
                'appliesOncePerCustomer' => true,
            ], fn($v) => $v !== null),
        ];

        return $this->graphql($shopDomain, $accessToken, self::MUTATION_SHIPPING_UPDATE, $variables);
    }

    // -----------------------------------------------------------------
    // Delete discount
    // -----------------------------------------------------------------

    private function deleteDiscount(string $id, string $shopDomain, string $accessToken): void
    {
        $response = $this->graphql($shopDomain, $accessToken, self::MUTATION_DELETE, ['id' => $id]);
        $this->checkErrors($response, 'discountCodeDelete');

        Log::info("Shopify discount deleted: {$id}");
    }

    // -----------------------------------------------------------------
    // Fetch discount by ID
    // -----------------------------------------------------------------

    private function fetchDiscountById(string $id, string $shopDomain, string $accessToken): ?array
    {
        $result = $this->graphql($shopDomain, $accessToken, self::QUERY_DISCOUNT_BY_ID, ['id' => $id]);

        return $result['data']['codeDiscountNode'] ?? null;
    }

    private function extractCodeFromNode(array $node): ?string
    {
        return $node['codeDiscount']['codes']['edges'][0]['node']['code'] ?? null;
    }

    // -----------------------------------------------------------------
    // Customer selection via Shopify Segments
    // -----------------------------------------------------------------

    /**
     * Resolve customerSelection block.
     *
     * No filters  → { all: true }   + delete old segment if any
     * Has filters → delete old segment, create fresh one, return { customerSegments: { add: [id] } }
     *
     * Segment query uses OR between filter types:
     *   (amount_spent filters) OR (last_order_date filters) OR (customer_tags filters)
     */
    private function resolveCustomerSelection(Campaign $campaign, string $shopDomain, string $accessToken): array
    {
        $filters = $campaign->customer_filters;

        if (!$this->hasActiveFilters($filters)) {
            // Clean up any old segment
            if (!empty($campaign->shopify_segment_id)) {
                $this->deleteSegment($campaign->shopify_segment_id, $shopDomain, $accessToken);
                $campaign->update(['shopify_segment_id' => null]);
            }
            return ['all' => true];
        }

        // Delete old segment — filters may have changed since last run
        if (!empty($campaign->shopify_segment_id)) {
            $this->deleteSegment($campaign->shopify_segment_id, $shopDomain, $accessToken);
        }

        // Build ShopifyQL WHERE clause and create a fresh segment
        $segmentQuery = $this->buildSegmentQuery($filters);
        $segmentName  = "crn-campaign-{$campaign->id}-" . now()->format('YmdHis');
        $segmentId    = $this->createSegment($segmentName, $segmentQuery, $shopDomain, $accessToken);

        $campaign->update(['shopify_segment_id' => $segmentId]);

        Log::info("Campaign [{$campaign->id}]: segment created. Segment ID: {$segmentId}");

        return ['customerSegments' => ['add' => [$segmentId]]];
    }

    /**
     * Build a ShopifyQL WHERE clause from campaign filters.
     *
     * Each filter type group is wrapped in parentheses.
     * All groups are joined with OR.
     * Within a range filter (from + to), conditions are joined with AND.
     */
    private function buildSegmentQuery(array $filters): string
    {
        $parts = [];

        // amount_spent (customer level)
        $spentFrom = $filters['total_spent']['from'] ?? null;
        $spentTo   = $filters['total_spent']['to'] ?? null;
        if ($spentFrom !== null && $spentTo !== null) {
            $parts[] = "(amount_spent >= {$spentFrom} AND amount_spent <= {$spentTo})";
        } elseif ($spentFrom !== null) {
            $parts[] = "amount_spent >= {$spentFrom}";
        } elseif ($spentTo !== null) {
            $parts[] = "amount_spent <= {$spentTo}";
        }

        // last_order_date (customer level)
        $dateFrom = $filters['last_order_date']['from'] ?? null;
        $dateTo   = $filters['last_order_date']['to'] ?? null;
        if ($dateFrom !== null && $dateTo !== null) {
            $parts[] = "(last_order_date >= {$dateFrom} AND last_order_date <= {$dateTo})";
        } elseif ($dateFrom !== null) {
            $parts[] = "last_order_date >= {$dateFrom}";
        } elseif ($dateTo !== null) {
            $parts[] = "last_order_date <= {$dateTo}";
        }

        // customer_tags — OR between multiple tags
        $tags = $filters['tags'] ?? [];
        if (is_string($tags) && $tags !== '') {
            $tags = array_map('trim', explode(',', $tags));
        }
        $tags = array_values(array_filter((array) $tags));
        if (!empty($tags)) {
            $tagClauses = array_map(fn($tag) => "customer_tags CONTAINS '{$tag}'", $tags);
            $parts[]    = '(' . implode(' OR ', $tagClauses) . ')';
        }

        // All groups joined with OR
        return implode(' OR ', $parts);
    }

    private function hasActiveFilters(array $filters): bool
    {
        if (!empty($filters['total_spent']['from']) || !empty($filters['total_spent']['to'])) {
            return true;
        }
        if (!empty($filters['last_order_date']['from']) || !empty($filters['last_order_date']['to'])) {
            return true;
        }
        if (!empty($filters['tags'])) {
            return true;
        }

        $purchased = $filters['purchased_products'] ?? '[]';
        if (is_string($purchased)) {
            $purchased = json_decode($purchased, true) ?? [];
        }

        return !empty($purchased);
    }

    // -----------------------------------------------------------------
    // Segment helpers
    // -----------------------------------------------------------------

    private function createSegment(string $name, string $query, string $shopDomain, string $accessToken): string
    {
        $response = $this->graphql($shopDomain, $accessToken, self::MUTATION_SEGMENT_CREATE, [
            'name'  => $name,
            'query' => $query,
        ]);

        $this->checkErrors($response, 'segmentCreate');

        return $response['data']['segmentCreate']['segment']['id'];
    }

    private function deleteSegment(string $id, string $shopDomain, string $accessToken): void
    {
        try {
            $response = $this->graphql($shopDomain, $accessToken, self::MUTATION_SEGMENT_DELETE, ['id' => $id]);
            $this->checkErrors($response, 'segmentDelete');
            Log::info("Shopify segment deleted: {$id}");
        } catch (\Throwable $e) {
            // Segment may have been manually deleted — log and continue
            Log::warning("Could not delete segment [{$id}]: {$e->getMessage()}");
        }
    }

    // -----------------------------------------------------------------
    // Date resolution
    // -----------------------------------------------------------------

    private function resolveDates(Campaign $campaign): array
    {
        if ($campaign->schedule_type === 'custom') {
            $start = Carbon::parse($campaign->custom_start_date)->startOfDay();
            $end   = $start->copy()->addDays((int) $campaign->custom_validity)->endOfDay();
        } else {
            $start = Carbon::now()->startOfMonth()->startOfDay();
            $end   = $start->copy()->addDays((int) $campaign->monthly_validity)->endOfDay();
        }

        return [$start->toIso8601String(), $end->toIso8601String()];
    }

    // -----------------------------------------------------------------
    // Builder helpers
    // -----------------------------------------------------------------

    private function buildCustomerGets(Campaign $campaign, array $rules): array
    {
        $items = $this->resolveProductItems($campaign);

        if ($campaign->discount_type === 'percentage_discount') {
            return [
                'items' => $items,
                'value' => ['percentage' => (float) $rules['percentage']['value'] / 100],
            ];
        }

        return [
            'items' => $items,
            'value' => [
                'discountAmount' => [
                    'amount'            => (string) $rules['fixed']['value'],
                    'appliesOnEachItem' => false,
                ],
            ],
        ];
    }

    private function resolveProductItems(Campaign $campaign): array
    {
        $raw      = $campaign->selected_products;
        $variants = is_string($raw) ? json_decode($raw, true) : $raw;

        if (is_string($variants)) {
            $variants = json_decode($variants, true);
        }

        if (empty($variants)) {
            return ['all' => true];
        }

        return ['products' => ['productVariantsToAdd' => $variants]];
    }

    private function buildMinimumRequirement(array $rules): ?array
    {
        foreach (['percentage', 'fixed', 'shipping'] as $key) {
            if (!empty($rules[$key]['active']) && !empty($rules[$key]['min_subtotal'])) {
                return [
                    'subtotal' => [
                        'greaterThanOrEqualToSubtotal' => (string) $rules[$key]['min_subtotal'],
                    ],
                ];
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // GraphQL transport
    // -----------------------------------------------------------------

    private function graphql(string $shopDomain, string $accessToken, string $query, array $variables = []): array
    {
        $url = "https://{$shopDomain}/admin/api/2026-04/graphql.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type'           => 'application/json',
        ])->post($url, ['query' => $query, 'variables' => $variables]);

        if ($response->failed()) {
            Log::error("Shopify GraphQL HTTP error [{$response->status()}]", [
                'shop' => $shopDomain,
                'body' => $response->body(),
            ]);
            throw new \RuntimeException("Shopify API request failed with status {$response->status()}");
        }

        return $response->json();
    }

    private function checkErrors(array $response, string $mutationKey): void
    {
        if (isset($response['errors'])) {
            $message = is_array($response['errors'])
                ? json_encode($response['errors'])
                : $response['errors'];
            throw new \RuntimeException("Shopify GraphQL error: {$message}");
        }

        $userErrors = $response['data'][$mutationKey]['userErrors'] ?? [];

        if (!empty($userErrors)) {
            $messages = collect($userErrors)
                ->map(function ($e) {
                    $fieldPath = is_array($e['field']) ? implode('.', $e['field']) : ($e['field'] ?? 'unknown');
                    return "[$fieldPath] {$e['message']}";
                })
                ->implode(', ');

            Log::error("Shopify mutation '{$mutationKey}' failed: {$messages}");
            throw new \RuntimeException("Shopify userErrors: {$messages}");
        }
    }
}
