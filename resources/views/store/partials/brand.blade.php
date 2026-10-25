{{-- Store logo: uploaded full logo, or the store name as text with an optional round icon. --}}
@php
    $storeName = setting('store_name', config('app.name'));
    $parts = preg_split('/\s+/', $storeName, 2);
    $mark = setting('brand_mark');
@endphp
<a href="{{ route('home') }}" class="brand {{ $mark && ! setting('logo') ? 'has-mark' : '' }}" aria-label="{{ $storeName }} home">
    @if (setting('logo'))
        <img src="{{ media_url(setting('logo')) }}" alt="{{ $storeName }}">
    @else
        @if ($mark)<img src="{{ media_url($mark) }}" alt="" class="brand-mark">@endif
        <span class="brand-text">
            <span class="brand-name">{{ strtoupper($parts[0]) }}</span>
            @if (! empty($parts[1]))<span class="brand-sub">{{ $parts[1] }}</span>@endif
        </span>
    @endif
</a>
