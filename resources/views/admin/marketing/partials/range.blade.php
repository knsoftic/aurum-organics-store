{{-- Date range picker shared by the traffic pages. Expects: $range, $from, $to --}}
<form method="GET" class="toolbar" style="margin-bottom:16px">
    @foreach (request()->except(['range', 'from', 'to', 'page']) as $key => $value)
        @if (is_string($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <div class="tabs" style="margin:0;border:0">
        @foreach (\App\Http\Controllers\Admin\TrafficController::RANGES as $key => $label)
            @continue($key === 'custom')
            <a href="{{ request()->fullUrlWithQuery(['range' => $key, 'from' => null, 'to' => null, 'page' => null]) }}" class="{{ $range === $key ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="filters">
        <input type="hidden" name="range" value="custom">
        <input class="input" type="date" name="from" value="{{ $from->toDateString() }}" style="min-width:0">
        <input class="input" type="date" name="to" value="{{ $to->toDateString() }}" style="min-width:0">
        <button class="btn btn-light btn-sm">Apply</button>
    </div>
</form>
