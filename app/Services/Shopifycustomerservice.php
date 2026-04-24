<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyCustomerService
{
    private const QUERY_CUSTOMERS = <<<'GQL'
        query GetCustomers($query: String!, $after: String) {
            customers(first: 250, query: $query, after: $after) {
                pageInfo { hasNextPage endCursor }
                edges {
                    node {
                        id
                        firstName
                        lastName
                        email
                        phone
                        totalSpent: lifetimeDuration
                        amountSpent { amount currencyCode }
                        lastOrder {
                            createdAt
                        }
                        tags
                    }
                }
            }
        }
    GQL;

    /**
     * Fetch all matching customers for a campaign with full data.
     * Respects customer_filters and paginates through all results.
     *
     * Returns an array of customer data arrays.
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
            $result    = $this->graphql($shopDomain, $accessToken, self::QUERY_CUSTOMERS, $variables);

            $customersData = $result['data']['customers'] ?? [];

            foreach (($customersData['edges'] ?? []) as $edge) {
                $node = $edge['node'];

                $customers[] = [
                    'id'              => $node['id'],
                    'firstName'       => $node['firstName'] ?? '',
                    'lastName'        => $node['lastName'] ?? '',
                    'email'           => $node['email'] ?? '',
                    'phone'           => $node['phone'] ?? '',
                    'totalSpent'      => $node['amountSpent']['amount'] ?? '0.00',
                    'lastOrder'       => $node['lastOrder'] ?? null,
                    'tags'            => $node['tags'] ?? [],
                ];
            }

            $hasNextPage = $customersData['pageInfo']['hasNextPage'] ?? false;
            $cursor      = $customersData['pageInfo']['endCursor'] ?? null;
        }

        Log::info("Campaign [{$campaign->id}]: fetched {$this->count($customers)} customer(s) from Shopify.");

        return $customers;
    }

    // -----------------------------------------------------------------
    // Query builder
    // -----------------------------------------------------------------

    private function buildSearchQuery(array $filters): string
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

        // Empty query = fetch all customers
        return implode(' AND ', $queryParts);
    }

    private function count(array $customers): int
    {
        return count($customers);
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
