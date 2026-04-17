@extends('layouts.app')

@section('page_content')
<form data-save-bar onsubmit="handleSubmit(event)" onreset="handleReset()">
    <s-page heading="Create campaign">
        <s-link slot="breadcrumb-actions" target="_self" href="{{route('campaigns.index',[
          'host' => app('request')->input('host'),
          'shop' => Auth::user()->name
          ])}}">
            Campaigns
        </s-link>

        <s-section heading="Campaign basics">
            <s-grid gap="base">
                <s-text-field
                    name="campaign_name"
                    label="Campaign name"
                    autocomplete="off"
                    labelAccessibilityVisibility="visible"
                    placeholder="Spring sale for VIP customers"
                    details="Use a name that clearly describes what this campaign does."
                    required>
                </s-text-field>

                <s-select label="Campaign status" name="campaign_status">
                    <s-option value="1">Active</s-option>
                    <s-option value="0">Inactive</s-option>
                </s-select>

                <s-select
                    name="campaign_type"
                    label="Campaign type"
                    labelAccessibilityVisibility="visible"
                    details="Choose the primary goal or structure of this campaign.">
                    <s-option value="discount" selected>
                        Discount
                    </s-option>
                    <s-option value="other">Other</s-option>
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
                    <s-choice value="monthly" selected>
                        Monthly schedule
                    </s-choice>

                    <s-choice value="custom">
                        Custom schedule
                    </s-choice>
                </s-choice-list>

                <s-stack direction="block" gap="base" id="stack-monthly">
                    <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                        <s-select
                            name="monthly_frequency"
                            label="Repeat every"
                            labelAccessibilityVisibility="visible"
                            details="Number of months between each campaign run.">
                            <s-option value="1" selected>Every month</s-option>
                            <s-option value="2">Every 2 months</s-option>
                            <s-option value="3">Every 3 months</s-option>
                            <s-option value="3">Every 4 months</s-option>
                            <s-option value="3">Every 5 months</s-option>
                            <s-option value="3">Every 6 months</s-option>
                            <s-option value="3">Every 7 months</s-option>
                            <s-option value="3">Every 8 months</s-option>
                            <s-option value="3">Every 9 months</s-option>
                            <s-option value="3">Every 10 months</s-option>
                            <s-option value="3">Every 11 months</s-option>
                            <s-option value="12">Every 12 months</s-option>
                        </s-select>

                        <s-select
                            name="monthly_validity"
                            label="Discount validity"
                            labelAccessibilityVisibility="visible"
                            details="How long each discount code is valid after it starts.">
                            <s-option value="2" selected>2 days</s-option>
                            <s-option value="4">4 days</s-option>
                            <s-option value="6">6 days</s-option>
                            <s-option value="8">8 days</s-option>
                            <s-option value="10">10 days</s-option>
                        </s-select>
                    </s-grid>
                </s-stack>

                <s-stack direction="block" gap="base" id="stack-custom">
                    <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                        <s-date-field
                            required
                            name="custom_start_date"
                            label="Start date"
                            labelAccessibilityVisibility="visible"
                            details="The date when this campaign first becomes active."></s-date-field>

                        <s-select
                            id="field-custom-validity"
                            name="custom_validity"
                            label="Discount validity"
                            labelAccessibilityVisibility="visible"
                            details="How long the discount remains valid after the start date.">
                            <s-option value="2" selected>2 days</s-option>
                            <s-option value="4">4 days</s-option>
                            <s-option value="6">6 days</s-option>
                            <s-option value="8">8 days</s-option>
                            <s-option value="10">10 days</s-option>
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
                    placeholder="Select a message template"
                    required>

                    <s-option value="" selected>Select</s-option>

                    @foreach($messageTemplates as $template)
                    <s-option value="{{ $template->id }}">
                        {{ $template->name }}
                    </s-option>
                    @endforeach
                </s-select>

                <s-select
                    id="email-template-select"
                    name="email_template"
                    label="Email template"
                    placeholder="Select an email template"
                    required>

                    <s-option value="" selected>Select</s-option>

                    @foreach($emailTemplates as $template)
                    <s-option value="{{ $template->id }}">
                        {{ $template->name }}
                    </s-option>
                    @endforeach
                </s-select>
            </s-stack>
        </s-section>

        <s-section heading="Discount rules" id="section-discount-rules">
            <s-box border="base" borderRadius="base" padding="base">
                <s-stack direction="block" gap="large-100">
                    <s-stack direction="block" gap="base">
                        <s-heading>Percentage discount</s-heading>
                        <s-text color="subdued">
                            Apply a percentage off when the order meets a minimum subtotal.
                        </s-text>
                        <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                            <s-number-field
                                autocomplete="off"
                                name="percentage_value"
                                label="Discount percentage"
                                labelAccessibilityVisibility="visible"
                                suffix="%"
                                min="0"
                                max="100"
                                placeholder="10">
                            </s-number-field>
                            <s-money-field
                                autocomplete="off"
                                name="percentage_min_subtotal"
                                label="Minimum order subtotal"
                                labelAccessibilityVisibility="visible"
                                placeholder="0.00">
                            </s-money-field>
                        </s-grid>
                        <s-switch
                            name="percentage_active"
                            label="Enable percentage discount"
                            labelAccessibilityVisibility="visible">
                        </s-switch>
                    </s-stack>

                    <s-divider></s-divider>

                    <s-stack direction="block" gap="base">
                        <s-heading>Fixed amount discount</s-heading>
                        <s-text color="subdued">
                            Take a fixed amount off when the order meets a minimum subtotal.
                        </s-text>
                        <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                            <s-money-field
                                autocomplete="off"
                                name="fixed_value"
                                label="Discount amount"
                                labelAccessibilityVisibility="visible"
                                placeholder="0.00">
                            </s-money-field>
                            <s-money-field
                                autocomplete="off"
                                name="fixed_min_subtotal"
                                label="Minimum order subtotal"
                                labelAccessibilityVisibility="visible"
                                placeholder="0.00">
                            </s-money-field>
                        </s-grid>
                        <s-switch
                            name="fixed_active"
                            label="Enable fixed discount"
                            labelAccessibilityVisibility="visible">
                        </s-switch>
                    </s-stack>

                    <s-divider></s-divider>

                    <s-stack direction="block" gap="base">
                        <s-heading>Shipping discount</s-heading>
                        <s-text color="subdued">
                            Reduce shipping costs when the customer spends a minimum amount.
                        </s-text>
                        <s-grid gap="base" gridTemplateColumns="1fr 1fr">
                            <s-money-field
                                autocomplete="off"
                                name="shipping_discount_amount"
                                label="Shipping discount amount"
                                labelAccessibilityVisibility="visible"
                                placeholder="0.00">
                            </s-money-field>
                            <s-money-field
                                autocomplete="off"
                                name="shipping_min_subtotal"
                                label="Minimum order subtotal"
                                labelAccessibilityVisibility="visible"
                                placeholder="0.00">
                            </s-money-field>
                        </s-grid>
                        <s-switch
                            name="shipping_active"
                            label="Enable shipping discount"
                            labelAccessibilityVisibility="visible">
                        </s-switch>
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
<script src="{{ asset('js/create-campaign.js') }}"></script>
@endsection