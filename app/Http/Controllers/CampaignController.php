<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::where('user_id', Auth::id())
            ->select([
                'id',
                'campaign_name',
                'campaign_status',
                'campaign_type',
                'discount_code',
                'updated_at'
            ])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('campaigns', compact('campaigns'));
    }

    public function create()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $emailTemplate = Template::where('user_id', $user->id)
            ->where('type', 'email')
            ->where('status', true)
            ->first();
        $messageTemplate = Template::where('user_id', $user->id)
            ->where('type', 'message')
            ->where('status', true)
            ->first();

        return view('create-campaign', [
            'emailTemplate'   => $emailTemplate,
            'messageTemplate' => $messageTemplate,
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

        $user = Auth::user();
        $emailTemplate = Template::where('user_id', $user->id)
            ->where('type', 'email')
            ->where('status', true)
            ->first();
        $messageTemplate = Template::where('user_id', $user->id)
            ->where('type', 'message')
            ->where('status', true)
            ->first();

        return view('update-campaign', [
            'campaign'        => $campaign,
            'emailTemplate'   => $emailTemplate,
            'messageTemplate' => $messageTemplate,
        ]);
    }

    public function update(Request $request)
    {
        return view('update-campaign');
    }

    public function store(Request $request)
    {
        $isDiscount      = $request->input('campaign_type') === 'discount';
        $isCustomSchedule = $request->input('schedule_type') === 'custom';

        // s-switch submits 'on' when checked; boolean() handles on/1/true/yes
        $percentageActive = $request->boolean('percentage_active');
        $fixedActive      = $request->boolean('fixed_active');
        $shippingActive   = $request->boolean('shipping_active');

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
        if ($isDiscount && $percentageActive) {
            $rules['percentage_value']       = ['required', 'numeric', 'min:0', 'max:100'];
            $rules['percentage_min_subtotal'] = ['required', 'numeric', 'min:0'];
        }

        if ($isDiscount && $fixedActive) {
            $rules['fixed_value']       = ['required', 'numeric', 'min:0'];
            $rules['fixed_min_subtotal'] = ['required', 'numeric', 'min:0'];
        }

        if ($isDiscount && $shippingActive) {
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
            'percentage_value.required'       => 'Discount percentage is required when percentage discount is enabled.',
            'percentage_value.max'            => 'Discount percentage cannot exceed 100.',
            'percentage_min_subtotal.required' => 'Minimum subtotal is required when percentage discount is enabled.',
            'fixed_value.required'            => 'Discount amount is required when fixed discount is enabled.',
            'fixed_min_subtotal.required'     => 'Minimum subtotal is required when fixed discount is enabled.',
            'shipping_discount_amount.required' => 'Shipping discount amount is required when shipping discount is enabled.',
            'shipping_min_subtotal.required'  => 'Minimum subtotal is required when shipping discount is enabled.',
        ]);

        // At least one discount rule must be enabled for discount-type campaigns
        if ($isDiscount) {
            $validator->after(function ($v) use ($percentageActive, $fixedActive, $shippingActive) {
                if (!$percentageActive && !$fixedActive && !$shippingActive) {
                    $v->errors()->add(
                        'discount_rules',
                        'At least one discount rule (percentage, fixed, or shipping) must be enabled.'
                    );
                }
            });
        }

        $validator->validate();

        // ── Build JSON Columns ─────────────────────────────────────────────────
        $discountRules = $isDiscount ? [
            'percentage' => [
                'active'      => $percentageActive,
                'value'       => $percentageActive ? (float) $request->input('percentage_value') : null,
                'min_subtotal' => $percentageActive ? (float) $request->input('percentage_min_subtotal') : null,
            ],
            'fixed' => [
                'active'      => $fixedActive,
                'value'       => $fixedActive ? (float) $request->input('fixed_value') : null,
                'min_subtotal' => $fixedActive ? (float) $request->input('fixed_min_subtotal') : null,
            ],
            'shipping' => [
                'active'      => $shippingActive,
                'value'       => $shippingActive ? (float) $request->input('shipping_discount_amount') : null,
                'min_subtotal' => $shippingActive ? (float) $request->input('shipping_min_subtotal') : null,
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
            'discount_rules'      => $discountRules,
            'customer_filters'    => $customerFilters,
            'message_template_id' => $request->input('message_template'),
            'email_template_id'   => $request->input('email_template'),
        ]);

        return response()->json([
            'message'  => 'Campaign created successfully.',
        ], 201);
    }
}
