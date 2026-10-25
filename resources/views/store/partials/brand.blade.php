{{-- Store logo: uploaded full logo, or the store name as text with an optional round icon. --}}
@php
    $storeName = setting('store_name', config('app.name'));
    $parts = preg_split('/\s+/', $storeName, 2);
    $mark = setting('brand_mark');
@endphp
<a href="{{ route('home') }}" class="brand {{ $mark && ! setting('logo') ? 'has-mark' : '' }}" aria-label="{{ $storeName }} home">
    @if (setting('logo'))
        <img src="{{ media_webp(media_url(setting('logo')), 400) }}" alt="{{ $storeName }}" decoding="async">
    @else
        @if ($mark)<img src="{{ media_webp(media_url($mark), 120) }}" alt="" class="brand-mark" width="46" height="46" decoding="async">@endif
        <span class="brand-text">
            <span class="brand-name">{{ strtoupper($parts[0]) }}</span>
            @if (! empty($parts[1]))<span class="brand-sub">{{ $parts[1] }}</span>@endif
        </span>
    @endif
</a>
