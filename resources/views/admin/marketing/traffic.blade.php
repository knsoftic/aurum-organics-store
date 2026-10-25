@extends('layouts.admin')

@section('title', 'Traffic')
@section('crumb', 'Marketing')

@section('actions')
    <span class="live-pill" title="Visitors active in the last 5 minutes"><span class="dot"></span> {{ $live }} online now</span>
    <a href="{{ route('admin.marketing.traffic.log', request()->only('range', 'from', 'to')) }}" class="btn btn-light btn-sm"><x-icon name="receipt" /> Visit log</a>
@endsection

@php
    $change = function (string $key) use ($kpi, $previous) {
        $now = $kpi[$key]; $before = $previous[$key];
        if (! $before) return $now ? '<span class="delta up">new</span>' : '';
        $pct = round(($now - $before) / $before * 100);
        $good = $key === 'bounce' ? $pct <= 0 : $pct >= 0;
        return '<span class="delta '.($good ? 'up' : 'down').'">'.($pct >= 0 ? '▲' : '▼').' '.abs($pct).'%</span>';
    };
    $duration = fn (int $s) => $s >= 60 ? floor($s / 60).'m '.($s % 60).'s' : $s.'s';
    $deviceIcon = ['mobile' => '📱', 'tablet' => '📲', 'desktop' => '🖥️'];
    $logLink = fn (array $filter) => route('admin.marketing.traffic.log', request()->only('range', 'from', 'to') + $filter);
@endphp

@section('content')
@include('admin.marketing.partials.range')

@if ($kpi['visits'] === 0 && $previous['visits'] === 0)
    <div class="alert alert-info">No visits recorded yet. Visits appear here as soon as people open the store (your own visits while logged in to the admin are skipped — open the store in a private window to test).</div>
@endif

<div class="grid kpi-grid">
    <div class="card stat"><div class="label">Visitors</div><div class="value">{{ number_format($kpi['visitors']) }}</div><div class="sub">{!! $change('visitors') !!} unique people</div></div>
    <div class="card stat"><div class="label">Visits</div><div class="value">{{ number_format($kpi['visits']) }}</div><div class="sub">{!! $change('visits') !!} sessions</div></div>
    <div class="card stat"><div class="label">Page views</div><div class="value">{{ number_format($kpi['pageviews']) }}</div><div class="sub">{!! $change('pageviews') !!} {{ $kpi['visits'] ? round($kpi['pageviews'] / $kpi['visits'], 1) : 0 }} per visit</div></div>
    <div class="card stat"><div class="label">Bounce rate</div><div class="value">{{ $kpi['bounce'] }}%</div><div class="sub">{!! $change('bounce') !!} left after 1 page</div></div>
    <div class="card stat"><div class="label">Avg. visit</div><div class="value">{{ $duration($kpi['duration']) }}</div><div class="sub">{!! $change('duration') !!} time on site</div></div>
    <div class="card stat dark"><div class="label">Orders from visits</div><div class="value">{{ number_format($kpi['orders']) }}</div><div class="sub">{{ $kpi['conversion'] }}% conversion · {{ money($kpi['revenue']) }}</div></div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-head"><h3>Daily traffic</h3><span class="muted" style="font-size:12.5px">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span></div>
    <div class="card-body"><div style="position:relative;height:280px"><canvas id="trafficChart"></canvas></div></div>
</div>

