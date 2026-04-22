@extends('layouts.app')

@section('page_content')

<script>
    window.__selected_products = @json($productsData);
    window.__purchased_products = @json($purchasedProductsData);
    window.__existing_filters = @json($campaign->customer_filters);
</script>

<form data-save-bar onsubmit="handleSubmit(event)" onreset="handleReset()">
    <s-page heading="{{ $campaign->campaign_name }}">
        <s-link slot="breadcrumb-actions" target="_self" href="{{route('campaigns.index',[
          'host' => app('request')->input('host'),
          'shop' => Auth::user()->name
          ])}}">
            Campaigns
        </s-link>

        <s-section heading="Campaign basics">
            <s-grid gap="base">

                <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">

                <s-text-field
                    name="campaign_name"
                    label="Campaign name"
                    value="{{ $campaign->campaign_name }}"
                    autocomplete="off"
                    labelAccessibilityVisibility="visible"
                    placeholder="Spring sale for VIP customers"
                    details="Use a name that clearly describes what this campaign does."
                    required>
                </s-text-field>

                <s-select
                    label="Campaign status"
                    name="campaign_status"
                    value="{{ $campaign->campaign_status === 'active' ? '1' : '0' }}">
                    <s-option value="1" {{ $campaign->campaign_status === 'active' ? 'selected' : '' }}>Active</s-option>
                    <s-option value="0" {{ $campaign->campaign_status === 'inactive' ? 'selected' : '' }}>Inactive</s-option>
                </s-select>

                <s-select
                    name="campaign_type"
                    label="Campaign type"
                    labelAccessibilityVisibility="visible"
                    value="{{ $campaign->campaign_type }}"
                    details="Choose the primary goal or structure of this campaign.">
                    <s-option value="discount" {{ $campaign->campaign_type === 'discount' ? 'selected' : '' }}>
                        Discount
                    </s-option>
                    <s-option value="other" {{ $campaign->campaign_type === 'other' ? 'selected' : '' }}>Other</s-option>
                </s-select>
            </s-grid>
        </s-section>

        <s-section heading="Customer targeting">
            <s-stack border="base" borderRadius="base" gap="small" padding="base">
                <s-stack direction="block" gap="base">
                    <s-text color="subdued">
                        Define which customers are eligible.
                    </s-text>

                    <s-stack
                        direction="inline"
                        justifyContent="space-between"
                        alignItems="center">
                        <s-stack gap="small-200">
                            <s-text type="strong">Current filters</s-text>
                            <s-text color="subdued">
                                Total spent, last order date, purchased products, and order tags
                            </s-text>

                            <s-text type="strong" id="customers-heading">All customers</s-text> <s-text color="subdued" id="customer-filters-summary">(No filters applied)</s-text>
                        </s-stack>

                        <s-button
                            variant="secondary"
                            commandFor="customer-filters-modal"
                            command="--show">
                            Edit filters
                        </s-button>
                    </s-stack>
                </s-stack>
            </s-stack>
        </s-section>

        <s-section heading="Discount code" id="section-discount-code">
            <s-box border="base" borderRadius="base" padding="base">
                <s-stack direction="block" gap="base">
                    <s-text-field
                        required
                        autocomplete="off"
                        name="discount_code"
                        value="{{ $campaign->discount_code }}"
                        label="Discount code"
                        labelAccessibilityVisibility="visible"
                        placeholder="SPRING-SALE-25"
                        details="Enter a code to reuse an existing discount, or generate a new one.">
                    </s-text-field>

                    <s-stack
                        direction="inline"
                        justifyContent="space-between"
                        alignItems="center">
                        <s-button variant="secondary" id="generateCodeBtn">Generate code</s-button>
                    </s-stack>
                </s-stack>
            </s-box>
        </s-section>

        <s-section heading="Discounted products" id="section-discounted-products">
            <s-box border="base" borderRadius="base" padding="base">
                <s-stack direction="block" gap="base">
                    <s-text color="subdued">
                        Choose which products this campaign applies to.
                    </s-text>

                    <s-stack
                        direction="inline"
                        justifyContent="space-between"
                        alignItems="center">
                        <s-stack gap="small-200">
                            <s-text type="strong">Selected products</s-text>
                            <s-text color="subdued" id="selection-summary">No products selected yet</s-text>
                        </s-stack>

                        <s-button
                            variant="secondary"
                            id="select-products-btn"
                            commandFor="products-modal"
                            command="--show">
                            Select products
                        </s-button>
                    </s-stack>

                    <s-stack id="product-list-container">
                    </s-stack>
                </s-stack>
            </s-box>
        </s-section>

        <s-section heading="Campaign scheduling">
            <s-stack direction="block" gap="base">
                <s-text color="subdued">
                    Control when this campaign runs and how long each discount code remains valid.
                </s-text>

                <s-choice-list
                    id="campaign-schedule-mode"
                    name="schedule_type"
                    label="Schedule type"
                    labelAccessibilityVisibility="visible">
                    <s-choice value="monthly" {{ $campaign->schedule_type === 'monthly' ? 'selected' : '' }}>
                        Monthly schedule
                    </s-choice>

                    <s-choice value="custom" {{ $campaign->schedule_type === 'custom' ? 'selected' : '' }}>
                        Custom schedule
                    </s-choice>
                </s-choice-list>

                <s-stack direction="block" gap="base" id="stack-monthly">
                    <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                        <s-select
                            name="monthly_frequency"
                            value="{{ $campaign->monthly_frequency }}"
                            label="Repeat every"
                            labelAccessibilityVisibility="visible"
                            details="Number of months between each campaign run.">
                            <s-option value="1" {{ $campaign->monthly_frequency === '1' ? 'selected' : '' }}>Every month</s-option>
                            <s-option value="2" {{ $campaign->monthly_frequency === '2' ? 'selected' : '' }}>Every 2 months</s-option>
                            <s-option value="3" {{ $campaign->monthly_frequency === '3' ? 'selected' : '' }}>Every 3 months</s-option>
                            <s-option value="4" {{ $campaign->monthly_frequency === '4' ? 'selected' : '' }}>Every 4 months</s-option>
                            <s-option value="5" {{ $campaign->monthly_frequency === '5' ? 'selected' : '' }}>Every 5 months</s-option>
                            <s-option value="6" {{ $campaign->monthly_frequency === '6' ? 'selected' : '' }}>Every 6 months</s-option>
                            <s-option value="7" {{ $campaign->monthly_frequency === '7' ? 'selected' : '' }}>Every 7 months</s-option>
                            <s-option value="8" {{ $campaign->monthly_frequency === '8' ? 'selected' : '' }}>Every 8 months</s-option>
                            <s-option value="9" {{ $campaign->monthly_frequency === '9' ? 'selected' : '' }}>Every 9 months</s-option>
                            <s-option value="10" {{ $campaign->monthly_frequency === '10' ? 'selected' : '' }}>Every 10 months</s-option>
                            <s-option value="11" {{ $campaign->monthly_frequency === '11' ? 'selected' : '' }}>Every 11 months</s-option>
                            <s-option value="12" {{ $campaign->monthly_frequency === '12' ? 'selected' : '' }}>Every 12 months</s-option>
                        </s-select>

                        <s-select
                            name="monthly_validity"
                            label="Discount validity"
                            value="{{ $campaign->monthly_validity }}"
                            labelAccessibilityVisibility="visible"
                            details="How long each discount code is valid after it starts.">
                            <s-option value="2" {{ $campaign->monthly_validity === '2' ? 'selected' : '' }}>2 days</s-option>
                            <s-option value="4" {{ $campaign->monthly_validity === '4' ? 'selected' : '' }}>4 days</s-option>
                            <s-option value="6" {{ $campaign->monthly_validity === '6' ? 'selected' : '' }}>6 days</s-option>
                            <s-option value="8" {{ $campaign->monthly_validity === '8' ? 'selected' : '' }}>8 days</s-option>
                            <s-option value="10" {{ $campaign->monthly_validity === '10' ? 'selected' : '' }}>10 days</s-option>
                        </s-select>
                    </s-grid>
                </s-stack>

                <s-stack direction="block" gap="base" id="stack-custom">
                    <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                        <s-date-field
                            required
                            name="custom_start_date"
                            value="{{ $campaign->custom_start_date }}"
                            label="Start date"
                            labelAccessibilityVisibility="visible"
                            details="The date when this campaign first becomes active."></s-date-field>

                        <s-select
                            id="field-custom-validity"
                            name="custom_validity"
                            value="{{ $campaign->custom_validity }}"
                            label="Discount validity"
                            labelAccessibilityVisibility="visible"
                            details="How long the discount remains valid after the start date.">
                            <s-option value="2" {{ $campaign->custom_validity === '2' ? 'selected' : '' }}>2 days</s-option>
                            <s-option value="4" {{ $campaign->custom_validity === '4' ? 'selected' : '' }}>4 days</s-option>
                            <s-option value="6" {{ $campaign->custom_validity === '6' ? 'selected' : '' }}>6 days</s-option>
                            <s-option value="8" {{ $campaign->custom_validity === '8' ? 'selected' : '' }}>8 days</s-option>
                            <s-option value="10" {{ $campaign->custom_validity === '10' ? 'selected' : '' }}>10 days</s-option>
                        </s-select>
                    </s-grid>
                </s-stack>
            </s-stack>
        </s-section>

        <s-section heading="Template Selection" padding="base">
            <s-stack gap="base">
                <s-select
                    id="message-template-select"
                    name="message_template"
                    label="Message template"
                    value="{{ $campaign->message_template_id }}"
                    required>
                    <s-option value="">Select</s-option>

                    @foreach($messageTemplates as $template)
                    <s-option
                        value="{{ $template->id }}"
                        {{ $campaign->message_template_id == $template->id ? 'selected' : '' }}>
                        {{ $template->name }}
                    </s-option>
                    @endforeach
                </s-select>

                <s-select
                    id="email-template-select"
                    name="email_template"
                    label="Email template"
                    value="{{ $campaign->email_template_id }}"
                    required>
                    <s-option value="">Select</s-option>

                    @foreach($emailTemplates as $template)
                    <s-option
                        value="{{ $template->id }}"
                        {{ $campaign->email_template_id == $template->id ? 'selected' : '' }}>
                        {{ $template->name }}
                    </s-option>
                    @endforeach
                </s-select>
            </s-stack>
        </s-section>

        <s-section heading="Discount rules" id="section-discount-rules">
            @php
            // Determine which discount type is currently active in the database
            $rules = $campaign->discount_rules;
            $activeType = 'percentage_discount'; // default
            if ($rules['fixed']['active'] ?? false) $activeType = 'fixed_amount_discount';
            if ($rules['shipping']['active'] ?? false) $activeType = 'shipping_discount';
            @endphp

            <s-box border="base" borderRadius="base" padding="base">
                <s-stack direction="block" gap="large-100">
                    <s-choice-list
                        label="Choose Discount type"
                        name="discount_type"
                        id="discount-type-choice-list"
                        value="{{ $activeType }}">
                        <s-choice value="percentage_discount" {{ $activeType === 'percentage_discount' ? 'selected' : '' }}>Percentage discount</s-choice>
                        <s-choice value="fixed_amount_discount" {{ $activeType === 'fixed_amount_discount' ? 'selected' : '' }}>Fixed amount discount</s-choice>
                        <s-choice value="shipping_discount" {{ $activeType === 'shipping_discount' ? 'selected' : '' }}>Shipping discount</s-choice>
                    </s-choice-list>

                    <s-stack direction="block" gap="base" id="stack-percentage"
                        style="display: {{ $activeType === 'percentage_discount' ? 'block' : 'none' }};">
                        <s-heading>Percentage discount</s-heading>
                        <s-text color="subdued">Apply a percentage off when the order meets a minimum subtotal.</s-text>
                        <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                            <s-number-field name="percentage_value" label="Discount percentage" suffix="%" min="0" max="100"
                                value="{{ $rules['percentage']['value'] ?? '' }}"></s-number-field>
                            <s-money-field name="percentage_min_subtotal" label="Minimum order subtotal"
                                value="{{ $rules['percentage']['min_subtotal'] ?? '' }}"></s-money-field>
                        </s-grid>
                    </s-stack>

                    <s-stack direction="block" gap="base" id="stack-fixed"
                        style="display: {{ $activeType === 'fixed_amount_discount' ? 'block' : 'none' }};">
                        <s-heading>Fixed amount discount</s-heading>
                        <s-text color="subdued">Take a fixed amount off when the order meets a minimum subtotal.</s-text>
                        <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                            <s-money-field name="fixed_value" label="Discount amount"
                                value="{{ $rules['fixed']['value'] ?? '' }}"></s-money-field>
                            <s-money-field name="fixed_min_subtotal" label="Minimum order subtotal"
                                value="{{ $rules['fixed']['min_subtotal'] ?? '' }}"></s-money-field>
                        </s-grid>
                    </s-stack>

                    <s-stack direction="block" gap="base" id="stack-shipping"
                        style="display: {{ $activeType === 'shipping_discount' ? 'block' : 'none' }};">
                        <s-heading>Shipping discount</s-heading>
                        <s-text color="subdued">Reduce shipping costs when the customer spends a minimum amount.</s-text>
                        <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                            <s-money-field name="shipping_discount_amount" label="Shipping discount amount"
                                value="{{ $rules['shipping']['value'] ?? '' }}"></s-money-field>
                            <s-money-field name="shipping_min_subtotal" label="Minimum order subtotal"
                                value="{{ $rules['shipping']['min_subtotal'] ?? '' }}"></s-money-field>
                        </s-grid>
                    </s-stack>
                </s-stack>
            </s-box>
        </s-section>

        <s-box slot="aside">
            <s-section heading="Campaign summary">
                <s-unordered-list>
                    <s-list-item>
                        <s-text type="strong">Status:</s-text> <s-text id="summary-status"></s-text>
                    </s-list-item>
                    <s-list-item>
                        <s-text type="strong">Type:</s-text> <s-text id="summary-type"></s-text>
                    </s-list-item>
                    <s-list-item>
                        <s-text type="strong">Targeting:</s-text> <s-text id="summary-targeting"></s-text>
                    </s-list-item>
                    <s-list-item>
                        <s-text type="strong">Discounts enabled:</s-text> <s-text id="summary-discounts"></s-text>
                    </s-list-item>
                </s-unordered-list>
            </s-section>
        </s-box>

        <s-modal
            id="customer-filters-modal"
            heading="Customer filters"
            size="base"
            padding="base">
            <s-stack direction="block" gap="large-100">
                <s-banner id="error-banner" tone="info">

                </s-banner>

                <s-section heading="Total amount spent">
                    <s-text color="subdued">
                        Filter customers by the total amount they have spent across all
                        orders.
                    </s-text>
                    <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                        <s-money-field
                            autocomplete="off"
                            name="total_spent_from"
                            label="From"
                            id="filter-spent-from"
                            labelAccessibilityVisibility="visible"
                            placeholder="0.00">
                        </s-money-field>
                        <s-money-field
                            autocomplete="off"
                            name="total_spent_to"
                            label="To"
                            id="filter-spent-to"
                            labelAccessibilityVisibility="visible"
                            placeholder="500.00">
                        </s-money-field>
                    </s-grid>
                </s-section>

                <s-section heading="Last ordered date">
                    <s-text color="subdued">
                        Choose a date range for the customer’s most recent order.
                    </s-text>
                    <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                        <s-date-field
                            name="last_order_from"
                            id="filter-date-from"
                            label="From"
                            labelAccessibilityVisibility="visible">
                        </s-date-field>
                        <s-date-field
                            name="last_order_to"
                            id="filter-date-to"
                            label="To"
                            labelAccessibilityVisibility="visible">
                        </s-date-field>
                    </s-grid>
                </s-section>

                <s-section heading="Products purchased">
                    <s-stack direction="inline" gap="base">
                        <s-text color="subdued">
                            Target customers who have purchased specific products.
                        </s-text>
                    </s-stack>
                    <s-stack direction="inline" justifyContent="space-between" alignItems="center">
                        <s-text color="subdued" id="purchased-products-count">No products selected yet</s-text>

                        <s-button
                            id="choose-purchased-btn"
                            variant="secondary">
                            Choose products
                        </s-button>
                    </s-stack>
                </s-section>

                <s-section heading="Order tags">
                    <s-text color="subdued">
                        Filter customers whose orders contain specific tags.
                    </s-text>

                    <s-stack direction="block" gap="base">
                        <s-text-field
                            id="tag-input-field"
                            name="order_tags"
                            autocomplete="off"
                            label="Add tag"
                            labelAccessibilityVisibility="exclusive"
                            placeholder="VIP, newsletter, high-value">
                        </s-text-field>
                        <s-stack id="chip-container" direction="inline" gap="small-400">
                        </s-stack>
                    </s-stack>
                </s-section>
            </s-stack>

            <s-button id="apply-filters-btn"
                slot="primary-action"
                variant="primary"
                commandFor="customer-filters-modal"
                command="--hide">
                Apply filters
            </s-button>
            <s-button
                slot="secondary-actions"
                commandFor="customer-filters-modal"
                command="--hide">
                Cancel
            </s-button>
        </s-modal>

    </s-page>
</form>
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/update-campaign.js') }}"></script>
@endsection