@extends('layouts.store')

@section('title', $page->title)

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
