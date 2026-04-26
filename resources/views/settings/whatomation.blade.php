@extends('layouts.app')

@section('page_content')

@php
$action = $integration
? route('settings.whatomation.update', $integration->id)
: route('settings.whatomation.store');
$method = $integration ? 'PATCH' : 'POST';
$config = $integration->configurations ?? [];
@endphp

<form id="whatomation-form" data-save-bar action="{{ $action }}" method="POST">
    @csrf
    @if($integration) @method('PATCH') @endif

    <s-page heading="Configure Texnity">
        <s-link slot="breadcrumb-actions" target="_self" href="{{route('settings', [
            'host' => request('host'),
            'shop' => Auth::user()->name
        ])}}">
            Settings
        </s-link>

        <s-section>
            <s-stack direction="block" gap="base">
                <s-stack direction="inline" gap="small-100" alignItems="center">
                    <s-heading>Whatomation configuration</s-heading>
                    <s-badge tone="{{ ($integration->status ?? false) ? 'success' : 'critical' }}">
                        {{ ($integration->status ?? false) ? 'Active' : 'Inactive' }}
                    </s-badge>
                </s-stack>

                <s-grid gridTemplateColumns="repeat(auto-fit, minmax(400px, 1fr))" gap="base">
                    <s-select
                        name="status"
                        label="Status"
                        value="{{ old('status', isset($integration) ? $integration->status : '') }}"
                        error="{{ $errors->first('status') }}"
                        required>

                        <s-option
                            value="1"
                            @if(isset($integration) && old('status', $integration->status) == 1) selected @endif>
                            Active
                        </s-option>

                        <s-option
                            value="0"
                            @if(isset($integration) && old('status', $integration->status) == 0) selected @endif>
                            Inactive
                        </s-option>
                    </s-select>

                    <s-text-field
                        readOnly
                        label="Device Name"
                        value=""></s-text-field>
                </s-grid>
            </s-stack>
        </s-section>
    </s-page>
</form>

<script>
    document.getElementById('whatomation-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = this;

        // 1. Clear previous errors before starting
        const inputs = form.querySelectorAll('[name]');
        inputs.forEach(input => input.removeAttribute('error'));

        try {
            const token = await shopify.idToken();

            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });

            const data = await response.json();

            if (response.ok) {
                shopify.toast.show(data.message || 'Settings Saved');

                window.location.reload();
            } else if (response.status === 422) {
                // 2. Handle Validation Errors (422)
                shopify.toast.show('Please fix the errors', {
                    isError: true
                });

                Object.keys(data.errors).forEach(key => {
                    // Find the component with the matching name attribute
                    const element = form.querySelector(`[name="${key}"]`);
                    if (element) {
                        // Set the error attribute dynamically
                        element.setAttribute('error', data.errors[key][0]);
                    }
                });
            } else {
                shopify.toast.show(data.message || 'An error occurred', {
                    isError: true
                });
            }
        } catch (error) {
            shopify.toast.show('Request failed', {
                isError: true
            });
        }
    });
</script>
@endsection