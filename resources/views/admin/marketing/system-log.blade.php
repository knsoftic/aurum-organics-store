@extends('layouts.admin')

@section('title', 'Error log')
@section('crumb')<a href="{{ route('admin.marketing.traffic') }}">Traffic</a>@endsection

@php $colors = ['emergency' => 'b-red', 'alert' => 'b-red', 'critical' => 'b-red', 'error' => 'b-red', 'warning' => 'b-amber', 'notice' => 'b-blue', 'info' => 'b-blue', 'debug' => 'b-gray']; @endphp

@section('content')
<div class="toolbar">
    <div class="tabs" style="margin:0;border:0">
        <a href="{{ route('admin.marketing.system-log') }}" class="{{ ! request('level') ? 'active' : '' }}">All</a>
        @foreach (['error', 'warning', 'info'] as $level)
            <a href="{{ route('admin.marketing.system-log', ['level' => $level]) }}" class="{{ request('level') === $level ? 'active' : '' }}">{{ ucfirst($level) }}</a>
        @endforeach
    </div>
    <span class="muted" style="font-size:12.5px">storage/logs/laravel.log · {{ number_format($size / 1024, 1) }} KB · newest first</span>
</div>

<div class="card">
    @forelse ($entries as $entry)
        <details class="log-entry">
            <summary>
                <span class="badge plain {{ $colors[$entry['level']] ?? 'b-gray' }}">{{ $entry['level'] }}</span>
                <span class="muted" style="font-size:12px;white-space:nowrap">{{ $entry['time'] }}</span>
                <span class="msg">{{ $entry['message'] }}</span>
            </summary>
            <pre>{{ $entry['detail'] }}</pre>
        </details>
    @empty
        <div class="empty">No log entries — the site has not reported any errors.</div>
    @endforelse
</div>
@endsection
