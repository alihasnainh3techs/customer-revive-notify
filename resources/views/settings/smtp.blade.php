@extends('layouts.app')

@section('page_content')

<form data-save-bar onsubmit="handleSubmit(event)" onreset="handleReset()">
    <s-page heading="Configure SMTP">
        <s-link slot="breadcrumb-actions" target="_self" href="{{route('settings',[
          'host' => app('request')->input('host'),
          'shop' => Auth::user()->name
          ])}}">
            Settings
        </s-link>

        <s-section>
            <s-stack direction="block" gap="base">
                <s-stack direction="inline" gap="small-100" alignItems="center">
                    <s-heading>SMTP configuration</s-heading>
                    @if(isset($config) && $config->status)
                    <s-badge tone="success">Active</s-badge>
                    @else
                    <s-badge tone="critical">Inactive</s-badge>
                    @endif
                </s-stack>

                <s-grid gridTemplateColumns="repeat(auto-fit, minmax(400px, 1fr))" gap="base">

                    <input type="hidden" name="id" value="{{ $config->id ?? '' }}">

                    <s-select
                        name="service"
                        label="Default email service"
                        value="{{ $config->service ?? '' }}"
                        required>
                        <s-option
                            value="default"
                            {{ ($config->service ?? 'default') === 'default' ? 'selected' : '' }}>
                            Use default email
                        </s-option>

                        <s-option
                            value="custom"
                            {{ ($config->service ?? '') === 'custom' ? 'selected' : '' }}>
                            Use custom SMTP
                        </s-option>
                    </s-select>
                    <s-select
                        name="status"
                        label="Status"
                        value="{{ ($config->status ?? false) ? '1' : '0' }}"
                        required>
                        <s-option
                            value="1"
                            {{ ($config->status ?? false) ? 'selected' : '' }}>
                            Active
                        </s-option>

                        <s-option
                            value="0"
                            {{ !($config->status ?? true) ? 'selected' : '' }}>
                            Inactive
                        </s-option>
                    </s-select>
                    <s-text-field
                        autocomplete="off"
                        name="smtp_host"
                        value="{{ $config->smtp_host ?? '' }}"
                        label="SMTP host"
                        placeholder="smtp.your-provider.com"
                        required>
                    </s-text-field>

                    <s-number-field
                        autocomplete="off"
                        name="port"
                        value="{{ $config->port ?? '' }}"
                        label="Port"
                        placeholder="587"
                        min={1}
                        max={65535}
                        required>
                    </s-number-field>

                    <s-select
                        autocomplete="off"
                        name="security_type"
                        value="{{ $config->security_type ?? '' }}"
                        label="Security"
                        required>
                        <s-option value="none" {{ ($config->security_type ?? '') === 'none'  ? 'selected' : '' }}>None</s-option>
                        <s-option value="ssl" {{ ($config->security_type ?? '') === 'ssl'   ? 'selected' : '' }}>SSL / SMTPS (465)</s-option>
                        <s-option value="tls" {{ ($config->security_type ?? '') === 'tls'   ? 'selected' : '' }}>STARTTLS (587)</s-option>
                    </s-select>

                    <s-text-field
                        autocomplete="off"
                        name="username"
                        value="{{ $config->username ?? '' }}"
                        label="Username"
                        placeholder="SMTP username"
                        required>
                    </s-text-field>

                    <s-password-field
                        autocomplete="off"
                        name="password"
                        value="{{ $config->password ?? '' }}"
                        label="Password"
                        placeholder="SMTP password"
                        required>
                    </s-password-field>

                    <s-text-field
                        autocomplete="off"
                        name="custom_from_email"
                        value="{{ $config->custom_from_email ?? '' }}"
                        label="Custom From / Reply‑To"
                        placeholder="notifications@yourdomain.com">
                    </s-text-field>
                </s-grid>
            </s-stack>
        </s-section>
    </s-page>
</form>
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/smtp.js') }}"></script>
@endsection