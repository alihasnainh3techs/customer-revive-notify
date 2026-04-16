@extends('shopify-app::layouts.default')

@section('styles')
<script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endsection

@push('scripts')
<script src="{{ asset('js/app.js') }}"></script>
@endpush

@section('content')

{{-- Shopify Side Navigation Menu --}}
<ui-nav-menu>
    <a href="{{ url('/') }}" rel="home">Home</a>
    <a href="{{ url('/campaigns') }}">Campaigns</a>
    <a href="{{ url('/explore') }}">Explore</a>
    <a href="{{ url('/pricing') }}">Pricing</a>
    <a href="{{ url('/settings') }}">Settings</a>
    <a href="{{ url('/guide') }}">Guide</a>
</ui-nav-menu>

<main>
    @yield('page_content')
</main>

@endsection