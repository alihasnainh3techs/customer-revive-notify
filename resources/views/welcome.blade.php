@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('page_content')

<s-page heading="Dashboard">
    <s-section padding="base">
        <s-grid
            gridTemplateColumns="@container (inline-size <= 400px) 1fr, 1fr auto 1fr auto 1fr"
            gap="small">
            <s-clickable paddingBlock="small-400" paddingInline="small-100" borderRadius="base">
                <s-grid gap="small-300">
                    <s-heading>Total Outreach</s-heading>
                    <s-stack direction="inline" gap="small-200">
                        <s-text>
                            <strong>
                                {{ number_format($totalOutreach) }}
                            </strong>
                        </s-text>
                    </s-stack>
                </s-grid>
            </s-clickable>
            <s-divider direction="block"></s-divider>
            <s-clickable paddingBlock="small-400" paddingInline="small-100" borderRadius="base">
                <s-grid gap="small-300">
                    <s-heading>Active Campaigns</s-heading>
                    <s-stack direction="inline" gap="small-200">
                        <s-text>
                            <strong>
                                {{ number_format($activeCampaigns) }}
                            </strong>
                        </s-text>
                    </s-stack>
                </s-grid>
            </s-clickable>
            <s-divider direction="block"></s-divider>
            <s-clickable paddingBlock="small-400" paddingInline="small-100" borderRadius="base">
                <s-grid gap="small-300">
                    <s-heading>Delivery Success Rate</s-heading>
                    <s-stack direction="inline" gap="small-200">
                        <s-text>
                            <strong>
                                {{ $successRate }}%
                            </strong>
                        </s-text>
                    </s-stack>
                </s-grid>
            </s-clickable>
        </s-grid>
    </s-section>

    <s-stack direction="block" gap="small">
        <s-section>
            <div class="campaign-charts">
                <s-box heading="Daily Message Volume">
                    <div style="height: 300px;">
                        <canvas id="volumeChart"></canvas>
                    </div>
                </s-box>
                <s-box heading="Campaign Type Distribution">
                    <div style="height: 300px;">
                        <canvas id="typeChart"></canvas>
                    </div>
                </s-box>
            </div>
        </s-section>

        <s-section heading="Popular Templates">
            <div class="template-list">
                @php
                // Set the max usage for percentage calculation based on the top template
                $maxUsage = $popularTemplates->first()->total ?? 1;
                @endphp

                @forelse($popularTemplates as $template)
                <div class="template-item">
                    <div class="template-info">
                        <s-text><strong>{{ $template->name }}</strong></s-text>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill"
                                style="width: {{ ($template->total / $maxUsage) * 100 }}%;">
                            </div>
                        </div>
                    </div>
                    <div class="template-meta">
                        <s-text>{{ number_format($template->total) }} used</s-text>
                    </div>
                </div>
                @empty
                <s-text color="subdued">No template activity recorded yet.</s-text>
                @endforelse
            </div>
        </s-section>
    </s-stack>
</s-page>
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    window.DashboardData = {
        volumeLabels: @json($chartLabels),
        volumeData: @json($chartData),
        distLabels: @json($distribution->keys()),
        distData: @json($distribution->values())
    };
</script>
<script src="{{ asset('js/dashboard.js') }}"></script>
@endsection