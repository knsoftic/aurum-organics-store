@extends('layouts.admin')

@section('title', 'Keywords')
@section('crumb', 'Marketing')

@section('actions')
    @if ($gsc['configured'])
        <form method="POST" action="{{ route('admin.marketing.keywords.sync') }}" class="inline-form">@csrf<button class="btn btn-light btn-sm"><x-icon name="refresh" /> Sync Search Console</button></form>
    @endif
    <a href="#add" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add keywords</a>
@endsection

@php
    $ranked = $rows->filter(fn ($r) => $r->position);
    $spark = function (array $points) {
        if (count($points) < 2) return '';
        $w = 90; $h = 26; $min = min($points); $max = max($points); $span = max(1, $max - $min);
        $step = $w / (count($points) - 1);
        // Position 1 is best, so lower numbers are drawn higher.
        $coords = collect($points)->map(fn ($p, $i) => round($i * $step, 1).','.round(3 + ($p - $min) / $span * ($h - 6), 1))->implode(' ');
        return '<svg class="spark" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><polyline fill="none" stroke="currentColor" stroke-width="1.6" points="'.$coords.'"/></svg>';
    };
    $posBadge = fn ($p) => $p === null ? 'b-gray' : ($p <= 3 ? 'b-green' : ($p <= 10 ? 'b-blue' : ($p <= 20 ? 'b-amber' : 'b-gray')));
@endphp

@section('content')
@if ($gsc['configured'])
    @if ($gsc['error'])
        <div class="alert alert-error">{{ $gsc['error'] }}</div>
    @else
        <div class="alert alert-success">Google Search Console connected ({{ $gsc['email'] }}) · last synced {{ $gsc['lastSync'] ? \Carbon\Carbon::parse($gsc['lastSync'])->diffForHumans() : 'never' }}. Positions are 30-day averages from Google; data arrives with a 2–3 day delay.</div>
    @endif
@else
    <div class="alert alert-info">
        <strong>See real Google rankings automatically:</strong> connect Google Search Console (free) in
        <a href="{{ route('admin.marketing.tracking') }}#gsc" style="text-decoration:underline">Tracking &amp; Pixels → Search Console API</a> —
        <a href="{{ route('admin.marketing.seo') }}#gsc-api" style="text-decoration:underline">step-by-step guide</a>.
        Until then you can check a keyword on Google (🔍) and type the position in by hand.
    </div>
@endif

<div class="grid grid-4">
    <div class="card stat"><div class="label">Tracked keywords</div><div class="value">{{ $rows->count() }}</div><div class="sub">{{ $ranked->count() }} with a position</div></div>
    <div class="card stat"><div class="label">Top 3</div><div class="value">{{ $ranked->where('position', '<=', 3)->count() }}</div><div class="sub">best spots on Google</div></div>
    <div class="card stat"><div class="label">Top 10 (page 1)</div><div class="value">{{ $ranked->where('position', '<=', 10)->count() }}</div><div class="sub">{{ $ranked->whereBetween('position', [10.01, 20])->count() }} more on page 2</div></div>
    <div class="card stat dark"><div class="label">Clicks from Google</div><div class="value">{{ number_format($rows->sum('clicks')) }}</div><div class="sub">{{ number_format($rows->sum('impressions')) }} impressions · last 30 days</div></div>
</div>

