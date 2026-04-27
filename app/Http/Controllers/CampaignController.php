<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\Campaign;
use App\Services\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    protected $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function index(Request $request)
    {
        $query = Campaign::where('user_id', Auth::id());

        $totalCampaignsCount = $query->count();

        $campaigns = $query->select(['id', 'campaign_name', 'campaign_status', 'campaign_type', 'discount_code', 'updated_at'])
            ->when($request->search, function ($q, $search) {
                return $q->where('campaign_name', 'like', '%' . $search . '%');
            })
            ->when($request->status && $request->status !== 'all', function ($q) use ($request) {
                return $q->where('campaign_status', $request->status);
            })
            ->when($request->type && $request->type !== 'all', function ($q) use ($request) {
                return $q->where('campaign_type', $request->type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString(); // Keeps filter params in pagination links

        return view('campaigns', compact('campaigns', 'totalCampaignsCount'));
    }

    public function logs(Request $request, Campaign $campaign)
    {
        abort_if($campaign->user_id !== Auth::id(), 403, 'Unauthorized action.');

        $logs = $campaign->logs()->orderBy('created_at', 'desc')->get();

        return view('campaign-logs', [
            'campaign' => $campaign,
            'logs' => $logs
        ]);
    }

    public function create()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $emailTemplates = Template::where('user_id', $user->id)
            ->where('type', 'email')
            ->where('status', true)
            ->get();

        $messageTemplates = Template::where('user_id', $user->id)
            ->where('type', 'message')
            ->where('status', true)
            ->get();

        $integration = $user->integrations()
            ->where('provider', 'texnity')
            ->first();

        $response = $this->integrationService->validateAndFetchTemplates(
            $user->name,
            $integration->configurations['password']
        );

        $templates = [];
        if (isset($response['templates'])) {
            $templates = collect($response['templates'])->pluck('template_name')->toArray();
        }

        return view('create-campaign', [
            'emailTemplates'   => $emailTemplates,
            'messageTemplates' => $messageTemplates,
            'texnityTemplates' => $templates,
        ]);
    }

    public function destroy(Campaign $campaign)
    {
        abort_if($campaign->user_id !== Auth::id(), 403, 'Unauthorized action.');

        $campaign->delete();

        return response()->json([
            'message' => 'Campaign deleted successfully.'
        ]);
    }

    public function edit($id)
    {
        $campaign = Campaign::where('user_id', Auth::id())->findOrFail($id);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $allTemplates = Template::where('user_id', $user->id)
            ->where('status', true)
            ->get();

        $emailTemplates = $allTemplates->where('type', 'email')->whereNull('source');

        $messageTemplates = $allTemplates->where('type', 'message')->whereNull('source');

        $existingTexnityTemplates = $allTemplates->where('source', 'texnity');

        $integration = $user->integrations()
            ->where('provider', 'texnity')
            ->first();

        $response = $this->integrationService->validateAndFetchTemplates(
            $user->name,
            $integration->configurations['password']
        );

        $templates = [];
        if (isset($response['templates'])) {
            $templates = collect($response['templates'])->pluck('template_name')->toArray();
        }

        $selectedIds = json_decode($campaign->selected_products, true) ?: [];
        $productsData = [];

        if (!empty($selectedIds) && is_array($selectedIds)) {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $user->password,
                'Content-Type' => 'application/json',
            ])->post(
                "https://{$user->name}/admin/api/" . env('SHOPIFY_API_VERSION') . "/graphql.json",
                [
                    'query' => <<<'GRAPHQL'
                        query GetVariantsWithProductImage($ids: [ID!]!) {
                            nodes(ids: $ids) {
                                ... on ProductVariant {
                                    id
                                    title
                                    sku
                                    product {
                                        id
                                        title
                                        images(first: 1) {
                                            edges {
                                                node {
                                                    url
                                                    altText
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        GRAPHQL,
                    'variables' => [
                        'ids' => $selectedIds,
                    ],
                ]
            );

            if ($response->successful()) {
                $productsData = collect($response->json('data.nodes', []))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        $purchasedIds = json_decode(
            $campaign->customer_filters['purchased_products'] ?? '[]',
            true
        ) ?: [];
        $purchasedProductsData = [];

        if (!empty($purchasedIds) && is_array($purchasedIds)) {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $user->password,
                'Content-Type' => 'application/json',
            ])->post(
                "https://{$user->name}/admin/api/" . env('SHOPIFY_API_VERSION') . "/graphql.json",
                [
                    'query' => <<<'GRAPHQL'
                    query GetVariantsWithProductImage($ids: [ID!]!) {
                        nodes(ids: $ids) {
                            ... on ProductVariant {
                                id
                                title
                                sku
                                product {
                                    id
                                    title
                                    images(first: 1) {
                                        edges {
                                            node {
                                                url
                                                altText
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    GRAPHQL,
                    'variables' => ['ids' => $purchasedIds],
                ]
            );

            if ($response->successful()) {
                $purchasedProductsData = collect($response->json('data.nodes', []))
                    ->filter()
                    ->values()
                    ->all();
            }
        }

        return view('update-campaign', [
            'campaign'         => $campaign,
            'emailTemplates'   => $emailTemplates,
            'messageTemplates' => $messageTemplates,
            'texnityTemplates'  => $templates,
            'existingTexnity'   => $existingTexnityTemplates,
            'productsData'     => $productsData,
            'purchasedProductsData' => $purchasedProductsData,
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        abort_if($campaign->user_id !== Auth::id(), 403, 'Unauthorized action.');

        $isDiscount      = $request->input('campaign_type') === 'discount';
        $isCustomSchedule = $request->input('schedule_type') === 'custom';
        $discountType = $request->input('discount_type');

        // ── Validation Rules ──────────────────────────────────────────────────
        $rules = [
            'campaign_name'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('campaigns')
                    ->where(fn($query) => $query->where('user_id', Auth::id()))
                    ->ignore($campaign->id),
            ],
            'message_template' => ['required'],
            'email_template'   => ['required'],
        ];

        // discount_code required only when type is discount
        if ($isDiscount) {
            $rules['discount_code'] = ['required', 'string', 'max:255'];
        }

        // custom_start_date required only when schedule is custom
        if ($isCustomSchedule) {
            $rules['custom_start_date'] = ['required', 'date_format:Y-m-d'];
        }

        // Discount rule fields required only when their switch is enabled
        if ($isDiscount && $discountType === 'percentage_discount') {
            $rules['percentage_value']       = ['required', 'numeric', 'min:0', 'max:100'];
            $rules['percentage_min_subtotal'] = ['required', 'numeric', 'min:0'];
        }

        if ($isDiscount && $discountType === 'fixed_amount_discount') {
            $rules['fixed_value']       = ['required', 'numeric', 'min:0'];
            $rules['fixed_min_subtotal'] = ['required', 'numeric', 'min:0'];
        }

        if ($isDiscount && $discountType === 'shipping_discount') {
            $rules['shipping_discount_amount'] = ['required', 'numeric', 'min:0'];
            $rules['shipping_min_subtotal']    = ['required', 'numeric', 'min:0'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'campaign_name.required'          => 'Campaign name is required.',
            'discount_code.required'          => 'A discount code is required for discount campaigns.',
            'custom_start_date.required'      => 'A start date is required for custom schedules.',
            'custom_start_date.date_format'   => 'Start date must be a valid date (YYYY-MM-DD).',
            'message_template.required'       => 'Please select a message template.',
            'email_template.required'         => 'Please select an email template.',
            'percentage_value.required'       => 'Discount percentage is required.',
            'percentage_value.max'            => 'Discount percentage cannot exceed 100.',
            'percentage_min_subtotal.required' => 'Minimum subtotal is required.',
            'fixed_value.required'            => 'Discount amount is required.',
            'fixed_min_subtotal.required'     => 'Minimum subtotal is required.',
            'shipping_discount_amount.required' => 'Shipping discount amount is required.',
            'shipping_min_subtotal.required'  => 'Minimum subtotal is required.',
        ]);

        $validator->validate();

        // ── Build JSON Columns ─────────────────────────────────────────────────
        $discountRules = $isDiscount ? [
            'percentage' => [
                'active' => $discountType === 'percentage_discount',
                'value' => $discountType === 'percentage_discount'
                    ? (float) $request->input('percentage_value')
                    : null,
                'min_subtotal' => $discountType === 'percentage_discount'
                    ? (float) $request->input('percentage_min_subtotal')
                    : null,
            ],

            'fixed' => [
                'active' => $discountType === 'fixed_amount_discount',
                'value' => $discountType === 'fixed_amount_discount'
                    ? (float) $request->input('fixed_value')
                    : null,
                'min_subtotal' => $discountType === 'fixed_amount_discount'
                    ? (float) $request->input('fixed_min_subtotal')
                    : null,
            ],

            'shipping' => [
                'active' => $discountType === 'shipping_discount',
                'value' => $discountType === 'shipping_discount'
                    ? (float) $request->input('shipping_discount_amount')
                    : null,
                'min_subtotal' => $discountType === 'shipping_discount'
                    ? (float) $request->input('shipping_min_subtotal')
                    : null,
            ],
        ] : null;

        $customerFilters = [
            'total_spent' => [
                'from' => $request->filled('total_spent_from') ? (float) $request->input('total_spent_from') : null,
                'to'   => $request->filled('total_spent_to')   ? (float) $request->input('total_spent_to')   : null,
            ],
            'last_order_date' => [
                'from' => $request->filled('last_order_from') ? $request->input('last_order_from') : null,
                'to'   => $request->filled('last_order_to')   ? $request->input('last_order_to')   : null,
            ],
            'tags'              => $request->input('order_tags') ?? [],
            'purchased_products' => $request->input('purchased_products') ?? [],
        ];

        $campaign->update([
            'campaign_name'       => $request->input('campaign_name'),
            'campaign_status'     => $request->input('campaign_status') === '1' ? 'active' : 'inactive',
            'campaign_type'       => $request->input('campaign_type'),
            'discount_code'       => $isDiscount ? $request->input('discount_code') : null,
            'schedule_type'       => $request->input('schedule_type'),
            'monthly_frequency'   => !$isCustomSchedule ? $request->input('monthly_frequency') : null,
            'monthly_validity'    => !$isCustomSchedule ? $request->input('monthly_validity')   : null,
            'custom_start_date'   => $isCustomSchedule  ? $request->input('custom_start_date')  : null,
            'custom_validity'     => $isCustomSchedule  ? $request->input('custom_validity')    : null,
            'selected_products'   => $request->input('selected_products'),
            'discount_rules'      => $discountRules,
            'customer_filters'    => $customerFilters,
            'message_template_id' => $request->input('message_template'),
            'email_template_id'   => $request->input('email_template'),
        ]);

        return response()->json([
            'message'  => 'Campaign updated successfully.',
        ], 200);
    }

    public function store(Request $request)
    {
        $isDiscount      = $request->input('campaign_type') === 'discount';
        $isCustomSchedule = $request->input('schedule_type') === 'custom';
        $discountType = $request->input('discount_type');

        // ── Validation Rules ──────────────────────────────────────────────────
        $rules = [
            'campaign_name'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('campaigns')->where(fn($query) => $query->where('user_id', Auth::id())),
            ],
            'message_template' => ['required'],
            'email_template'   => ['required'],
        ];

        // discount_code required only when type is discount
        if ($isDiscount) {
            $rules['discount_code'] = ['required', 'string', 'max:255'];
        }

        // custom_start_date required only when schedule is custom
        if ($isCustomSchedule) {
            $rules['custom_start_date'] = ['required', 'date_format:Y-m-d'];
        }

        // Discount rule fields required only when their switch is enabled
        if ($isDiscount && $discountType === 'percentage_discount') {
            $rules['percentage_value']       = ['required', 'numeric', 'min:0', 'max:100'];
            $rules['percentage_min_subtotal'] = ['required', 'numeric', 'min:0'];
        }

        if ($isDiscount && $discountType === 'fixed_amount_discount') {
            $rules['fixed_value']       = ['required', 'numeric', 'min:0'];
            $rules['fixed_min_subtotal'] = ['required', 'numeric', 'min:0'];
        }

        if ($isDiscount && $discountType === 'shipping_discount') {
            $rules['shipping_discount_amount'] = ['required', 'numeric', 'min:0'];
            $rules['shipping_min_subtotal']    = ['required', 'numeric', 'min:0'];
        }

        $validator = Validator::make($request->all(), $rules, [
            'campaign_name.required'          => 'Campaign name is required.',
            'discount_code.required'          => 'A discount code is required for discount campaigns.',
            'custom_start_date.required'      => 'A start date is required for custom schedules.',
            'custom_start_date.date_format'   => 'Start date must be a valid date (YYYY-MM-DD).',
            'message_template.required'       => 'Please select a message template.',
            'email_template.required'         => 'Please select an email template.',
            'percentage_value.required'       => 'Discount percentage is required.',
            'percentage_value.max'            => 'Discount percentage cannot exceed 100.',
            'percentage_min_subtotal.required' => 'Minimum subtotal is required.',
            'fixed_value.required'            => 'Discount amount is required.',
            'fixed_min_subtotal.required'     => 'Minimum subtotal is required.',
            'shipping_discount_amount.required' => 'Shipping discount amount is required.',
            'shipping_min_subtotal.required'  => 'Minimum subtotal is required.',
        ]);

        $validator->validate();

        // ── Build JSON Columns ─────────────────────────────────────────────────
        $discountType = $request->input('discount_type');

        // ── Build JSON Columns ─────────────────────────────────────────────────
        $discountRules = $isDiscount ? [
            'percentage' => [
                'active' => $discountType === 'percentage_discount',
                'value' => $discountType === 'percentage_discount'
                    ? (float) $request->input('percentage_value')
                    : null,
                'min_subtotal' => $discountType === 'percentage_discount'
                    ? (float) $request->input('percentage_min_subtotal')
                    : null,
            ],

            'fixed' => [
                'active' => $discountType === 'fixed_amount_discount',
                'value' => $discountType === 'fixed_amount_discount'
                    ? (float) $request->input('fixed_value')
                    : null,
                'min_subtotal' => $discountType === 'fixed_amount_discount'
                    ? (float) $request->input('fixed_min_subtotal')
                    : null,
            ],

            'shipping' => [
                'active' => $discountType === 'shipping_discount',
                'value' => $discountType === 'shipping_discount'
                    ? (float) $request->input('shipping_discount_amount')
                    : null,
                'min_subtotal' => $discountType === 'shipping_discount'
                    ? (float) $request->input('shipping_min_subtotal')
                    : null,
            ],
        ] : null;

        $customerFilters = [
            'total_spent' => [
                'from' => $request->filled('total_spent_from') ? (float) $request->input('total_spent_from') : null,
                'to'   => $request->filled('total_spent_to')   ? (float) $request->input('total_spent_to')   : null,
            ],
            'last_order_date' => [
                'from' => $request->filled('last_order_from') ? $request->input('last_order_from') : null,
                'to'   => $request->filled('last_order_to')   ? $request->input('last_order_to')   : null,
            ],
            'tags'              => $request->input('order_tags') ?? [],
            'purchased_products' => $request->input('purchased_products') ?? [],
        ];

        // ── Persist ────────────────────────────────────────────────────────────
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Message Template
        $messageTemplateId = $request->input('message_template');
        if ($request->input('message_template_source') !== 'app') {
            $messageTemplate = \App\Models\Template::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name'    => $messageTemplateId
                ],
                [
                    'source' => $request->input('message_template_source'),
                    'type'   => 'message'
                ]
            );
            $messageTemplateId = $messageTemplate->id;
        }

        // Email Template
        $emailTemplateId = $request->input('email_template');
        if ($request->input('email_template_source') !== 'app') {
            $emailTemplate = \App\Models\Template::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'name'    => $emailTemplateId
                ],
                [
                    'source' => $request->input('email_template_source'),
                    'type'   => 'email'
                ]
            );
            $emailTemplateId = $emailTemplate->id;
        }

        Campaign::create([
            'user_id'             => $user->id,
            'campaign_name'       => $request->input('campaign_name'),
            'campaign_status'     => $request->input('campaign_status') === '1' ? 'active' : 'inactive',
            'campaign_type'       => $request->input('campaign_type'),
            'discount_code'       => $isDiscount ? $request->input('discount_code') : null,
            'schedule_type'       => $request->input('schedule_type'),
            'monthly_frequency'   => !$isCustomSchedule ? $request->input('monthly_frequency') : null,
            'monthly_validity'    => !$isCustomSchedule ? $request->input('monthly_validity')   : null,
            'custom_start_date'   => $isCustomSchedule  ? $request->input('custom_start_date')  : null,
            'custom_validity'     => $isCustomSchedule  ? $request->input('custom_validity')    : null,
            'selected_products'   => $request->input('selected_products'),
            'discount_type'       => $request->input('discount_type'),
            'discount_rules'      => $discountRules,
            'customer_filters'    => $customerFilters,
            'message_template_id' => $messageTemplateId,
            'email_template_id'   => $emailTemplateId,
        ]);

        return response()->json([
            'message'  => 'Campaign created successfully.',
        ], 201);
    }
}
