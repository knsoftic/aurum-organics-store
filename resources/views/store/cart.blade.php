@extends('layouts.store')

@section('title', 'Your Bag')

@section('content')
<section class="page-hero" style="padding-bottom:30px">
    <div class="container">
        <span class="eyebrow center">Shopping</span>
        <h1>Your Bag</h1>
    </div>
</section>

<section class="section" style="padding-top:40px">
    <div class="container">
        @if ($errors->has('cart'))<div class="alert alert-error">{{ $errors->first('cart') }}</div>@endif

        @if ($items->isEmpty())
            <div class="empty-state">
                <x-icon name="bag" />
                <h4>Your bag is empty</h4>
                <p class="muted">Looks like you haven't added anything yet.</p>
                <a href="{{ route('shop') }}" class="btn btn-primary" style="margin-top:12px">Continue Shopping</a>
            </div>
        @else
            <div class="layout-2">
                <div>
                    <table class="cart-table">
                        <thead><tr><th>Product</th><th>Quantity</th><th style="text-align:right">Total</th></tr></thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>
                                        <div class="cart-product">
                                            <a href="{{ route('product.show', $item->product) }}"><img src="{{ $item->product->image_url }}" alt="{{ $item->product->name }}"></a>
                                            <div>
                                                <h3><a href="{{ route('product.show', $item->product) }}">{{ $item->product->name }}</a></h3>
                                                <div class="muted" style="font-size:14px">{{ $item->product->size_label }} · {{ money($item->product->price) }}</div>
                                                <button type="button" class="remove-link" style="margin-top:10px" data-cart-remove data-reload data-url="{{ route('cart.remove', $item->product) }}">Remove</button>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="qty sm" data-cart-qty data-reload data-url="{{ route('cart.update', $item->product) }}">
                                            <button type="button" data-step="-1" aria-label="Decrease">−</button>
                                            <input type="number" value="{{ $item->qty }}" min="1" max="{{ $item->product->maxPurchasable() }}" aria-label="Quantity">
                                            <button type="button" data-step="1" aria-label="Increase">+</button>
                                        </div>
                                    </td>
                                    <td style="text-align:right" class="price">{{ money($item->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <a href="{{ route('shop') }}" class="link-underline" style="display:inline-block;margin-top:30px">← Continue Shopping</a>
                </div>

                <aside class="panel summary-panel sticky">
                    <h3 style="font-size:28px">Order Summary</h3>

                    @if ($freeShippingRemaining !== null)
                        <p class="muted" style="font-size:14px;margin:0">
                            @if ($freeShippingRemaining > 0)
                                Add <strong>{{ money($freeShippingRemaining) }}</strong> more for free delivery.
                            @else
                                <strong style="color:var(--success)">Your order qualifies for free delivery.</strong>
                            @endif
                        </p>
                    @endif

                    @if ($totals['coupon'])
                        <div class="coupon-applied">
                            <span><x-icon name="tag" style="width:15px;vertical-align:-2px" /> <strong>{{ $totals['coupon']->code }}</strong> — {{ $totals['coupon']->label }}</span>
                            <form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE')<button class="remove-link" type="submit">Remove</button></form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('cart.coupon') }}" class="coupon-form">
                            @csrf
                            <input class="input {{ $errors->has('code') ? 'is-invalid' : '' }}" name="code" placeholder="Coupon code" value="{{ old('code') }}" aria-label="Coupon code">
                            <button class="btn btn-outline btn-sm" type="submit">Apply</button>
                        </form>
                        @error('code')<div class="field-error">{{ $message }}</div>@enderror
                    @endif

                    <div class="totals">
                        <div class="row"><span>Subtotal</span><span>{{ money($totals['subtotal']) }}</span></div>
                        @if ($totals['discount'] > 0)
                            <div class="row discount"><span>Coupon discount</span><span>− {{ money($totals['discount']) }}</span></div>
                        @endif
                        <div class="row"><span>Delivery</span><span>{{ $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' }}</span></div>
                        <div class="row grand"><span>Total</span><span>{{ money($totals['total']) }}</span></div>
                    </div>
                    <a href="{{ route('checkout') }}" class="btn btn-gold btn-block" style="margin-top:20px">Proceed to Checkout <x-icon name="arrow-right" /></a>
                    <div class="secure-note"><x-icon name="lock" /> Secure checkout · Cash on Delivery available</div>
                </aside>
            </div>
        @endif
    </div>
</section>
@endsection
