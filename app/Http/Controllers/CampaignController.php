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
        $tags      = $request->input('tags', []);
        $spentFrom = $request->input('totalSpent.from');
        $spentTo   = $request->input('totalSpent.to');
        $dateFrom  = $request->input('lastOrderDate.from');
        $dateTo    = $request->input('lastOrderDate.to');

        // Build the Segment Query String parts
        $queryParts = [];

        // Tags: Uses CONTAINS and single quotes
        foreach ($tags as $tag) {
            $queryParts[] = "customer_tags CONTAINS '" . addslashes($tag) . "'";
        }

        // Amount Spent: Uses amount_spent
        if (!is_null($spentFrom)) {
            $queryParts[] = "amount_spent >= " . (float) $spentFrom;
        }
        if (!is_null($spentTo)) {
            $queryParts[] = "amount_spent <= " . (float) $spentTo;
        }

        // Dates: Uses last_order_date and quoted YYYY-MM-DD
        if (!is_null($dateFrom)) {
            $formattedDate = date('Y-m-d', strtotime($dateFrom));
            $queryParts[] = "last_order_date >= '" . $formattedDate . "'";
        }
        if (!is_null($dateTo)) {
            $formattedDate = date('Y-m-d', strtotime($dateTo));
            $queryParts[] = "last_order_date <= '" . $formattedDate . "'";
        }

        // Join with AND
        $queryString = implode(' AND ', $queryParts);

        // Call Shopify using your verified Segment logic
        $user = Auth::user();
        $version = env('SHOPIFY_API_VERSION', '2025-01');

        $graphql = <<<GQL
    query {
      customerSegmentMembers(first: 1, query: "{$queryString}") {
        totalCount
      }
    }
    GQL;

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $user->password,
            'Content-Type'           => 'application/json',
        ])->post("https://{$user->name}/admin/api/{$version}/graphql.json", [
            'query' => $graphql,
        ]);

        if ($response->failed()) {
            return response()->json(['success' => false, 'message' => 'API Error'], 502);
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            return response()->json(['success' => false, 'errors' => $data['errors']], 422);
        }

        // Correctly pathing to the totalCount based on your working syntax
        $count = $data['data']['customerSegmentMembers']['totalCount'] ?? 0;

        return response()->json([
            'success' => true,
            'count'   => $count,
            'debug_query' => $queryString // Shows you the exact string sent to Shopify
        ]);
    }
}
