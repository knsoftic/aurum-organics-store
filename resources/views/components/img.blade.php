{{-- Responsive photo: WebP copies at several widths + width/height so the page does not jump.
     <x-img :src="$url" alt="…" sizes="(max-width: 900px) 100vw, 50vw" priority /> --}}
@props(['src', 'alt' => '', 'sizes' => '100vw', 'priority' => false, 'widths' => null])
@php $img = \App\Services\ImageOptimizer::responsive($src, $widths); @endphp
<img src="{{ $img['src'] }}" @if ($img['srcset']) srcset="{{ $img['srcset'] }}" sizes="{{ $sizes }}" @endif @if ($img['width']) width="{{ $img['width'] }}" height="{{ $img['height'] }}" @endif alt="{{ $alt }}" {{ $attributes->merge(['loading' => $priority ? 'eager' : 'lazy', 'decoding' => 'async']) }} @if ($priority) fetchpriority="high" @endif>
