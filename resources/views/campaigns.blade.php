@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('page_content')
<form method="GET" action="{{ route('campaigns.index') }}">
    <input type="hidden" name="host" value="{{ app('request')->input('host') }}">
    <input type="hidden" name="shop" value="{{ Auth::user()->name }}">

    <s-page>
        <s-stack alignItems="end" paddingBlockEnd="base">
            @if($campaigns->isNotEmpty())
            <s-button target="_self" href="{{route('campaigns.create',[
          'host' => app('request')->input('host'),
          'shop' => Auth::user()->name
          ])}}" type="button" variant="primary" icon="plus" accessibilityLabel="Create a new campaign">
                Create Campaign
            </s-button>
            @endif
        </s-stack>
        @if($totalCampaignsCount === 0)
        <s-section accessibilityLabel="Empty state section">
            <s-grid gap="base" justifyItems="center" paddingBlock="large-400">
                <s-box maxInlineSize="200px" maxBlockSize="200px">
                    <s-image
                        aspectRatio="1/0.5"
                        src="{{ asset('img/campaign.svg') }}"
                        alt="A document"></s-image>
                </s-box>
                <s-grid
                    justifyItems="center"
                    maxInlineSize="450px"
                    gap="base">
                    <s-stack alignItems="center">
                        <s-heading>No campaigns yet</s-heading>
                        <s-paragraph>
                            <p class="text-center">
                                Start your first campaign to connect and engage with your audience.
                            </p>
                        </s-paragraph>
                    </s-stack>
                    <s-button target="_self" href="{{route('campaigns.create',[
                    'host' => app('request')->input('host'),
                    'shop' => Auth::user()->name
                    ])}}" type="button" variant="primary" icon="plus" accessibilityLabel="Create a new campaign">
                        Create Campaign
                    </s-button>
                </s-grid>
            </s-grid>
        </s-section>
        @else
        <s-section accessibilityLabel="Campaign table section">
            <s-table
                id="campaigns-table"
                @if($campaigns->total() > 10)
                paginate="true"
                @endif

                @if(!$campaigns->onFirstPage())
                hasPreviousPage="true"
                @endif

                @if($campaigns->hasMorePages())
                hasNextPage="true"
                @endif
                >
                <s-grid slot="filters" gap="small-200" gridTemplateColumns="1fr 1fr 1fr auto">
                    <s-text-field
                        label="Search campaigns"
                        name="search"
                        value="{{ request('search') }}"
                        autocomplete="off"
                        labelAccessibilityVisibility="exclusive"
                        placeholder="Searching all campaigns">
                    </s-text-field>

                    <s-select label="Campaign Status" name="status" value="{{ request('status', 'all') }}" labelAccessibilityVisibility="exclusive">
                        <s-option value="all" {{ request('status') == 'all' ? 'selected' : '' }}>All Statuses</s-option>
                        <s-option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</s-option>
                        <s-option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</s-option>
                    </s-select>

                    <s-select label="Campaign type" name="type" value="{{ request('type', 'all') }}" labelAccessibilityVisibility="exclusive">
                        <s-option value="all" {{ request('type') == 'all' ? 'selected' : '' }}>All Types</s-option>
                        <s-option value="discount" {{ request('type') == 'discount' ? 'selected' : '' }}>Discount</s-option>
                        <s-option value="other" {{ request('type') == 'other' ? 'selected' : '' }}>Other</s-option>
                    </s-select>

                    <s-button type="submit" variant="secondary">
                        Search
                    </s-button>
                </s-grid>
                <s-table-header-row>
                    <s-table-header listSlot="primary">Campaign</s-table-header>
                    <s-table-header listSlot="secondary">Status</s-table-header>
                    <s-table-header>Type</s-table-header>
                    <s-table-header>Discount Code</s-table-header>
                    <s-table-header>Updated At</s-table-header>
                    <s-table-header>Actions</s-table-header>
                </s-table-header-row>
                <s-table-body>
                    @foreach($campaigns as $campaign)
                    <s-table-row>
                        <s-table-cell>
                            {{ Str::limit($campaign->campaign_name, 25) }}
                        </s-table-cell>
                        <s-table-cell>
                            @if($campaign->campaign_status === 'active')
                            <s-badge tone="success">Active</s-badge>
                            @else
                            <s-badge tone="critical">Inactive</s-badge>
                            @endif
                        </s-table-cell>
                        <s-table-cell>
                            <s-badge>
                                {{ ucfirst($campaign->campaign_type) }}
                            </s-badge>
                        </s-table-cell>
                        <s-table-cell>
                            @if($campaign->discount_code)
                            {{ $campaign->discount_code }}
                            @else
                            <s-text color="subdued">—</s-text>
                            @endif
                        </s-table-cell>
                        <s-table-cell>
                            <s-stack direction="inline" gap="small-200" alignItems="center">
                                <s-text
                                    color="subdued"
                                    title="{{ $campaign->updated_at->format('F j, Y \a\t g:i A') }}">
                                    {{ $campaign->updated_at->diffForHumans() }}
                                </s-text>
                                <span title="{{ $campaign->updated_at->format('F j, Y \a\t g:i A') }}">
                                    <s-icon type="clock" color="subdued" size="small"></s-icon>
                                </span>
                            </s-stack>
                        </s-table-cell>
                        <s-table-cell>
                            <s-stack direction="inline" gap="small-200">
                                <s-button
                                    target="_self"
                                    href="{{route('campaigns.edit', [
                                'campaign' => $campaign->id,
                                'host' => app('request')->input('host'),
                                'shop' => Auth::user()->name
                                ])}}"
                                    variant="tertiary"
                                    tone="neutral"
                                    icon="edit"
                                    accessibilityLabel="Edit campaign">
                                </s-button>
                                <s-button
                                    variant="tertiary"
                                    tone="critical"
                                    onclick='selectCampaign(`@json($campaign)`)'
                                    icon="delete"
                                    commandFor="delete-campaign-modal"
                                    command="--show"
                                    accessibilityLabel="Delete campaign">
                                </s-button>
                            </s-stack>
                        </s-table-cell>
                    </s-table-row>
                    @endforeach
                </s-table-body>
            </s-table>
            <script>
                window.__pagination = {
                    currentPage: Number('{{ $campaigns->currentPage() }}'),
                    lastPage: Number('{{ $campaigns->lastPage() }}'),
                };
            </script>
        </s-section>
        @endif
    </s-page>
</form>

<form id="delete-campaign-form">
    <s-modal id="delete-campaign-modal" heading="Delete campaign?">
        <s-stack gap="base">
            <s-text id="modal-delete-campaign-name"></s-text>
            <s-text tone="caution">This action cannot be undone.</s-text>
        </s-stack>

        <input type="hidden" name="id">

        <s-button
            id="confirm-delete-campaign-modal"
            slot="primary-action"
            variant="primary"
            type="submit"
            tone="critical">
            Delete campaign
        </s-button>
        <s-button
            id="cancel-delete-campaign-modal"
            slot="secondary-actions"
            variant="secondary"
            commandFor="delete-campaign-modal"
            command="--hide">
            Cancel
        </s-button>
    </s-modal>
</form>
@endsection

@section('scripts')
@parent
<script src="{{ asset('js/campaigns.js') }}"></script>
@endsection