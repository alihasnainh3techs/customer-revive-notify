@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('page_content')
<s-page>
    <s-section heading="Explore More Apps">
        <div class="d-grid gap-3 md-grid-cols-2">

            @foreach(collect($data)->where('status', '1')->map(fn($app) => [
            'name' => trim($app['name']),
            'plan' => trim($app['plan']),
            'description' => trim($app['description']),
            'url' => $app['app_url'],
            'image' => $app['image'],
            ]) as $app)

            <s-clickable
                href="{{ $app['url'] }}"
                border="base"
                borderRadius="base"
                padding="base"
                inlineSize="100%">
                <s-grid gridTemplateColumns="auto 1fr auto" alignItems="stretch" gap="base">
                    <s-thumbnail
                        size="small"
                        src="{{ $app['image'] }}"
                        alt="{{ $app['name'] }} icon"></s-thumbnail>
                    <s-box>
                        <s-heading>{{ $app['name'] }}</s-heading>
                        <s-paragraph>{{ $app['plan'] }}</s-paragraph>
                        <s-paragraph>
                            {{ Str::limit($app['description'], 80) }}
                        </s-paragraph>
                    </s-box>
                    <s-stack justifyContent="start">
                        <s-button
                            href="{{ $app['url'] }}"
                            icon="download"
                            accessibilityLabel="Download {{ $app['name'] }}"></s-button>
                    </s-stack>
                </s-grid>
            </s-clickable>

            @endforeach

        </div>
    </s-section>
</s-page>
@endsection