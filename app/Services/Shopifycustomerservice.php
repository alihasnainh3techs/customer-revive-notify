<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyCustomerService
{
    private const QUERY_CUSTOMERS = <<<'GQL'
        query GetCustomers($query: String, $after: String) {
            customers(first: 250, query: $query, after: $after) {
                pageInfo { hasNextPage endCursor }
                edges {
                    node {
                        id
                        firstName
                        lastName
                        email
                        phone
                        amountSpent { amount currencyCode }
                        lastOrder { createdAt }
                        tags
                    }
                }
            }
        }
    GQL;

    /**
     * Fetch all matching customers for a campaign with full data.
     * Paginates through all results respecting customer_filters.
     * Tags use OR logic — a customer is included if they have ANY of the tags.
     * Different filter types use OR logic between each other.
     */
    public function fetchCampaignCustomers(Campaign $campaign): array
    {
        $user        = $campaign->user;
        $shopDomain  = $user->name;
        $accessToken = $user->password;
        $filters     = $campaign->customer_filters;

        $query = $this->buildSearchQuery($filters);

        $customers   = [];
        $cursor      = null;
        $hasNextPage = true;

        while ($hasNextPage) {
            $variables = ['query' => $query];
            if ($cursor) {
                $variables['after'] = $cursor;
            }

            $result        = $this->graphql($shopDomain, $accessToken, self::QUERY_CUSTOMERS, $variables);
            $customersData = $result['data']['customers'] ?? [];

            foreach (($customersData['edges'] ?? []) as $edge) {
                $node        = $edge['node'];
                $customers[] = [
                    'id'         => $node['id'],
                    'firstName'  => $node['firstName'] ?? '',
                    'lastName'   => $node['lastName'] ?? '',
                    'email'      => $node['email'] ?? '',
                    'phone'      => $node['phone'] ?? '',
                    'totalSpent' => $node['amountSpent']['amount'] ?? '0.00',
                    'lastOrder'  => $node['lastOrder'] ?? null,
                    'tags'       => $node['tags'] ?? [],
                ];
            }

            $hasNextPage = $customersData['pageInfo']['hasNextPage'] ?? false;
            $cursor      = $customersData['pageInfo']['endCursor'] ?? null;
        }

        Log::info("Campaign [{$campaign->id}]: fetched " . count($customers) . " customer(s) from Shopify.");

        return $customers;
    }

    // -----------------------------------------------------------------
    // Query builder
    // -----------------------------------------------------------------

    /**
     * Build a Shopify customer search query from filter criteria.
     *
     * Rules:
     * - amount_spent range: AND between from/to bounds
     * - last_order_date range: AND between from/to bounds
     * - customer tags: OR between multiple tags (customer has ANY of the tags)
     * - Different filter groups: OR between each other
     */
    private function buildSearchQuery(array $filters): string
    {
        $parts = [];

        // amount_spent group — AND between bounds, wrapped in parens if both set
        $spentFrom = $filters['total_spent']['from'] ?? null;
        $spentTo   = $filters['total_spent']['to'] ?? null;
        if ($spentFrom !== null && $spentTo !== null) {
            $parts[] = "(total_spent:>={$spentFrom} AND total_spent:<={$spentTo})";
        } elseif ($spentFrom !== null) {
            $parts[] = "total_spent:>={$spentFrom}";
        } elseif ($spentTo !== null) {
            $parts[] = "total_spent:<={$spentTo}";
        }

        // last_order_date group — AND between bounds
        $dateFrom = $filters['last_order_date']['from'] ?? null;
        $dateTo   = $filters['last_order_date']['to'] ?? null;
        if ($dateFrom !== null && $dateTo !== null) {
            $parts[] = "(last_order_date:>={$dateFrom} AND last_order_date:<={$dateTo})";
        } elseif ($dateFrom !== null) {
            $parts[] = "last_order_date:>={$dateFrom}";
        } elseif ($dateTo !== null) {
            $parts[] = "last_order_date:<={$dateTo}";
        }

        // Customer tags — OR between multiple tags
        $tags = $filters['tags'] ?? [];
        if (is_string($tags) && $tags !== '') {
            $tags = array_map('trim', explode(',', $tags));
        }
        $tags = array_values(array_filter((array) $tags));
        if (!empty($tags)) {
            $tagClauses = array_map(fn($tag) => "tag:{$tag}", $tags);
            $parts[]    = count($tagClauses) > 1
                ? '(' . implode(' OR ', $tagClauses) . ')'
                : $tagClauses[0];
        }

        // All filter groups joined with OR
        return implode(' OR ', $parts);
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
}
