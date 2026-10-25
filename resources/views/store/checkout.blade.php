@extends('layouts.store')

@section('title', 'Checkout')

@php
    $selectedId = (int) old('payment_method_id', $methods->first()?->id);
    $selectedAdvance = (int) old('advance_method_id', $advanceMethods->first()?->id);
@endphp

@section('content')
<div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('cart.index') }}">Bag</a><span class="sep">/</span><span style="color:var(--ink)">Checkout</span><span class="sep">/</span><span>Confirmation</span>
    </nav>
</div>

<section style="padding-bottom:100px">
    <div class="container">
        @if ($errors->any())
            <div class="alert alert-error">
                <strong>Please check the following:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="layout-2">
            <form id="checkoutForm" method="POST" action="{{ route('checkout.store') }}" enctype="multipart/form-data" novalidate>
                @csrf

                <div class="panel">
                    <h2 class="panel-title"><span class="num">1</span> Contact</h2>
                    <div class="form-grid">
                        <div class="field full">
                            <label for="customer_name">Full name</label>
                            <input id="customer_name" class="input @error('customer_name') is-invalid @enderror" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120" autocomplete="name">
                            @error('customer_name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="phone">Mobile number</label>
                            <input id="phone" class="input @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" required placeholder="03XX XXXXXXX" inputmode="tel" autocomplete="tel">
                            @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="email">Email <span class="opt">(optional — for order updates)</span></label>
                            <input id="email" type="email" class="input @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" autocomplete="email">
                            @error('email')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title"><span class="num">2</span> Delivery address</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label for="city">City</label>
                            <input id="city" class="input @error('city') is-invalid @enderror" name="city" value="{{ old('city') }}" required maxlength="80" list="cities" autocomplete="address-level2">
                            <datalist id="cities">
                                @foreach (['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar', 'Quetta', 'Sialkot', 'Gujranwala', 'Hyderabad', 'Bahawalpur', 'Sargodha', 'Abbottabad', 'Sukkur'] as $city)
                                    <option value="{{ $city }}">
                                @endforeach
                            </datalist>
                            @error('city')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="postal_code">Postal code <span class="opt">(optional)</span></label>
                            <input id="postal_code" class="input" name="postal_code" value="{{ old('postal_code') }}" maxlength="20" autocomplete="postal-code">
                        </div>
                        <div class="field full">
                            <label for="address">Complete address</label>
                            <textarea id="address" class="textarea @error('address') is-invalid @enderror" name="address" required maxlength="500" style="min-height:90px" placeholder="House #, street, area, nearby landmark" autocomplete="street-address">{{ old('address') }}</textarea>
                            @error('address')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field full">
                            <label for="notes">Order notes <span class="opt">(optional)</span></label>
                            <input id="notes" class="input" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Delivery instructions, preferred time…">
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2 class="panel-title"><span class="num">3</span> Payment</h2>

                    @if ($methods->isEmpty())
                        <div class="alert alert-error">No payment methods are available right now. Please contact us to place your order.</div>
                    @endif

                    <div class="pay-options">
                        @foreach ($methods as $method)
                            <div class="pay-option {{ $selectedId === $method->id ? 'selected' : '' }}" data-pay-option data-id="{{ $method->id }}" data-type="{{ $method->type }}" data-advance="{{ $method->requiresAdvance() ? 1 : 0 }}">
                                <label>
                                    <input type="radio" name="payment_method_id" value="{{ $method->id }}" @checked($selectedId === $method->id)>
                                    <img class="logo" src="{{ $method->logo_url }}" alt="">
                                    <span>
                                        <span class="name">{{ $method->name }}</span>
                                        @if ($method->description)<span class="desc">{{ $method->description }}</span>@endif
                                    </span>
                                    @if ((float) $method->discount_percent > 0)
                                        <span class="perk">Save {{ rtrim(rtrim($method->discount_percent, '0'), '.') }}%</span>
                                    @elseif ($method->isCod() && $method->feeFor() > 0)
                                        <span class="perk">+{{ money($method->feeFor()) }} fee</span>
                                    @endif
                                </label>

                                <div class="pay-detail">
                                    @if ($method->isCod())
                                        @if ($method->instructions)<p style="margin-bottom:{{ $method->requiresAdvance() ? '14px' : '0' }}">{{ $method->instructions }}</p>@endif
                                        @if ($method->requiresAdvance())
                                            <div class="advance-note">
                                                To confirm your order, please pay an advance of <strong data-show="advance"></strong> now.
                                                The remaining <strong data-show="cod_remaining"></strong> is paid in cash on delivery.
                                            </div>
                                            @if ($advanceMethods->isEmpty())
                                                <p class="field-error">No advance payment account is configured. Please contact us.</p>
                                            @else
                                                <p style="font-weight:500;margin-bottom:10px">Choose where you sent the advance:</p>
                                                <div class="advance-accounts">
                                                    @foreach ($advanceMethods as $account)
                                                        <div class="advance-account {{ $selectedAdvance === $account->id ? 'selected' : '' }}" data-advance-account>
                                                            <label>
                                                                <input type="radio" name="advance_method_id" value="{{ $account->id }}" @checked($selectedAdvance === $account->id)>
                                                                <img src="{{ $account->logo_url }}" alt=""> {{ $account->name }}
                                                            </label>
                                                            @include('store.partials.account-details', ['method' => $account])
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif
                                    @elseif ($method->isManual())
                                        <div class="amount-callout"><span>Amount to send</span><strong data-show="total"></strong></div>
                                        @include('store.partials.account-details', ['method' => $method])
                                        @if ($method->instructions)<p style="margin:0">{{ $method->instructions }}</p>@endif
                                    @else
                                        <p style="margin:0;display:flex;gap:10px;align-items:center"><x-icon name="lock" style="width:18px;color:var(--gold)" /> {{ $method->instructions ?: 'You will be redirected to a secure payment page to complete your purchase.' }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="proof-block" data-proof-block>
                        <h3 style="font-size:22px;margin:6px 0 4px">Confirm your payment</h3>
                        <p class="muted" style="font-size:14px">Already paid? Enter the transaction ID and upload the screenshot so we can verify it quickly.</p>
                        @include('store.partials.proof-fields')
                    </div>
                </div>

                @if (setting('checkout_note'))
                    <p class="muted" style="font-size:14px;margin:20px 0 0;display:flex;gap:10px"><x-icon name="phone" style="width:18px;flex:none;color:var(--gold)" /> {{ setting('checkout_note') }}</p>
                @endif

                <button type="submit" class="btn btn-gold btn-block" style="margin-top:24px;padding:20px" data-submit-label data-loading="Placing your order…" @disabled($methods->isEmpty())>
                    Place Order
                </button>
                <p class="muted" style="font-size:12.5px;text-align:center;margin-top:12px">
                    By placing your order you agree to our
                    <a href="{{ route('page.show', 'terms-and-conditions') }}" style="text-decoration:underline">terms</a> and
                    <a href="{{ route('page.show', 'return-refund-policy') }}" style="text-decoration:underline">return policy</a>.
                </p>
            </form>

            <aside class="panel summary-panel sticky">
                <h3 style="font-size:28px">Your Order</h3>
                @foreach ($items as $item)
                    <div class="summary-item">
                        <div class="thumb"><img src="{{ $item->product->image_url }}" alt=""><span class="q">{{ $item->qty }}</span></div>
                        <div><h4>{{ $item->product->name }}</h4><small>{{ $item->product->size_label }}</small></div>
                        <span>{{ money($item->total) }}</span>
                    </div>
                @endforeach

                @if ($totals['coupon'])
                    <div class="coupon-applied">
                        <span><x-icon name="tag" style="width:15px;vertical-align:-2px" /> <strong>{{ $totals['coupon']->code }}</strong> applied</span>
                        <form method="POST" action="{{ route('cart.coupon.remove') }}">@csrf @method('DELETE')<button class="remove-link" type="submit">Remove</button></form>
                    </div>
                @else
                    <form method="POST" action="{{ route('cart.coupon') }}" class="coupon-form">
                        @csrf
                        <input class="input" name="code" placeholder="Coupon code" aria-label="Coupon code">
                        <button class="btn btn-outline btn-sm" type="submit">Apply</button>
                    </form>
                    @error('code')<div class="field-error">{{ $message }}</div>@enderror
                @endif

                <div class="totals">
                    <div class="row"><span>Subtotal</span><span data-show="subtotal">{{ money($totals['subtotal']) }}</span></div>
                    <div class="row discount" data-row="discount"><span>Coupon discount</span><span>− <span data-show="discount"></span></span></div>
                    <div class="row"><span>Delivery</span><span data-show="shipping">{{ $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' }}</span></div>
                    <div class="row discount" data-row="payment_discount"><span>Advance payment discount</span><span>− <span data-show="payment_discount"></span></span></div>
                    <div class="row" data-row="payment_fee"><span>Payment fee</span><span data-show="payment_fee"></span></div>
                    <div class="row grand"><span>Total</span><span data-show="total">{{ money($totals['total']) }}</span></div>
                    <div class="row advance" data-row="advance"><span>Pay now (advance)</span><span data-show="advance"></span></div>
                </div>
                <div class="secure-note"><x-icon name="lock" /> Your information is safe with us</div>
            </aside>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const totals = @json($methodTotals);
    const symbol = @json(setting('currency_symbol', 'Rs.'));
    const fmt = (n) => symbol + ' ' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
    const options = document.querySelectorAll('[data-pay-option]');
    const proofBlock = document.querySelector('[data-proof-block]');
    const submit = document.querySelector('[data-submit-label]');

    function render() {
        const checked = document.querySelector('input[name=payment_method_id]:checked');
        if (!checked) return;
        const option = checked.closest('[data-pay-option]');
        options.forEach((o) => o.classList.toggle('selected', o === option));

        const t = totals[checked.value] || {};
        const values = Object.assign({}, t, { shipping: t.shipping > 0 ? fmt(t.shipping) : 'Free', cod_remaining: fmt((t.total || 0) - (t.advance || 0)) });
        document.querySelectorAll('[data-show]').forEach((el) => {
            const key = el.dataset.show;
            if (values[key] === undefined) return;
            el.textContent = typeof values[key] === 'number' ? fmt(values[key]) : values[key];
        });
        ['discount', 'payment_discount', 'payment_fee', 'advance'].forEach((key) => {
            const row = document.querySelector('[data-row="' + key + '"]');
            if (row) row.style.display = t[key] > 0 ? '' : 'none';
        });

        const type = option.dataset.type;
        const needsProof = type === 'manual' || option.dataset.advance === '1';
        proofBlock.classList.toggle('show', needsProof);

        submit.textContent = type === 'gateway'
            ? 'Continue to Secure Payment · ' + fmt(t.total)
            : (t.advance > 0 ? 'Place Order · Pay ' + fmt(t.advance) + ' now' : 'Place Order · ' + fmt(t.total));
    }

    document.addEventListener('change', (e) => {
        if (e.target.name === 'payment_method_id') render();
        if (e.target.name === 'advance_method_id') {
            document.querySelectorAll('[data-advance-account]').forEach((a) => a.classList.toggle('selected', a.contains(e.target)));
        }
    });
    render();
})();
</script>
@endpush
