@extends('layouts.app')

@section('page_content')
<form data-save-bar onsubmit="handleSubmit()" onreset="handleReset()">
    <s-page heading="Settings">
        <input type="hidden" name="active_tab" id="active-tab-input" value="templates">

        <div class="button-group" style="width: fit-content; padding: 3px; background-color: rgb(227, 227, 227); border-radius: 10px; margin-bottom: 15px;">
            <s-button type="button" class="toggle-tabs-btn" variant="tertiary" icon="text-block" data-target="templates">Templates</s-button>
            <s-button type="button" class="toggle-tabs-btn" variant="tertiary" icon="notification" data-target="notifications">Notifications</s-button>
        </div>

        <!-- Each tab panel -->
        <div id="templates" class="tab-content" style="display: flex; flex-direction: column; gap: 15px;">
            <s-stack alignItems="end">
                <s-button variant="secondary" icon="plus" commandFor="add-template-modal">Add Template</s-button>
            </s-stack>
        </div>
        <div id="notifications" class="tab-content" style="display: none; flex-direction: column; gap: 15px;">...</div>
    </s-page>

    <s-modal id="add-template-modal" heading="Add Template" accessibilityLabel="add-template-modal">
        <s-stack gap="small-200">
            <div style="display: flex; flex-direction: row; justify-content: space-between; gap: 10px">
                <s-stack direction="inline" gap="small-300" alignItems="center">
                    <s-text>Status</s-text>
                    <s-badge tone="success">Active</s-badge>
                </s-stack>
                <s-switch name="status" value="1" checked accessibilityLabel="status-switch"></s-switch>
            </div>
            <input type="hidden" name="temp_type" id="temp_type" value="1">

            <s-divider></s-divider>

            <s-grid gridTemplateColumns="repeat(12, 1fr)" gap="base">
                <s-grid-item gridColumn="span 6">
                    <s-clickable
                        id="type-email"
                        class="temp-type-option"
                        data-value="1"
                        border="base"
                        borderRadius="base"
                        padding="base"
                        inlineSize="100%"
                        background="subdued"
                        onclick="selectTempType('1')">
                        <s-stack alignItems="center">Email</s-stack>
                    </s-clickable>
                </s-grid-item>

                <s-grid-item gridColumn="span 6">
                    <s-clickable
                        id="type-message"
                        class="temp-type-option"
                        data-value="2"
                        border="base"
                        borderRadius="base"
                        padding="base"
                        inlineSize="100%"
                        background="primary-subdued"
                        onclick="selectTempType('2')">
                        <s-stack alignItems="center">Message</s-stack>
                    </s-clickable>
                </s-grid-item>
            </s-grid>
            <s-text-field label="Subject" name="subject" value=""></s-text-field>
            <s-text-area label="Message" name="body" value="" rows="3"></s-text-area>
            <s-text>Instruction:</s-text>
            <s-grid gridTemplateColumns="repeat(12, 1fr)" gap="base">
                <s-grid-item gridColumn="span 6" gridRow="span 1">
                    <s-stack direction="inline" alignItems="center" gap="small-300">1. Store Name => <s-button onclick="copyToClipboard(this, '%STORE_NAME%')" variant="secondary">%STORE_NAME%</s-button></s-stack>
                </s-grid-item>
                <s-grid-item gridColumn="span 6" gridRow="span 1">
                    <s-stack direction="inline" alignItems="center" gap="small-300">2. Customer Name => <s-button onclick="copyToClipboard(this, '%CUSTOMER_NAME%')" variant="secondary">%CUSTOMER_NAME%</s-button></s-stack>
                </s-grid-item>
                <s-grid-item gridColumn="span 6" gridRow="span 1">
                    <s-stack direction="inline" alignItems="center" gap="small-300">3. Order Number => <s-button onclick="copyToClipboard(this, '%ORDER_NUMBER%')" variant="secondary">%ORDER_NUMBER%</s-button></s-stack>
                </s-grid-item>
                <s-grid-item gridColumn="span 6" gridRow="span 1">
                    <s-stack direction="inline" alignItems="center" gap="small-300">4. Order Status => <s-button onclick="copyToClipboard(this, '%ORDER_STATUS%')" variant="secondary">%ORDER_STATUS%</s-button></s-stack>
                </s-grid-item>
                <s-grid-item gridColumn="span 6" gridRow="span 1">
                    <s-stack direction="inline" alignItems="center" gap="small-300">5. Tracking Number => <s-button onclick="copyToClipboard(this, '%TRACKING_NUMBER%')" variant="secondary">%TRACKING_NUMBER%</s-button></s-stack>
                </s-grid-item>
                <s-grid-item gridColumn="span 6" gridRow="span 1">
                    <s-stack direction="inline" alignItems="center" gap="small-300">6. Tracking Link => <s-button onclick="copyToClipboard(this, '%TRACKING_LINK%')" variant="secondary">%TRACKING_LINK%</s-button></s-stack>
                </s-grid-item>
            </s-grid>

        </s-stack>
        <s-button slot="secondary-actions" commandFor="add_Temp_Modal" command="--hide">
            Close
        </s-button>
        <s-button type="submit" slot="primary-action" variant="primary" commandFor="add-template-modal" command="--hide">
            Add
        </s-button>

    </s-modal>

</form>
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/settings.js') }}"></script>
@endsection