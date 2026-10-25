@extends('layouts.admin')

@section('title', 'Visit details')
@section('crumb')<a href="{{ route('admin.marketing.traffic.log') }}">Visit log</a>@endsection

@php
    $timeline = $visit->pages->map(fn ($p) => ['at' => $p->created_at, 'type' => 'page', 'text' => $p->path, 'sub' => $p->title])
        ->concat($visit->events->map(fn ($e) => ['at' => $e->created_at, 'type' => 'event', 'text' => \App\Models\VisitEvent::NAMES[$e->name] ?? $e->name, 'sub' => trim(($e->label ?? '').($e->value ? ' · '.money($e->value) : ''), ' ·')]))
        ->sortBy('at')->values();
@endphp

@section('content')
<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h3>What they did</h3><span class="muted" style="font-size:12.5px">{{ $visit->pageviews }} pages · {{ $visit->duration >= 60 ? floor($visit->duration / 60).'m '.($visit->duration % 60).'s' : $visit->duration.'s' }}</span></div>
            <div class="card-body">
                <ul class="timeline">
                    @forelse ($timeline as $item)
                        <li>
                            @if ($item['type'] === 'event')<span class="badge b-green plain" style="margin-right:6px">{{ $item['text'] }}</span>@else<strong style="word-break:break-all">{{ $item['text'] }}</strong>@endif
                            @if ($item['sub'])<span class="muted"> {{ $item['sub'] }}</span>@endif
                            <small>{{ $item['at']->format('d M Y, h:i:s A') }}</small>
                        </li>
                    @empty
                        <li>No pages recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        @if ($history->isNotEmpty())
            <div class="card">
                <div class="card-head"><h3>Earlier visits by the same person</h3></div>
                <div class="table-wrap"><table class="table">
                    <tbody>@foreach ($history as $h)
                        <tr onclick="location='{{ route('admin.marketing.traffic.visit', $h) }}'" style="cursor:pointer"><td>{{ $h->started_at->format('d M Y, h:i A') }}</td><td>{{ $h->source }} / {{ $h->medium }}</td><td class="num">{{ $h->pageviews }} pages</td><td>@if ($h->order_id)<span class="badge b-green plain">Order</span>@endif</td></tr>
                    @endforeach</tbody>
                </table></div>
            </div>
        @endif
    </div>
    <div>
        <div class="card">
            <div class="card-head"><h3>Visitor</h3></div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Started</dt><dd>{{ $visit->started_at->format('d M Y, h:i A') }}</dd>
                    <dt>Location</dt><dd>{{ $visit->flag }} {{ country_name($visit->country) }}{{ $visit->city ? ' · '.$visit->city : '' }}{{ $visit->region ? ', '.$visit->region : '' }}</dd>
                    <dt>Device</dt><dd>{{ ucfirst($visit->device) }} · {{ $visit->browser }} · {{ $visit->os }}{{ $visit->screen_w ? ' · '.$visit->screen_w.'px' : '' }}</dd>
                    <dt>Source</dt><dd>{{ $visit->source }} / {{ $visit->medium }}</dd>
                    @if ($visit->referrer)<dt>Referrer</dt><dd style="word-break:break-all">{{ $visit->referrer }}</dd>@endif
                    @foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm)
                        @if ($visit->{$utm})<dt>{{ $utm }}</dt><dd>{{ $visit->{$utm} }}</dd>@endif
                    @endforeach
                    @if ($visit->click_id)<dt>Ad click</dt><dd>{{ $visit->click_id }}</dd>@endif
                    <dt>Landing page</dt><dd style="word-break:break-all">{{ $visit->landing_path }}</dd>
                    <dt>Language</dt><dd>{{ $visit->language ?: '—' }} · {{ $visit->timezone ?: '—' }}</dd>
                    <dt>IP address</dt><dd>{{ $visit->ip }}</dd>
                    @if ($visit->order)<dt>Order</dt><dd><a href="{{ route('admin.orders.show', $visit->order) }}" style="text-decoration:underline">#{{ $visit->order->order_number }}</a> · {{ money($visit->revenue) }}</dd>@endif
                </dl>
                <p class="cell-sub" style="margin:14px 0 0;word-break:break-all">{{ $visit->user_agent }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
