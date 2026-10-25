{{-- <link rel="preload"> for the page's main photo, matching the srcset that <x-img> renders. --}}
@props(['src', 'sizes' => '100vw'])
@php $img = \App\Services\ImageOptimizer::responsive($src); @endphp
<link rel="preload" as="image" href="{{ $img['src'] }}" @if ($img['srcset']) imagesrcset="{{ $img['srcset'] }}" imagesizes="{{ $sizes }}" @endif fetchpriority="high">