<div class="grid grid-2" style="margin-top:20px">
    @include('admin.marketing.partials.breakdown', ['title' => 'Channels', 'column' => 'Channel', 'rows' => $channels, 'total' => $kpi['visits'],
        'label' => fn ($r) => e(\App\Http\Controllers\Admin\TrafficController::mediumLabel($r->label)), 'link' => fn ($r) => $logLink(['medium' => $r->label])])
    @include('admin.marketing.partials.breakdown', ['title' => 'Sources', 'column' => 'Source / medium', 'rows' => $sources, 'total' => $kpi['visits'],
        'label' => fn ($r) => '<strong>'.e($r->label).'</strong> <span class="muted">/ '.e($r->medium).'</span>', 'link' => fn ($r) => $logLink(['source' => $r->label])])
    @include('admin.marketing.partials.breakdown', ['title' => 'Countries', 'column' => 'Country', 'rows' => $countries, 'total' => $kpi['visits'],
        'label' => fn ($r) => country_flag($r->label).' '.e(country_name($r->label)), 'link' => fn ($r) => $logLink(['country' => $r->label])])
    @include('admin.marketing.partials.breakdown', ['title' => 'Cities', 'column' => 'City', 'rows' => $cities, 'total' => $kpi['visits'],
        'empty' => $hasGeo ? 'No city data yet for this period.' : 'Add the free GeoLite2 database in Tracking & Pixels → Visitor analytics to see cities.'])
    @include('admin.marketing.partials.breakdown', ['title' => 'Devices', 'column' => 'Device', 'rows' => $devices, 'total' => $kpi['visits'],
        'label' => fn ($r) => ($deviceIcon[$r->label] ?? '').' '.e(ucfirst($r->label)), 'link' => fn ($r) => $logLink(['device' => $r->label])])
    @include('admin.marketing.partials.breakdown', ['title' => 'Browsers & apps', 'column' => 'Browser', 'rows' => $browsers, 'total' => $kpi['visits']])
    @include('admin.marketing.partials.breakdown', ['title' => 'Operating systems', 'column' => 'System', 'rows' => $systems, 'total' => $kpi['visits']])
    @include('admin.marketing.partials.breakdown', ['title' => 'Campaigns (UTM)', 'column' => 'utm_campaign', 'rows' => $campaigns, 'total' => $kpi['visits'],
        'empty' => 'Add ?utm_source=facebook&utm_medium=paid&utm_campaign=eid-sale to your ad links to see each campaign here.'])
    @include('admin.marketing.partials.breakdown', ['title' => 'Landing pages', 'column' => 'First page of the visit', 'rows' => $landing, 'total' => $kpi['visits']])
    @include('admin.marketing.partials.breakdown', ['title' => 'Referring websites', 'column' => 'Website', 'rows' => $referrers, 'total' => $kpi['visits']])
</div>

<div class="grid grid-2" style="margin-top:20px">
    <div class="card">
        <div class="card-head"><h3>Most viewed pages</h3></div>
        @if ($pages->isEmpty())
            <div class="empty" style="padding:30px 20px">No page views yet for this period.</div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Page</th><th class="num">Views</th><th class="num">Visits</th></tr></thead>
                <tbody>@foreach ($pages as $p)<tr><td><a href="{{ url($p->label) }}" target="_blank" class="cell-main" style="font-weight:500;white-space:normal;word-break:break-all">{{ $p->label }}</a></td><td class="num">{{ number_format($p->views) }}</td><td class="num">{{ number_format($p->visits) }}</td></tr>@endforeach</tbody>
            </table></div>
        @endif
    </div>
    <div>
        <div class="card">
            <div class="card-head"><h3>Shopping funnel</h3></div>
            <div class="card-body bar-list">
                @php $top = max(1, reset($funnel)); @endphp
                @foreach ($funnel as $step => $count)
                    <div class="item"><span>{{ $step }}</span><strong>{{ number_format($count) }} <span class="muted" style="font-weight:400">· {{ round($count / $top * 100, 1) }}%</span></strong>
                        <div class="bar"><span style="width:{{ max(1, round($count / $top * 100)) }}%"></span></div></div>
                @endforeach
                <p class="muted" style="margin:6px 0 0;font-size:12px">WhatsApp clicks: <strong>{{ $clicks['whatsapp'] ?? 0 }}</strong> · Contact forms: <strong>{{ $clicks['contact'] ?? 0 }}</strong></p>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>Busiest hours</h3><span class="muted" style="font-size:12px">Best time to run ads &amp; post</span></div>
            <div class="card-body">
                @php $peak = max(1, max($hours)); @endphp
                <div class="hours">
                    @foreach ($hours as $h => $c)
                        <div class="h" title="{{ sprintf('%02d:00', $h) }} — {{ $c }} visits"><span style="height:{{ max(3, round($c / $peak * 100)) }}%"></span><small>{{ $h % 3 === 0 ? $h : '' }}</small></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const d = @json($chart);
    const css = getComputedStyle(document.documentElement);
    const gold = css.getPropertyValue('--gold').trim() || '#7c4dc8', primary = css.getPropertyValue('--primary').trim() || '#3b1a6b';
    new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: { labels: d.labels, datasets: [
            { label: 'Visitors', data: d.visitors, borderColor: primary, backgroundColor: primary + '22', fill: true, tension: .35, pointRadius: 2 },
            { label: 'Page views', data: d.pageviews, borderColor: gold, borderDash: [5, 4], tension: .35, pointRadius: 0 },
            { label: 'Orders', data: d.orders, type: 'bar', backgroundColor: '#2f7a5299', yAxisID: 'y1', barPercentage: .5 },
        ] },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } }, x: { grid: { display: false } } } }
    });
})();
</script>
@endpush
