@extends('layouts.store')

@section('title', $category?->name ?? 'Shop')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow center">{{ setting('store_name') }}</span>
        <h1>{{ $category?->name ?? 'The Collection' }}</h1>
        <p class="lead" style="margin:0 auto">{{ $category?->description ?? 'Discover our full range.' }}</p>
    </div>
</section>

<section class="section" style="padding-top:40px">
    <div class="container">
        <div class="shop-toolbar">
            <div class="chips">
                @if ($categories->count() > 1)
                    <a href="{{ route('shop', request()->except('category', 'page')) }}" class="chip {{ request('category') ? '' : 'active' }}">All</a>
                    @foreach ($categories as $cat)
                        <a href="{{ route('shop', ['category' => $cat->slug] + request()->except('category', 'page')) }}" class="chip {{ request('category') === $cat->slug ? 'active' : '' }}">{{ $cat->name }}</a>
                    @endforeach
                @else
                    <span class="muted" style="font-size:14px">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('product', $products->total()) }}</span>
                @endif
            </div>
            <form method="GET" action="{{ route('shop') }}" style="display:flex;gap:10px;align-items:center">
                @if (request('category'))<input type="hidden" name="category" value="{{ request('category') }}">@endif
                <label for="sort" class="muted" style="font-size:13px">Sort by</label>
                <select id="sort" name="sort" class="select" onchange="this.form.submit()">
                    <option value="">Featured</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                    <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                </select>
            </form>
        </div>

        @if ($products->isEmpty())
            <div class="empty-state">
                <x-icon name="search" />
                <h4>No products found</h4>
                <p class="muted">Please check back soon.</p>
            </div>
        @else
            <div class="product-grid {{ $products->count() <= 3 ? 'cols-3' : '' }}">
                @foreach ($products as $i => $product)
                    <div class="reveal reveal-delay-{{ $i % 4 }}">@include('store.partials.product-card', ['product' => $product])</div>
                @endforeach
            </div>
            {{ $products->links() }}
        @endif
    </div>
</section>
@endsection