<div class="card" style="margin-top:20px">
    <div class="card-head"><h3>Your keywords</h3><span class="muted" style="font-size:12.5px">Sorted by priority · best ranking first is position 1</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Keyword</th><th>Target page</th><th class="num">Position</th><th>Trend</th><th class="num">Clicks</th><th class="num">Impr.</th><th class="num">CTR</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows->sortBy(fn ($r) => [$r->keyword->priority, $r->position ?? 999]) as $r)
                    @php $k = $r->keyword; @endphp
                    <tr>
                        <td>
                            <div class="cell-main" style="white-space:normal">{{ $k->keyword }}</div>
                            <div class="cell-sub">
                                <span class="badge plain {{ $k->priority === 1 ? 'b-red' : ($k->priority === 2 ? 'b-amber' : 'b-gray') }}">{{ \App\Models\SeoKeyword::PRIORITIES[$k->priority] }}</span>
                                @if ($k->group)<span class="badge plain b-violet">{{ $k->group }}</span>@endif
                            </div>
                        </td>
                        <td class="cell-sub" style="max-width:180px;word-break:break-all">@if ($k->target_path)<a href="{{ url($k->target_path) }}" target="_blank">{{ $k->target_path }}</a>@else — @endif</td>
                        <td class="num">
                            @if ($r->position)
                                <span class="badge plain {{ $posBadge($r->position) }} rank">{{ $r->position }}</span>
                                @if ($r->move)<span class="rank-move {{ $r->move > 0 ? 'up' : 'down' }}">{{ $r->move > 0 ? '▲' : '▼' }}{{ abs($r->move) }}</span>@endif
                                <div class="cell-sub">{{ $r->source === 'manual' ? 'by hand' : 'Google' }} · {{ $r->checked?->format('d M') }}</div>
                            @else
                                <span class="muted">not ranked yet</span>
                            @endif
                        </td>
                        <td style="color:var(--gold)">{!! $spark($r->spark) !!}</td>
                        <td class="num">{{ $r->impressions ? number_format($r->clicks) : '—' }}</td>
                        <td class="num">{{ $r->impressions ? number_format($r->impressions) : '—' }}</td>
                        <td class="num">{{ $r->ctr !== null ? $r->ctr.'%' : '—' }}</td>
                        <td class="actions">
                            <a href="{{ $k->google_url }}" target="_blank" rel="noopener" class="icon-action" title="Check on Google (Pakistan)"><x-icon name="search" /></a>
                            <details class="kw-menu">
                                <summary class="icon-action" title="Edit / record position"><x-icon name="edit" /></summary>
                                <div class="kw-pop">
                                    <form method="POST" action="{{ route('admin.marketing.keywords.rank', $k) }}" class="flex" style="gap:6px;margin-bottom:12px">
                                        @csrf
                                        <input class="input" type="number" name="position" min="1" max="100" step="1" placeholder="Position on Google" style="padding:7px 10px">
                                        <button class="btn btn-light btn-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.marketing.keywords.update', $k) }}" style="display:grid;gap:8px">
                                        @csrf @method('PUT')
                                        <input class="input" name="keyword" value="{{ $k->keyword }}" required>
                                        <input class="input" name="target_path" value="{{ $k->target_path }}" placeholder="/product/…">
                                        <div class="flex" style="gap:6px">
                                            <input class="input" name="group" value="{{ $k->group }}" placeholder="Group" list="kwGroups">
                                            <select class="select" name="priority">@foreach (\App\Models\SeoKeyword::PRIORITIES as $n => $l)<option value="{{ $n }}" @selected($k->priority === $n)>{{ $l }}</option>@endforeach</select>
                                        </div>
                                        <textarea class="textarea" name="notes" style="min-height:50px" placeholder="Notes">{{ $k->notes }}</textarea>
                                        <button class="btn btn-primary btn-sm">Save keyword</button>
                                    </form>
                                </div>
                            </details>
                            <form method="POST" action="{{ route('admin.marketing.keywords.destroy', $k) }}" class="inline-form" data-confirm="Remove “{{ $k->keyword }}”?">
                                @csrf @method('DELETE')
                                <button class="icon-action danger" title="Remove"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No keywords yet — add some below.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<datalist id="kwGroups">@foreach ($groups as $g)<option value="{{ $g }}">@endforeach</datalist>

<div class="grid grid-2" style="margin-top:20px">
    <div class="card">
        <div class="card-head"><h3>What people searched on Google</h3><span class="muted" style="font-size:12px">From Search Console · 28 days</span></div>
        @if ($queries->isEmpty())
            <div class="empty" style="padding:30px 20px">{{ $gsc['configured'] ? 'No search data yet — new sites take a few weeks to appear.' : 'Connect Search Console to see every search that showed your site, including ones you are not tracking.' }}</div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Search</th><th class="num">Pos.</th><th class="num">Clicks</th><th class="num">Impr.</th><th></th></tr></thead>
                <tbody>@foreach ($queries as $q)
                    <tr>
                        <td style="white-space:normal">{{ $q->query }}</td>
                        <td class="num"><span class="badge plain {{ $posBadge($q->position) }}">{{ round($q->position, 1) }}</span></td>
                        <td class="num">{{ number_format($q->clicks) }}</td>
                        <td class="num">{{ number_format($q->impressions) }}</td>
                        <td class="actions">
                            @if (in_array(mb_strtolower($q->query), $tracked, true))
                                <span class="muted" style="font-size:12px">tracked</span>
                            @else
                                <form method="POST" action="{{ route('admin.marketing.keywords.store') }}" class="inline-form">@csrf<input type="hidden" name="keyword" value="{{ $q->query }}"><input type="hidden" name="priority" value="2"><button class="btn btn-light btn-sm">Track</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </div>

    <div>
        <form method="POST" action="{{ route('admin.marketing.keywords.store') }}" class="card" id="add">
            @csrf
            <div class="card-head"><h3>Add keywords</h3></div>
            <div class="card-body form-grid">
                <div class="field full"><label>Keywords <span class="muted">(one per line)</span></label><textarea class="textarea" name="keyword" style="min-height:90px" placeholder="hair oil for dry hair&#10;best hair oil for women in pakistan" required></textarea></div>
                <div class="field"><label>Target page</label><input class="input" name="target_path" placeholder="/product/…"></div>
                <div class="field"><label>Group</label><input class="input" name="group" list="kwGroups" placeholder="e.g. Benefit"></div>
                <div class="field"><label>Priority</label><select class="select" name="priority">@foreach (\App\Models\SeoKeyword::PRIORITIES as $n => $l)<option value="{{ $n }}" @selected($n === 2)>{{ $l }}</option>@endforeach</select></div>
                <div class="field" style="align-self:end"><button class="btn btn-primary btn-block">Add</button></div>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.marketing.keywords.suggestions') }}" class="card">
            @csrf
            <div class="card-head"><h3>Keyword ideas for your products</h3></div>
            <div class="card-body">
                @if ($suggestions->isEmpty())
                    <p class="muted" style="margin:0">All suggestions are already tracked.</p>
                @else
                    <div style="display:flex;flex-wrap:wrap;gap:8px 14px;max-height:260px;overflow:auto">
                        @foreach ($suggestions as $s)
                            <label style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" name="keywords[]" value="{{ $s['keyword'] }}"> {{ $s['keyword'] }} <span class="muted" style="font-size:11px">{{ $s['group'] }}</span></label>
                        @endforeach
                    </div>
                    <button class="btn btn-light btn-sm" style="margin-top:12px">Add selected</button>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
