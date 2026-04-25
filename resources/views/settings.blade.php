@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('page_content')
<s-page heading="Settings">
    <input type="hidden" name="active_tab" id="active-tab-input" value="templates">

    <div class="tab-btn-group">
        <s-button type="button" class="toggle-tabs-btn" variant="tertiary" icon="text-block" data-target="templates" accessibilityLabel="Switch to Templates tab">Templates</s-button>
        <s-button type=" button" class="toggle-tabs-btn" variant="tertiary" icon="notification" data-target="notifications" accessibilityLabel="Switch to Notifications tab">Notifications</s-button>
    </div>

    {{-- Each tab panel --}}
    <div id="templates" class="tab-content" style="display: flex; flex-direction: column; gap: 15px;">
        @if($templates->isNotEmpty())
        <s-stack alignItems="end">
            <s-button variant="primary" icon="plus" accessibilityLabel="Create a new template" commandFor="create-template-modal">Add Template</s-button>
        </s-stack>
        @endif

        @if($templates->isEmpty())
        <s-section accessibilityLabel="Empty state section">
            <s-grid gap="base" justifyItems="center" paddingBlock="large-400">
                <s-box maxInlineSize="200px" maxBlockSize="200px">
                    <s-image
                        aspectRatio="1/0.5"
                        src="{{ asset('img/document.svg') }}"
                        alt="A document"></s-image>
                </s-box>
                <s-grid
                    justifyItems="center"
                    maxInlineSize="450px"
                    gap="base">
                    <s-stack alignItems="center">
                        <s-heading>No templates yet</s-heading>
                        <s-paragraph>
                            Create reusable templates to streamline your workflow.
                        </s-paragraph>
                    </s-stack>
                    <s-button
                        commandFor="create-template-modal"
                        variant="primary"
                        icon="plus"
                        accessibilityLabel="Add a new template">
                        Add template
                    </s-button>
                </s-grid>
            </s-grid>
        </s-section>
        @else
        <s-section accessibilityLabel="Templates table section">
            <s-table
                id="templates-table"
                @if($templates->total() > 10)
                paginate="true"
                @endif

                @if(!$templates->onFirstPage())
                hasPreviousPage="true"
                @endif

                @if($templates->hasMorePages())
                hasNextPage="true"
                @endif
                >
                <s-table-header-row>
                    <s-table-header listSlot="primary">Name</s-table-header>
                    <s-table-header>Type</s-table-header>
                    <s-table-header listSlot="secondary">Status</s-table-header>
                    <s-table-header>Actions</s-table-header>
                </s-table-header-row>
                <s-table-body>
                    @foreach($templates as $template)
                    <s-table-row>
                        <s-table-cell>
                            {{ Str::limit($template->name, 25) }}
                        </s-table-cell>
                        <s-table-cell>
                            <s-badge>
                                {{ ucfirst($template->type) }}
                            </s-badge>
                        </s-table-cell>
                        <s-table-cell>
                            @if($template->status)
                            <s-badge tone="success">Active</s-badge>
                            @else
                            <s-badge tone="critical">Inactive</s-badge>
                            @endif
                        </s-table-cell>
                        <s-table-cell>
                            <s-stack direction="inline" gap="small-200">
                                <s-button
                                    variant="tertiary"
                                    tone="neutral"
                                    icon="edit"
                                    commandFor="update-template-modal"
                                    command="--show"
                                    onclick='selectTemplate("{{ $template->id }}", "{{ addslashes($template->name) }}", "update", @json($template))'
                                    accessibilityLabel="Edit template">
                                </s-button>
                                <s-button
                                    variant="tertiary"
                                    tone="critical"
                                    icon="delete"
                                    commandFor="delete-template-modal"
                                    command="--show"
                                    onclick="selectTemplate('{{ $template->id }}', '{{ addslashes($template->name) }}', 'delete')"
                                    accessibilityLabel="Delete template">
                                </s-button>
                            </s-stack>
                        </s-table-cell>
                    </s-table-row>
                    @endforeach
                </s-table-body>
            </s-table>
            <script>
                window.__pagination = {
                    currentPage: Number('{{ $templates->currentPage() }}'),
                    lastPage: Number('{{ $templates->lastPage() }}'),
                };
            </script>
        </s-section>
        @endif

    </div>
    <div id="notifications" class="tab-content" style="display: none; flex-direction: column; gap: 15px;">
        <s-section heading="Notification Channels">
            <div class="d-grid gap-3 md-grid-cols-2">
                <s-clickable
                    target="_self"
                    href="{{route('settings.smtp.index',[
                    'host' => app('request')->input('host'),
                    'shop' => Auth::user()->name
                    ])}}"
                    border="base"
                    borderRadius="base"
                    padding="base"
                    inlineSize="100%">
                    <s-grid gridTemplateColumns="auto 1fr auto" alignItems="stretch" gap="base">
                        <s-thumbnail
                            size="small"
                            src="{{ asset('img/smtp.svg') }}"
                            alt="SMTP icon"></s-thumbnail>
                        <s-box>
                            <s-heading>SMTP Notifications</s-heading>
                            <s-paragraph>
                                Send email notifications via SMTP.
                            </s-paragraph>
                        </s-box>
                        <s-stack justifyContent="start">
                            <s-button
                                target="_self"
                                href="{{route('settings.smtp.index',[
                                'host' => app('request')->input('host'),
                                'shop' => Auth::user()->name
                                ])}}"
                                icon="settings"
                                accessibilityLabel="Configure SMTP email notifications"></s-button>
                        </s-stack>
                    </s-grid>
                </s-clickable>
                <s-clickable
                    target="_self"
                    href="{{route('settings.whatsapp.index',[
                    'host' => app('request')->input('host'),
                    'shop' => Auth::user()->name
                    ])}}"
                    border="base"
                    borderRadius="base"
                    padding="base"
                    inlineSize="100%">
                    <s-grid gridTemplateColumns="auto 1fr auto" alignItems="stretch" gap="base">
                        <s-thumbnail
                            size="small"
                            src="https://otn.kiz.app/logos/wa-notify.svg"
                            alt="Whatsapp Notifications icon"></s-thumbnail>
                        <s-box>
                            <s-heading>WhatsApp QR Connect</s-heading>
                            <s-paragraph>
                                Scan a QR code to enable WhatsApp notifications.
                            </s-paragraph>
                        </s-box>
                        <s-stack justifyContent="start">
                            <s-button
                                target="_self"
                                href="{{route('settings.whatsapp.index',[
                                'host' => app('request')->input('host'),
                                'shop' => Auth::user()->name
                                ])}}"
                                icon="settings"
                                accessibilityLabel="Configure WhatsApp QR Connect notifications"></s-button>
                        </s-stack>
                    </s-grid>
                </s-clickable>

                <s-clickable
                    href="https://apps.shopify.com/planet"
                    border="base"
                    borderRadius="base"
                    padding="base"
                    inlineSize="100%">
                    <s-grid gridTemplateColumns="auto 1fr auto" alignItems="stretch" gap="base">
                        <s-thumbnail
                            size="small"
                            src="https://cdn.shopify.com/app-store/listing_images/8c5469073674bc4bb7aa9adb6f30b97b/icon/CKSuysvvvJADEAE=.jpeg"
                            alt="Whatomation icon"></s-thumbnail>
                        <s-box>
                            <s-stack gap="small-100" direction="inline" alignItems="center">
                                <s-heading>Whatomation Notifications</s-heading>
                                <s-badge tone="info">Recommended</s-badge>
                            </s-stack>
                            <s-paragraph>
                                Send whatsapp notifications via Whatomation.
                            </s-paragraph>
                        </s-box>
                        <s-stack justifyContent="start">
                            @php $installed = in_array('whatomation', $data['apps'] ?? []); @endphp
                            <s-button
                                href="https://apps.shopify.com/planet"
                                icon="{{ $installed ? 'settings' : 'download' }}"
                                accessibilityLabel="Configure Whatomation whatsapp notifications"></s-button>
                        </s-stack>
                    </s-grid>
                </s-clickable>

                <s-clickable
                    href="https://apps.shopify.com/planet"
                    border="base"
                    borderRadius="base"
                    padding="base"
                    inlineSize="100%">
                    <s-grid gridTemplateColumns="auto 1fr auto" alignItems="stretch" gap="base">
                        <s-thumbnail
                            size="small"
                            src="https://cdn.shopify.com/app-store/listing_images/b8d47fd829850cef4c0c87e26213b9d4/icon/COmB7rzY0o4DEAE=.png"
                            alt="Texnity icon"></s-thumbnail>
                        <s-box>
                            <s-heading>Texnity</s-heading>
                            <s-paragraph>
                                Send whatsapp notifications via Texnity.
                            </s-paragraph>
                        </s-box>
                        <s-stack justifyContent="start">
                            @php $installed = in_array('texnity', $data['apps'] ?? []); @endphp
                            <s-button
                                href="https://apps.shopify.com/planet"
                                icon="{{ $installed ? 'settings' : 'download' }}"
                                accessibilityLabel="Configure Texnity notifications"></s-button>
                        </s-stack>
                    </s-grid>
                </s-clickable>

                <s-clickable
                    href="https://apps.shopify.com/planet"
                    border="base"
                    borderRadius="base"
                    padding="base"
                    inlineSize="100%">
                    <s-grid gridTemplateColumns="auto 1fr auto" alignItems="stretch" gap="base">
                        <s-thumbnail
                            size="small"
                            src="https://cdn.shopify.com/app-store/listing_images/32423517ef64f95da50dea5837ae5f36/icon/COSPnbL0lu8CEAE=.png"
                            alt="Branded SMS Pakistan icon"></s-thumbnail>
                        <s-box>
                            <s-heading>Branded SMS Pakistan</s-heading>
                            <s-paragraph>
                                Send branded SMS notifications via Branded SMS Pakistan.
                            </s-paragraph>
                        </s-box>
                        <s-stack justifyContent="start">
                            @php $installed = in_array('bsp', $data['apps'] ?? []); @endphp
                            <s-button
                                href="https://apps.shopify.com/planet"
                                icon="{{ $installed ? 'settings' : 'download' }}"
                                accessibilityLabel="Configure Branded SMS Pakistan notifications"></s-button>
                        </s-stack>
                    </s-grid>
                </s-clickable>
            </div>
        </s-section>
    </div>
