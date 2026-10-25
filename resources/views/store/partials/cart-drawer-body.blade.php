@if ($items->isEmpty())
    <div class="empty-state">
        <x-icon name="bag" />
        <h4>Your bag is empty</h4>
        <p class="muted">Discover our collection of pure, raw honey.</p>
        <a href="{{ route('shop') }}" class="btn btn-primary btn-sm" style="margin-top:10px">Start Shopping</a>
    </div>
@else
    @if ($freeShippingRemaining !== null)
        @php $freeAbove = (float) setting('free_shipping_min'); @endphp
        <div class="ship-progress">
            @if ($freeShippingRemaining > 0)
                You're <strong>{{ money($freeShippingRemaining) }}</strong> away from <strong>free delivery</strong>
            @else
                <strong>Congratulations!</strong> Your order ships free.
            @endif
            <div class="track"><span style="width: {{ $freeAbove > 0 ? min(100, round((($freeAbove - $freeShippingRemaining) / $freeAbove) * 100)) : 100 }}%"></span></div>
        </div>
    @endif

    <div class="drawer-items">
        @foreach ($items as $item)
            <div class="drawer-item">
                <a href="{{ route('product.show', $item->product) }}"><img src="{{ media_webp($item->product->image_url, 200) }}" alt="{{ $item->product->name }}" loading="lazy" decoding="async"></a>
                <div>
                    <h4><a href="{{ route('product.show', $item->product) }}">{{ $item->product->name }}</a></h4>
                    <div class="meta">{{ $item->product->size_label }} · {{ money($item->product->price) }}</div>
                    <div class="qty sm" data-cart-qty data-url="{{ route('cart.update', $item->product) }}">
                        <button type="button" data-step="-1" aria-label="Decrease">−</button>
                        <input type="number" value="{{ $item->qty }}" min="0" max="{{ $item->product->maxPurchasable() }}" aria-label="Quantity">
                        <button type="button" data-step="1" aria-label="Increase">+</button>
                    </div>
                </div>
                <div style="text-align:right">
                    <div class="price" style="display:block;margin-bottom:12px">{{ money($item->total) }}</div>
                    <button type="button" class="remove-link" data-cart-remove data-url="{{ route('cart.remove', $item->product) }}">Remove</button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="drawer-foot">
        <div class="row"><span class="muted">Subtotal</span><span>{{ money($totals['subtotal']) }}</span></div>
        @if ($totals['discount'] > 0)
            <div class="row"><span class="muted">Discount ({{ $totals['coupon']->code }})</span><span>− {{ money($totals['discount']) }}</span></div>
        @endif
        <div class="row"><span class="muted">Delivery</span><span>{{ $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' }}</span></div>
        <div class="row total"><span>Total</span><span>{{ money($totals['total']) }}</span></div>
        <a href="{{ route('checkout') }}" class="btn btn-gold btn-block">Checkout <x-icon name="arrow-right" /></a>
        <a href="{{ route('cart.index') }}" class="btn btn-outline btn-block">View Bag</a>
    </div>
@endif
