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

    @if($logs->isEmpty())
    {{-- Show Empty State if no logs exist --}}
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
                            This campaign hasn't generated any events yet. Once it starts processing, activity will appear here.
                        </p>
                    </s-paragraph>
                </s-stack>
            </s-grid>
        </s-grid>
    </s-section>
    @else
    {{-- Show Logs Table if data exists --}}
    <s-section accessibilityLabel="Campaign logs table section">
        <s-table id="campaign-logs-table">
            <s-table-header-row>
                <s-table-header listSlot="primary">Customer Name</s-table-header>
                <s-table-header>Customer Email</s-table-header>
                <s-table-header>Customer Phone</s-table-header>
                <s-table-header>Channel</s-table-header>
                <s-table-header listSlot="secondary">Status</s-table-header>
                <s-table-header>Failure Reason</s-table-header>
                <s-table-header>Sent At</s-table-header>
                <s-table-header>Created At</s-table-header>
            </s-table-header-row>
            <s-table-body>
                @foreach($logs as $log)
                <s-table-row>
                    <s-table-cell>{{ $log->customer_name ?? 'N/A' }}</s-table-cell>
                    <s-table-cell>{{ $log->customer_email ?? 'N/A' }}</s-table-cell>
                    <s-table-cell>{{ $log->customer_phone ?? 'N/A' }}</s-table-cell>
                    <s-table-cell>
                        <s-badge>
                            {{ ucfirst($log->channel) }}
                        </s-badge>
                    </s-table-cell>
                    <s-table-cell>
                        @if($log->status === 'sent')
                        <s-badge tone="success">
                            {{ ucfirst($log->status) }}
                        </s-badge>
                        @else
                        <s-badge tone="critical">
                            {{ ucfirst($log->status) }}
                        </s-badge>
                        @endif
                    </s-table-cell>
                    <s-table-cell>{{ $log->failure_reason ?? '-' }}</s-table-cell>
                    <s-table-cell>
                        @if($log->sent_at)
                        <s-stack direction="inline" gap="small-200" alignItems="center">
                            <s-text color="subdued">
                                {{ $log->sent_at->diffForHumans() }}
                            </s-text>
                            <span title="{{ $log->sent_at->format('F j, Y \a\t g:i A') }}">
                                <s-icon type="clock" color="subdued" size="small"></s-icon>
                            </span>
                        </s-stack>
                        @else
                        <s-text color="subdued">Pending</s-text>
                        @endif
                    </s-table-cell>
                    <s-table-cell>
                        <s-stack direction="inline" gap="small-200" alignItems="center">
                            <s-text
                                color="subdued">
                                {{ $log->created_at->diffForHumans() }}
                            </s-text>
                            <span title="{{ $log->created_at->format('F j, Y \a\t g:i A') }}">
                                <s-icon type="clock" color="subdued" size="small"></s-icon>
                            </span>
                        </s-stack>
                    </s-table-cell>
                </s-table-row>
                @endforeach
            </s-table-body>
        </s-table>
    </s-section>
    @endif

</s-page>
@endsection