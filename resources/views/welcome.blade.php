@extends('layouts.app')

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
                        <s-text><strong>15,240</strong></s-text>
                    </s-stack>
                </s-grid>
            </s-clickable>
            <s-divider direction="block"></s-divider>
            <s-clickable paddingBlock="small-400" paddingInline="small-100" borderRadius="base">
                <s-grid gap="small-300">
                    <s-heading>Active Campaigns</s-heading>
                    <s-stack direction="inline" gap="small-200">
                        <s-text><strong>12</strong></s-text>
                    </s-stack>
                </s-grid>
            </s-clickable>
            <s-divider direction="block"></s-divider>
            <s-clickable paddingBlock="small-400" paddingInline="small-100" borderRadius="base">
                <s-grid gap="small-300">
                    <s-heading>Delivery Success Rate</s-heading>
                    <s-stack direction="inline" gap="small-200">
                        <s-text><strong>98.4%</strong></s-text>
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
                <div class="template-item">
                    <div class="template-info">
                        <s-text><strong>Welcome Message (WhatsApp)</strong></s-text>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: 85%;"></div>
                        </div>
                    </div>
                    <div class="template-meta"><s-text>1,240 used</s-text></div>
                </div>
                <div class="template-item">
                    <div class="template-info">
                        <s-text><strong>Abandoned Cart Recovery (Email)</strong></s-text>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: 60%;"></div>
                        </div>
                    </div>
                    <div class="template-meta"><s-text>890 used</s-text></div>
                </div>
                <div class="template-item">
                    <div class="template-info">
                        <s-text><strong>Discount Flash Sale</strong></s-text>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: 40%;"></div>
                        </div>
                    </div>
                    <div class="template-meta"><s-text>450 used</s-text></div>
                </div>
            </div>
        </s-section>
    </s-stack>
</s-page>
@endsection

@section('scripts')
@parent
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="{{ asset('js/dashboard.js') }}"></script>
@endsection