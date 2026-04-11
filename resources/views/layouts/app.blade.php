@extends('shopify-app::layouts.default')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endsection

<script src="https://cdn.shopify.com/shopifycloud/polaris.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')

@section('content')

{{-- ✅ Shopify Side Navigation Menu --}}
<ui-nav-menu>
    <a href="{{ url('/') }}" rel="home">Home</a>
    <a href="{{ url('/campaign') }}">Campaigns</a>
    <a href="{{ url('/explore') }}">Explore</a>
    <a href="{{ url('/pricing') }}">Pricing</a>
    <a href="{{ url('/settings') }}">Settings</a>
    <a href="{{ url('/guide') }}">Guide</a>
</ui-nav-menu>

<main>
    @yield('page_content')
</main>

@endsection