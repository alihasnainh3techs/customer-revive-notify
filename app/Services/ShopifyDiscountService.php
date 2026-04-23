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

    private const QUERY_CUSTOMERS = <<<'GQL'
        query GetCustomers($query: String!, $after: String) {
            customers(first: 250, query: $query, after: $after) {
                pageInfo { hasNextPage endCursor }
                edges { node { id } }
            }
        }
    GQL;

    // -----------------------------------------------------------------
    // GraphQL — mutations: create
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
    // GraphQL — mutations: update
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
    // GraphQL — mutations: delete
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
     *   shopify_discount_id is null                          → create fresh
     *   shopify_discount_id present, not found in Shopify   → create fresh (was manually deleted)
     *   shopify_discount_id present, code changed           → delete old + create new
     *   shopify_discount_id present, code unchanged         → update existing
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

        // We have a stored ID — check if it still exists in Shopify
        $existing = $this->fetchDiscountById($campaign->shopify_discount_id, $shopDomain, $accessToken);

        if ($existing === null) {
            // Manually deleted from Shopify — re-create
            Log::warning("Campaign [{$campaign->id}]: discount ID {$campaign->shopify_discount_id} not found in Shopify (deleted). Re-creating.");
            $this->runCreate($campaign, $shopDomain, $accessToken);
            return;
        }

        // Compare the code string — detect renames
        $shopifyCode = $this->extractCodeFromNode($existing);

        if ($shopifyCode !== $campaign->discount_code) {
            Log::info("Campaign [{$campaign->id}]: code renamed '{$shopifyCode}' → '{$campaign->discount_code}'. Deleting old and re-creating.");
            $this->deleteDiscount($campaign->shopify_discount_id, $shopDomain, $accessToken);
            $this->runCreate($campaign, $shopDomain, $accessToken);
            return;
        }

        // Code is unchanged — update the existing discount
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
        string $shopDomain,
        string $accessToken,
        string $startsAt,
        string $endsAt,
        array $customerSel
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
        string $shopDomain,
        string $accessToken,
        string $startsAt,
        string $endsAt,
        array $customerSel
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
        string $id,
        string $shopDomain,
        string $accessToken,
        string $startsAt,
        string $endsAt,
        array $customerSel
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
        string $id,
        string $shopDomain,
        string $accessToken,
        string $startsAt,
        string $endsAt,
        array $customerSel
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
    // Delete
    // -----------------------------------------------------------------

    private function deleteDiscount(string $id, string $shopDomain, string $accessToken): void
    {
        $response = $this->graphql($shopDomain, $accessToken, self::MUTATION_DELETE, ['id' => $id]);
        $this->checkErrors($response, 'discountCodeDelete');

        Log::info("Shopify discount deleted: {$id}");
    }

    // -----------------------------------------------------------------
    // Fetch by ID
    // -----------------------------------------------------------------

    /**
     * Returns the codeDiscountNode array, or null if not found in Shopify.
     */
    private function fetchDiscountById(string $id, string $shopDomain, string $accessToken): ?array
    {
        $result = $this->graphql($shopDomain, $accessToken, self::QUERY_DISCOUNT_BY_ID, ['id' => $id]);

        return $result['data']['codeDiscountNode'] ?? null;
    }

    /**
     * Pull the code string out of a codeDiscountNode response.
     */
    private function extractCodeFromNode(array $node): ?string
    {
        return $node['codeDiscount']['codes']['edges'][0]['node']['code'] ?? null;
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

        return ['variants' => ['variantsToAdd' => $variants]];
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
    // Customer selection
    // -----------------------------------------------------------------

    /**
     * @throws \App\Exceptions\NoCustomersMatchedException
     */
    private function resolveCustomerSelection(Campaign $campaign, string $shopDomain, string $accessToken): array
    {
        $filters = $campaign->customer_filters;

        if (!$this->hasActiveFilters($filters)) {
            return ['all' => true];
        }

        $customerIds = $this->fetchMatchingCustomers($filters, $shopDomain, $accessToken);

        if (empty($customerIds)) {
            Log::warning("Campaign [{$campaign->id}]: no customers matched filters — skipping discount.");
            throw new \App\Exceptions\NoCustomersMatchedException(
                "No customers matched the filters for campaign [{$campaign->id}]."
            );
        }

        return ['customers' => ['add' => $customerIds]];
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

    private function fetchMatchingCustomers(array $filters, string $shopDomain, string $accessToken): array
    {
        $queryParts = [];

        if (!empty($filters['total_spent']['from'])) {
            $queryParts[] = "total_spent:>={$filters['total_spent']['from']}";
        }
        if (!empty($filters['total_spent']['to'])) {
            $queryParts[] = "total_spent:<={$filters['total_spent']['to']}";
        }
        if (!empty($filters['last_order_date']['from'])) {
            $queryParts[] = "last_order_date:>={$filters['last_order_date']['from']}";
        }
        if (!empty($filters['last_order_date']['to'])) {
            $queryParts[] = "last_order_date:<={$filters['last_order_date']['to']}";
        }

        $tags = $filters['tags'] ?? [];
        if (is_string($tags) && $tags !== '') {
            $tags = array_map('trim', explode(',', $tags));
        }
        foreach ((array) $tags as $tag) {
            if ($tag !== '') {
                $queryParts[] = "tag:{$tag}";
            }
        }

        $query       = implode(' AND ', $queryParts);
        $customerIds = [];
        $cursor      = null;
        $hasNextPage = true;

        while ($hasNextPage) {
            $variables     = array_filter(['query' => $query, 'after' => $cursor]);
            $result        = $this->graphql($shopDomain, $accessToken, self::QUERY_CUSTOMERS, $variables);
            $customersData = $result['data']['customers'] ?? [];

            foreach (($customersData['edges'] ?? []) as $edge) {
                $customerIds[] = $edge['node']['id'];
            }

            $hasNextPage = $customersData['pageInfo']['hasNextPage'] ?? false;
            $cursor      = $customersData['pageInfo']['endCursor'] ?? null;
        }

        return $customerIds;
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
        $userErrors = $response['data'][$mutationKey]['userErrors'] ?? [];

        if (!empty($userErrors)) {
            $messages = collect($userErrors)
                // ->map(fn($e) => "[{$e['field']}] {$e['message']}")
                ->map(function ($e) {
                    // Shopify 'field' is an array, so we join it with dots
                    $fieldPath = is_array($e['field']) ? implode('.', $e['field']) : ($e['field'] ?? 'unknown');
                    return "[$fieldPath] {$e['message']}";
                })
                ->implode(', ');

            Log::error("Shopify mutation '{$mutationKey}' failed: {$messages}");
            throw new \RuntimeException("Shopify userErrors: {$messages}");
        }
    }
}
