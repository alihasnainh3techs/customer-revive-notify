@extends('layouts.app')

@section('page_content')
<s-page heading="Configure WhatsApp QR Connect">
    <s-link slot="breadcrumb-actions" target="_self" href="{{route('settings',['host' => request('host'), 'shop' => Auth::user()->name])}}">
        Settings
    </s-link>

    @if(!$device)
    <s-section accessibilityLabel="Empty state section">
        <s-grid gap="base" justifyItems="center" paddingBlock="large-400">
            <s-box maxInlineSize="200px" maxBlockSize="200px">
                <s-image aspectRatio="1/0.5" src="{{ asset('img/qrcode.svg') }}" alt="A Mobile Qr code"></s-image>
            </s-box>
            <s-grid justifyItems="center" maxInlineSize="450px" gap="base">
                <s-stack alignItems="center">
                    <s-heading>No devices yet</s-heading>
                    <s-paragraph>Add devices to configure WhatsApp QR Connect.</s-paragraph>
                </s-stack>
                <!-- ADDED id="add-device-btn" -->
                <s-button id="add-device-btn" commandFor="add-device-modal" variant="primary" icon="plus" accessibilityLabel="Add device">
                    Add Device
                </s-button>
            </s-grid>
        </s-grid>
    </s-section>
    @else
    <s-section accessibilityLabel="Device table section">
        <s-table id="device-table">
            <s-table-header-row>
                <s-table-header listSlot="primary">Name</s-table-header>
                <s-table-header>Status</s-table-header>
                <s-table-header>WhatsApp Notifications</s-table-header>
                <s-table-header listSlot="secondary">Disconnected At</s-table-header>
                <s-table-header>Created At</s-table-header>
                <s-table-header>Action</s-table-header>
            </s-table-header-row>
            <s-table-body>
                <s-table-row>
                    <s-table-cell>{{ \Illuminate\Support\Str::limit($device->name, 25) }}</s-table-cell>
                    <s-table-cell>
                        <s-badge id="device-status-badge" tone="{{ $device->status == 'connected' ? 'success' : 'critical' }}">
                            {{ ucfirst($device->status) }}
                        </s-badge>
                    </s-table-cell>
                    <s-table-cell>
                        <s-switch
                            name="enable_whatsapp"
                            label="Enable WhatsApp notifications"
                            {{ $device->enable_whatsapp ? 'checked' : '' }}
                            id="enable-whatsapp-switch"
                            data-device-id="{{ $device->id }}"
                            value="1">
                        </s-switch>
                    </s-table-cell>
                    <s-table-cell>
                        <s-text color="subdued">
                            {{ $device->disconnected_at ? $device->disconnected_at->diffForHumans() : '—' }}
                        </s-text>
                    </s-table-cell>
                    <s-table-cell>
                        <s-text color="subdued">{{ $device->created_at->diffForHumans() }}</s-text>
                    </s-table-cell>
                    <s-table-cell>
                        <s-stack direction="inline" gap="small-200">
                            <s-button
                                variant="tertiary"
                                tone="critical"
                                icon="delete"
                                class="delete-device-btn"
                                data-device-id="{{ $device->id }}"
                                data-device-name="{{ $device->name }}"
                                accessibilityLabel="Delete device">
                            </s-button>
                        </s-stack>
                    </s-table-cell>
                </s-table-row>
            </s-table-body>
        </s-table>
    </s-section>
    @endif
</s-page>

<!-- Add Device Modal (unchanged, ids already present) -->
<s-modal id="add-device-modal" heading="Add WhatsApp Device">
    <div id="device-form-step">
        <s-text-field id="device-name-input" label="Device name" placeholder="e.g., My Phone" required></s-text-field>
    </div>
    <div id="qr-step" style="display: none;">
        <s-image id="qr-image" src="" alt="QR Code" aspectRatio="1/1"></s-image>
    </div>
    <s-button
        id="submit-device-btn"
        slot="primary-action"
        variant="primary">
        Next
    </s-button>
    <s-button
        id="cancel-add-device"
        slot="secondary-actions"
        variant="secondary">
        Cancel
    </s-button>
</s-modal>

<!-- Delete Confirmation Modal (unchanged) -->
<s-modal id="delete-device-modal" heading="Delete Device">
    <input type="hidden" id="device-id">
    <s-stack gap="base">
        <s-paragraph id="delete-device-name"></s-paragraph>
        <s-text tone="caution">This action cannot be undone.</s-text>
    </s-stack>
    <s-button
        id="confirm-delete-btn"
        slot="primary-action"
        variant="primary"
        tone="critical">
        Delete
    </s-button>
    <s-button
        id="cancel-delete-btn"
        slot="secondary-actions"
        variant="secondary">
        Cancel
    </s-button>
</s-modal>
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/whatsapp.js') }}"></script>
@endsection