</s-page>

<form id="create-template-form">
    <s-modal id="create-template-modal" heading="Add Template" accessibilityLabel="create-template-modal">
        <s-stack gap="base" direction="block">
            <div style="display: flex; flex-direction: row; justify-content: space-between; gap: 10px">
                <s-stack direction="inline" gap="small-300" alignItems="center">
                    <s-text>Status</s-text>
                    <s-badge id="status-badge" tone="success">Active</s-badge>
                </s-stack>
                <s-switch
                    id="status-switch"
                    name="status"
                    value="1"
                    checked
                    accessibilityLabel="status-switch">
                </s-switch>
            </div>

            <s-divider></s-divider>

            <s-grid gridTemplateColumns="repeat(12, 1fr)" gap="base">
                <s-grid-item gridColumn="span 6">
                    <s-clickable
                        id="btn-email"
                        border="base"
                        borderRadius="base"
                        padding="small"
                        inlineSize="100%"
                        background="subdued"
                        onclick="selectType('email','create')">
                        <s-stack alignItems="center">Email</s-stack>
                    </s-clickable>
                </s-grid-item>

                <s-grid-item gridColumn="span 6">
                    <s-clickable
                        id="btn-message"
                        border="base"
                        borderRadius="base"
                        padding="small"
                        inlineSize="100%"
                        background="primary-subdued"
                        onclick="selectType('message','create')">
                        <s-stack alignItems="center">Message</s-stack>
                    </s-clickable>
                </s-grid-item>
            </s-grid>

            <s-divider></s-divider>

            <s-text-field required label="Template name" name="template_name" autocomplete="off"></s-text-field>
            <div id="subject-field">
                <s-text-field required label="Subject" name="subject" autocomplete="off"></s-text-field>
            </div>
            <s-text-area required label="Message" name="body" rows="3"></s-text-area>

            <s-divider></s-divider>

            <!-- Variables section -->
            <s-paragraph color="subdued">
                Use these variables in your subject or message.
            </s-paragraph>

            <div id="variables-container" class="var-group">
            </div>
        </s-stack>

        <s-button id="create-template-btn-close" slot="secondary-actions" commandFor="create-template-modal" command="--hide">
            Close
        </s-button>
        <s-button id="create-template-btn-submit" type="submit" slot="primary-action" variant="primary">
            Add
        </s-button>

    </s-modal>

