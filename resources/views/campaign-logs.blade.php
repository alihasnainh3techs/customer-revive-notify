@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('page_content')
<s-page heading="{{ $campaign->campaign_name }} logs">
    <s-link slot="breadcrumb-actions" target="_self" href="{{route('campaigns.index',[
          'host' => app('request')->input('host'),
          'shop' => Auth::user()->name
          ])}}">
        Campaigns
    </s-link>

    <s-section accessibilityLabel="Empty logs section">
        <s-grid gap="base" justifyItems="center" paddingBlock="large-400">
            <s-box maxInlineSize="200px" maxBlockSize="200px">
                <s-image
                    aspectRatio="1/0.5"
                    src="{{ asset('img/empty-logs.svg') }}"
                    alt="No activity recorded"></s-image>
            </s-box>
            <s-grid
                justifyItems="center"
                maxInlineSize="450px"
                gap="base">
                <s-stack alignItems="center">
                    <s-heading>No activity logs yet</s-heading>
                    <s-paragraph>
                        <p class="text-center">
                            This campaign hasn't generated any events yet.
                        </p>
                    </s-paragraph>
                </s-stack>
            </s-grid>
        </s-grid>
    </s-section>

</s-page>
@endsection