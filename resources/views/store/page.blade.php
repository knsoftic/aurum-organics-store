@extends('layouts.store')

@section('title', $page->meta_title ?: $page->title)
@section('meta_description', $page->meta_description ?: \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($page->content))), 160))

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow center">{{ setting('store_name') }}</span>
        <h1>{{ $page->title }}</h1>
    </div>
</section>

<section class="section" style="padding-top:30px">
    <div class="container narrow rich" style="font-size:17px;font-weight:300;color:var(--muted)">
        {!! safe_html($page->content) !!}
        <p style="margin-top:40px;font-size:14px">Last updated {{ $page->updated_at->format('d M Y') }}</p>
    </div>
</section>
@endsection