</form>

<form id="update-template-form">
    <s-modal id="update-template-modal" heading="Update Template" accessibilityLabel="update-template-modal">
        <s-stack gap="base" direction="block">
            <div style="display: flex; flex-direction: row; justify-content: space-between; gap: 10px">
                <s-stack direction="inline" gap="small-300" alignItems="center">
                    <s-text>Status</s-text>
                    <s-badge id="update-status-badge" tone="success">Active</s-badge>
                </s-stack>
                <s-switch
                    id="update-status-switch"
                    name="update-status"
                    value="1"
                    checked
                    accessibilityLabel="status-switch">
                </s-switch>
            </div>

            <s-divider></s-divider>

            <input type="hidden" name="id" value="">

            <s-grid gridTemplateColumns="repeat(12, 1fr)" gap="base">
                <s-grid-item gridColumn="span 6">
                    <s-clickable
                        id="update-btn-email"
                        border="base"
                        borderRadius="base"
                        padding="small"
                        inlineSize="100%"
                        background="subdued"
                        onclick="selectType('email','update')">
                        <s-stack alignItems="center">Email</s-stack>
                    </s-clickable>
                </s-grid-item>

                <s-grid-item gridColumn="span 6">
                    <s-clickable
                        id="update-btn-message"
                        border="base"
                        borderRadius="base"
                        padding="small"
                        inlineSize="100%"
                        background="primary-subdued"
                        onclick="selectType('message','update')">
                        <s-stack alignItems="center">Message</s-stack>
                    </s-clickable>
                </s-grid-item>
            </s-grid>

            <s-divider></s-divider>

            <s-text-field required label="Template name" name="update_template_name" autocomplete="off"></s-text-field>
            <div id="update-subject-field">
                <s-text-field required label="Subject" name="update_subject" autocomplete="off"></s-text-field>
            </div>
            <s-text-area required label="Message" name="update_body" rows="3"></s-text-area>

            <s-divider></s-divider>

            <!-- Variables section -->
            <s-paragraph color="subdued">
                Use these variables in your subject or message.
            </s-paragraph>

            <div id="update-variables-container" class="var-group">
            </div>
        </s-stack>

        <s-button id="update-template-btn-close" slot="secondary-actions" commandFor="update-template-modal" command="--hide">
            Close
        </s-button>
        <s-button id="update-template-btn-submit" type="submit" slot="primary-action" variant="primary">
            Update
        </s-button>

    </s-modal>

</form>

<form id="delete-template-form">
    <s-modal id="delete-template-modal" heading="Delete template?">
        <s-stack gap="base">
            <s-text id="modal-delete-template-name"></s-text>
            <s-text tone="caution">This action cannot be undone.</s-text>
        </s-stack>

        <input type="hidden" name="id">

        <s-button
            id="confirm-delete-template-modal"
            slot="primary-action"
            variant="primary"
            type="submit"
            tone="critical">
            Delete template
        </s-button>
        <s-button
            id="cancel-delete-template-modal"
            slot="secondary-actions"
            variant="secondary"
            commandFor="delete-template-modal"
            command="--hide">
            Cancel
        </s-button>
    </s-modal>
</form>
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/settings.js') }}"></script>
@endsection