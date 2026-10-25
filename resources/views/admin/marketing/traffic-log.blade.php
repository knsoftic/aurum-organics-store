@extends('layouts.admin')

@section('title', 'Visit log')
@section('crumb')<a href="{{ route('admin.marketing.traffic', request()->only('range', 'from', 'to')) }}">Traffic</a>@endsection

@section('actions')
    <a href="{{ route('admin.marketing.system-log') }}" class="btn btn-light btn-sm"><x-icon name="alert" /> Error log</a>
@endsection

@php
    $deviceIcon = ['mobile' => '📱', 'tablet' => '📲', 'desktop' => '🖥️'];
    $eventBadge = ['add_to_cart' => ['b-amber', 'Bag'], 'begin_checkout' => ['b-blue', 'Checkout'], 'purchase' => ['b-green', 'Order'], 'whatsapp' => ['b-green', 'WhatsApp'], 'contact' => ['b-violet', 'Contact']];
@endphp

@section('content')
@include('admin.marketing.partials.range')

<form class="toolbar" method="GET">
    @foreach (request()->only('range', 'from', 'to') as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
    <div class="filters">
        <input class="input" name="q" value="{{ request('q') }}" placeholder="IP, page, city, campaign…" style="min-width:200px">
        <select class="select" name="source"><option value="">Any source</option>@foreach ($sources as $s)<option value="{{ $s }}" @selected(request('source') === $s)>{{ $s }}</option>@endforeach</select>
        <select class="select" name="medium"><option value="">Any channel</option>@foreach (\App\Services\Analytics\Source::MEDIUMS as $k => $l)<option value="{{ $k }}" @selected(request('medium') === $k)>{{ $l }}</option>@endforeach</select>
        <select class="select" name="country"><option value="">Any country</option>@foreach ($countries as $c)<option value="{{ $c }}" @selected(request('country') === $c)>{{ country_flag($c) }} {{ country_name($c) }}</option>@endforeach</select>
        <select class="select" name="device"><option value="">Any device</option>@foreach (['mobile', 'tablet', 'desktop'] as $d)<option value="{{ $d }}" @selected(request('device') === $d)>{{ ucfirst($d) }}</option>@endforeach</select>
        <select class="select" name="only"><option value="">All visits</option><option value="cart" @selected(request('only') === 'cart')>Added to bag</option><option value="orders" @selected(request('only') === 'orders')>Placed an order</option></select>
        <button class="btn btn-primary btn-sm">Filter</button>
        @if (request()->hasAny(['q', 'source', 'medium', 'country', 'device', 'only']))<a href="{{ route('admin.marketing.traffic.log', request()->only('range', 'from', 'to')) }}" class="btn btn-light btn-sm">Reset</a>@endif
    </div>
</form>

<div class="card">
    <div class="card-head"><h3>{{ number_format($visits->total()) }} visits</h3><span class="muted" style="font-size:12.5px">Click a row for the full page-by-page trail</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>Location</th><th>Device</th><th>Came from</th><th>Landing page</th><th class="num">Pages</th><th>Actions</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($visits as $visit)
                    <tr onclick="location='{{ route('admin.marketing.traffic.visit', $visit) }}'" style="cursor:pointer">
                        <td><div class="cell-main" style="font-weight:500">{{ $visit->started_at->format('d M, h:i A') }}</div><div class="cell-sub">{{ $visit->started_at->diffForHumans() }}</div></td>
                        <td>{{ $visit->flag }} {{ country_name($visit->country) }}@if ($visit->city)<div class="cell-sub">{{ $visit->city }}{{ $visit->region ? ', '.$visit->region : '' }}</div>@endif</td>
                        <td>{{ $deviceIcon[$visit->device] ?? '' }} {{ ucfirst($visit->device) }}<div class="cell-sub">{{ $visit->browser }} · {{ $visit->os }}</div></td>
                        <td><strong>{{ $visit->source }}</strong> <span class="muted">/ {{ $visit->medium }}</span>@if ($visit->utm_campaign)<div class="cell-sub">Campaign: {{ $visit->utm_campaign }}</div>@elseif ($visit->click_id)<div class="cell-sub">Ad click ({{ $visit->click_id }})</div>@endif</td>
                        <td style="max-width:220px;word-break:break-all" class="cell-sub">{{ $visit->landing_path }}</td>
                        <td class="num">{{ $visit->pageviews }}<div class="cell-sub">{{ $visit->duration >= 60 ? floor($visit->duration / 60).'m' : $visit->duration.'s' }}</div></td>
                        <td>
                            @foreach ($visit->events->unique('name') as $event)
                                @php [$color, $text] = $eventBadge[$event->name] ?? ['b-gray', $event->name]; @endphp
                                <span class="badge plain {{ $color }}" style="margin:1px">{{ $text }}</span>
                            @endforeach
                            @if ($visit->order_id)<div class="cell-sub">{{ money($visit->revenue) }}</div>@endif
                        </td>
                        <td class="cell-sub">{{ $visit->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No visits match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $visits->links() }}
</div>
@endsection
