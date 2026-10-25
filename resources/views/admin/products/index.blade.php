@extends('layouts.admin')

@section('title', 'Products')

@section('actions')
    <a href="{{ route('admin.products.create') }}" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add product</a>
@endsection

@section('content')
<form class="toolbar" method="GET">
    <div class="filters">
        <input class="input" name="q" value="{{ request('q') }}" placeholder="Search name or SKU…">
        <select class="select" name="status" onchange="this.form.submit()">
            <option value="">All products</option>
            <option value="active" @selected(request('status') === 'active')>Visible</option>
            <option value="hidden" @selected(request('status') === 'hidden')>Hidden</option>
        </select>
        <button class="btn btn-primary btn-sm">Search</button>
    </div>
    <span class="muted">Store mode: <strong>{{ setting('store_mode') === 'single' ? 'Single product' : 'Multi product' }}</strong> · <a href="{{ route('admin.settings.edit') }}#storefront" style="text-decoration:underline">change</a></span>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th></th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th class="num">Sold</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td style="width:60px"><img src="{{ $product->image_url }}" class="thumb" alt=""></td>
                        <td>
                            <a href="{{ route('admin.products.edit', $product) }}" class="cell-main">{{ $product->name }}</a>
                            @if ($product->is_featured)<span class="badge plain b-amber" style="margin-left:6px">Featured</span>@endif
                            <div class="cell-sub">{{ $product->sku ?: 'No SKU' }} {{ $product->size_label ? '· '.$product->size_label : '' }}</div>
                        </td>
                        <td>{{ $product->category?->name ?? '—' }}</td>
                        <td class="num"><div class="cell-main">{{ money($product->price) }}</div>@if ($product->compare_price)<div class="cell-sub"><s>{{ money($product->compare_price) }}</s></div>@endif</td>
                        <td class="num">
                            @if (! $product->track_stock)<span class="muted">∞</span>
                            @elseif ($product->stock <= (int) setting('low_stock_threshold', 5))<span class="badge b-red">{{ $product->stock }}</span>
                            @else {{ $product->stock }} @endif
                        </td>
                        <td class="num">{{ (int) $product->sold }}</td>
                        <td>@if ($product->is_active)<span class="badge b-green">Visible</span>@else<span class="badge b-gray">Hidden</span>@endif</td>
                        <td class="actions">
                            <a href="{{ route('product.show', $product) }}" target="_blank" class="icon-action" title="View in store"><x-icon name="eye" /></a>
                            <a href="{{ route('admin.products.edit', $product) }}" class="icon-action" title="Edit"><x-icon name="edit" /></a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline-form" data-confirm="Delete {{ $product->name }}?">
                                @csrf @method('DELETE')
                                <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty">No products yet. <a href="{{ route('admin.products.create') }}" style="text-decoration:underline">Add your first product</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $products->links() }}
</div>
@endsection
