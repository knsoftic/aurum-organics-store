@php $gallery = $product->gallery; @endphp
<article class="product-card {{ $product->isInStock() ? '' : 'out-of-stock' }}">
    <a href="{{ route('product.show', $product) }}" class="product-media" aria-label="{{ $product->name }}">
        @if ($product->badge)<span class="badge">{{ $product->badge }}</span>@endif
        @if ($product->discount_percent)<span class="badge sale">−{{ $product->discount_percent }}%</span>@endif
        <x-img class="main" :src="$gallery[0]" :alt="$product->name" sizes="(max-width: 640px) 50vw, 25vw" :widths="[400, 800]" />
        @if (isset($gallery[1]))<x-img class="alt" :src="$gallery[1]" sizes="(max-width: 640px) 50vw, 25vw" :widths="[400, 800]" />@endif
    </a>
    <div class="quick-add">
        @if ($product->isInStock())
            <form action="{{ route('cart.add', $product) }}" method="POST" data-ajax-cart>
                @csrf
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn btn-sm"><x-icon name="bag" /> Add to Bag</button>
            </form>
        @else
            <span class="btn btn-sm" style="width:100%;background:rgba(251,248,242,.95);cursor:default">Sold Out</span>
        @endif
    </div>
    <div class="product-info">
        @if ($product->size_label)<div class="cat">{{ $product->size_label }}</div>@endif
        <h3><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></h3>
        @if ($product->rating_count)
            <div class="rating-line"><x-stars :rating="$product->rating" /> ({{ $product->rating_count }})</div>
        @endif
        <div class="price">
            {{ money($product->price) }}
            @if ($product->discount_percent)<del>{{ money($product->compare_price) }}</del>@endif
        </div>
    </div>
</article>
