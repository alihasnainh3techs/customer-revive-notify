<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function index()
    {
        return view('create-campaign');
    }

    public function getFilteredCustomerCount(Request $request)
    {
        $tags               = $request->input('tags', []);
        $purchasedProducts  = $request->input('purchasedProducts', []);
        $spentFrom          = $request->input('totalSpent.from');
        $spentTo            = $request->input('totalSpent.to');
        $dateFrom           = $request->input('lastOrderDate.from');
        $dateTo             = $request->input('lastOrderDate.to');

        // --- Validate: At least one filter must be present ---
        $hasFilters = !empty($tags)
            || !empty($purchasedProducts)
            || !is_null($spentFrom)
            || !is_null($spentTo)
            || !is_null($dateFrom)
            || !is_null($dateTo);

        if (!$hasFilters) {
            return response()->json([
                'success' => false,
                'message' => 'At least one filter is required.',
                'errors'  => ['filters' => 'Please apply at least one filter.'],
            ], 422);
        }

        // --- Validate: Total Spent range ---
        if (!is_null($spentFrom)) {
            if (!is_numeric($spentFrom)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid from amount.',
                    'errors'  => [
                        'spentFrom' => 'Spent from must be a numeric value.',
                    ],
                ], 422);
            }

            if ((float) $spentFrom < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid from amount.',
                    'errors'  => [
                        'spentFrom' => 'Spent from cannot be less than zero.',
                    ],
                ], 422);
            }
        }

        if (!is_null($spentTo)) {
            if (!is_numeric($spentTo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid to amount.',
                    'errors'  => [
                        'spentTo' => 'Spent to must be a numeric value.',
                    ],
                ], 422);
            }

            if ((float) $spentTo < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid to amount.',
                    'errors'  => [
                        'spentTo' => 'Spent to cannot be less than zero.',
                    ],
                ], 422);
            }
        }

        if (!is_null($spentFrom) && !is_null($spentTo)) {
            if ((float) $spentFrom > (float) $spentTo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid amount range.',
                    'errors'  => [
                        'spentFrom' => 'Spent from amount cannot be greater than spent to amount.',
                    ],
                ], 422);
            }
        }

        // --- Validate: Last Order Date range ---
        if (!is_null($dateFrom)) {
            $parsedFrom = strtotime($dateFrom);

            if (!$parsedFrom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date value.',
                    'errors'  => [
                        'dateFrom' => 'From date is not a valid date.',
                    ],
                ], 422);
            }
        }

        if (!is_null($dateTo)) {
            $parsedTo = strtotime($dateTo);

            if (!$parsedTo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date value.',
                    'errors'  => [
                        'dateTo' => 'To date is not a valid date.',
                    ],
                ], 422);
            }
        }

        if (!is_null($dateFrom) && !is_null($dateTo)) {
            $parsedFrom = strtotime($dateFrom);
            $parsedTo   = strtotime($dateTo);

            if ($parsedFrom > $parsedTo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date range.',
                    'errors'  => [
                        'dateFrom' => 'From date cannot be later than to date.',
                    ],
                ], 422);
            }
        }

        // --- Build Shopify Customer Query String ---
        $queryParts = [];

        foreach ($tags as $tag) {
            $queryParts[] = 'tag:' . $tag;
        }

        if (!is_null($spentFrom)) {
            $queryParts[] = 'total_spent:>=' . (float) $spentFrom;
        }
        if (!is_null($spentTo)) {
            $queryParts[] = 'total_spent:<=' . (float) $spentTo;
        }

        if (!is_null($dateFrom)) {
            $queryParts[] = 'last_order_date:>=' . date('Y-m-d', strtotime($dateFrom));
        }
        if (!is_null($dateTo)) {
            $queryParts[] = 'last_order_date:<=' . date('Y-m-d', strtotime($dateTo));
        }

        // Purchased products — filter by product_id
        foreach ($purchasedProducts as $product) {
            if (!empty($product['id'])) {
                // Strip the GID prefix: "gid://shopify/Product/123" → "123"
                $numericId    = basename($product['id']);
                $queryParts[] = 'product_id:' . $numericId;
            }
        }

        $queryString = implode(' AND ', $queryParts);

        // --- Call Shopify GraphQL ---
        /** @var User $user */
        $user = Auth::user();
        $accessToken = $user->password;
        $shopDomain  = $user->name;
        $version = env('SHOPIFY_API_VERSION', '2025-07');

        $graphql = <<<GQL
        {
            customersCount(query: "{$queryString}") {
                count
                precision
            }
        }
        GQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type'           => 'application/json',
        ])->post("https://{$shopDomain}/admin/api/{$version}/graphql.json", [
            'query' => $graphql,
        ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reach Shopify API.',
                'errors'  => ['shopify' => $response->body()],
            ], 502);
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            return response()->json([
                'success' => false,
                'message' => 'Shopify GraphQL returned errors.',
                'errors'  => $data['errors'],
            ], 422);
        }

        $count     = $data['data']['customersCount']['count']     ?? 0;
        $precision = $data['data']['customersCount']['precision'] ?? 'exact';

        return response()->json([
            'success'   => true,
            'count'     => $count,
            'precision' => $precision,
        ]);
    }
}
