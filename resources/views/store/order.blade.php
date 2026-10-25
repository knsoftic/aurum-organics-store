@extends('layouts.store')

@section('title', 'Order '.$order->order_number)

@php
    $flow = ['pending' => 'Placed', 'confirmed' => 'Confirmed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
    $rank = ['pending' => 0, 'confirmed' => 1, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];
    $current = $rank[$order->status] ?? -1;
    $cancelled = in_array($order->status, \App\Models\Order::STOCK_RELEASED, true);
    $pendingPayment = $order->payments->firstWhere('status', 'pending');
    $isGateway = $order->payment_type === 'gateway';
    $canPayManually = ! $isGateway && $order->payable_now > 0 && ! $pendingPayment && $payMethods->isNotEmpty();
    $publicHistory = $order->histories->filter(fn ($h) => $h->status || $h->notify_customer);
@endphp

@section('content')
<section class="order-hero">
    <div class="container narrow">
        @if ($justPlaced)
            <div class="tick"><x-icon name="check" /></div>
            <span class="eyebrow center">Thank you, {{ \Illuminate\Support\Str::of($order->customer_name)->explode(' ')->first() }}</span>
            <h1 class="section-title" style="margin:16px 0 18px">Your order has been <em>placed</em></h1>
            <p class="lead" style="margin:0 auto 22px">
                @if ($order->payable_now > 0 && ! $pendingPayment)
                    Just one more step — complete your payment below to confirm the order.
                @else
                    We've received your order and will call you shortly to confirm it.
                @endif
            </p>
        @else
            <span class="eyebrow center">Order details</span>
            <h1 class="section-title" style="margin:16px 0 18px">Order <em>{{ $order->order_number }}</em></h1>
        @endif
        <span class="order-number">Order <strong>#{{ $order->order_number }}</strong> · {{ $order->created_at->format('d M Y, h:i A') }}</span>
    </div>
</section>

<section style="padding-bottom:100px">
    <div class="container">
        <div class="layout-2">
            <div>
                {{-- Progress --}}
                <div class="panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:26px;gap:10px;flex-wrap:wrap">
                        <h3 style="font-size:24px;margin:0">Order status</h3>
                        <span class="status-pill pill-{{ \App\Models\Order::statusColor($order->status) }}">{{ $order->status_label }}</span>
                    </div>
                    @if ($cancelled)
                        <div class="alert alert-error" style="margin:0">This order has been {{ strtolower($order->status_label) }}. Please contact us if you have any questions.</div>
                    @else
                        <div class="steps">
                            <span class="bar-fill" style="width: {{ max(0, $current) * 25 }}%"></span>
                            @foreach (array_values($flow) as $i => $label)
                                <div class="step {{ $current >= $i ? 'done' : '' }}">
                                    <div class="dot">@if ($current >= $i)<x-icon name="check" />@endif</div>
                                    {{ $label }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($order->tracking_number)
                        <div class="account-box" style="margin-top:24px">
                            <div class="account-row"><span>Courier</span><strong>{{ $order->courier ?: '—' }}</strong></div>
                            <div class="account-row"><span>Tracking number</span><strong>{{ $order->tracking_number }}</strong><button type="button" class="copy-btn" data-copy="{{ $order->tracking_number }}"><x-icon name="copy" /> Copy</button></div>
                        </div>
                    @endif
                </div>

                {{-- Payment --}}
                <div class="panel" id="payment">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:10px;flex-wrap:wrap">
                        <h3 style="font-size:24px;margin:0">Payment</h3>
                        <span class="status-pill pill-{{ \App\Models\Order::statusColor($order->payment_status) }}">{{ $order->payment_status_label }}</span>
                    </div>
                    <p style="margin-bottom:6px">Method: <strong>{{ $order->payment_method_name }}</strong></p>

                    @if ($pendingPayment)
                        <div class="alert alert-success" style="margin:16px 0 0">
                            We received your payment details ({{ money($pendingPayment->amount) }} via {{ $pendingPayment->method_name }}{{ $pendingPayment->reference ? ', Ref: '.$pendingPayment->reference : '' }}). Our team is verifying it — this usually takes a few hours.
                        </div>
                    @elseif ($isGateway && $order->payable_now > 0)
                        <p class="muted">Your online payment has not been completed yet.</p>
                        <a href="{{ route('order.pay', ['order' => $order->order_number, 'key' => $order->access_token]) }}" class="btn btn-gold">Pay {{ money($order->payable_now) }} Securely <x-icon name="arrow-right" /></a>
                    @elseif ($canPayManually)
                        <div class="advance-note" style="margin-top:14px">
                            @if ($order->payment_type === 'cod')
                                Please pay the advance of <strong>{{ money($order->payable_now) }}</strong> to confirm your order. The remaining {{ money($order->total - $order->advance_amount) }} is payable on delivery.
                            @else
                                Please send <strong>{{ money($order->payable_now) }}</strong> to one of the accounts below and submit the transaction details.
                            @endif
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif

                        <form method="POST" action="{{ route('order.payment', ['order' => $order->order_number, 'key' => $order->access_token]) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="advance-accounts" style="margin-bottom:22px">
                                @foreach ($payMethods as $i => $account)
                                    @php $isSelected = (int) old('payment_method_id', $payMethods->first()->id) === $account->id; @endphp
                                    <div class="advance-account {{ $isSelected ? 'selected' : '' }}" data-advance-account>
                                        <label>
                                            <input type="radio" name="payment_method_id" value="{{ $account->id }}" @checked($isSelected)>
                                            <img src="{{ $account->logo_url }}" alt=""> {{ $account->name }}
                                        </label>
                                        @include('store.partials.account-details', ['method' => $account])
                                    </div>
                                @endforeach
                            </div>
                            @include('store.partials.proof-fields')
                            <button type="submit" class="btn btn-primary" style="margin-top:20px" data-loading="Submitting…">Submit Payment Details</button>
                        </form>
                    @elseif ($order->payment_type === 'cod' && ! $cancelled && $order->due_amount > 0)
                        <p class="muted" style="margin:0">Please keep <strong style="color:var(--ink)">{{ money($order->due_amount) }}</strong> ready to pay in cash when your parcel arrives.</p>
                    @elseif ($order->payment_status === 'paid')
                        <p style="margin:0;color:var(--success)">✓ Paid in full — thank you!</p>
                    @endif
                </div>

                {{-- Details --}}
                <div class="panel">
                    <div class="info-grid">
                        <div>
                            <h4>Delivery to</h4>
                            <p><strong>{{ $order->customer_name }}</strong><br>{{ $order->address }}<br>{{ $order->city }} {{ $order->postal_code }}<br>{{ $order->phone }}@if ($order->email)<br>{{ $order->email }}@endif</p>
                        </div>
                        <div>
                            <h4>Updates</h4>
                            @if ($publicHistory->isEmpty())
                                <p class="muted">No updates yet.</p>
                            @else
                                <ul class="timeline">
                                    @foreach ($publicHistory as $history)
                                        <li>{{ $history->note ?: 'Status changed to '.(\App\Models\Order::STATUSES[$history->status] ?? $history->status).'.' }}<small>{{ $history->created_at->format('d M Y, h:i A') }}</small></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <aside class="panel summary-panel sticky">
                <h3 style="font-size:28px">Summary</h3>
                @foreach ($order->items as $item)
                    <div class="summary-item">
                        <div class="thumb"><img src="{{ $item->image_url }}" alt=""><span class="q">{{ $item->quantity }}</span></div>
                        <div><h4>{{ $item->product_name }}</h4><small>{{ $item->size_label }} · {{ money($item->price) }}</small></div>
                        <span>{{ money($item->total) }}</span>
                    </div>
                @endforeach
                <div class="totals">
                    <div class="row"><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
                    @if ($order->discount > 0)<div class="row discount"><span>Coupon ({{ $order->coupon_code }})</span><span>− {{ money($order->discount) }}</span></div>@endif
                    <div class="row"><span>Delivery</span><span>{{ $order->shipping > 0 ? money($order->shipping) : 'Free' }}</span></div>
                    @if ($order->payment_discount > 0)<div class="row discount"><span>Advance payment discount</span><span>− {{ money($order->payment_discount) }}</span></div>@endif
                    @if ($order->payment_fee > 0)<div class="row"><span>Payment fee</span><span>{{ money($order->payment_fee) }}</span></div>@endif
                    <div class="row grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
                    @if ($order->paid_amount > 0)
                        <div class="row discount"><span>Paid</span><span>{{ money($order->paid_amount) }}</span></div>
                        <div class="row"><span><strong>Balance due</strong></span><span><strong>{{ money($order->due_amount) }}</strong></span></div>
                    @endif
                </div>
                <div class="no-print" style="display:grid;gap:10px;margin-top:22px">
                    <button type="button" class="btn btn-outline btn-block btn-sm" onclick="window.print()">Print Receipt</button>
                    @if ($wa = whatsapp_link('Hi! I have a question about my order #'.$order->order_number.'.'))
                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="wa-order" style="margin:0"><x-icon name="whatsapp" style="width:18px" /> Questions? Chat with us</a>
                    @endif
                </div>
                <p class="muted no-print" style="font-size:12.5px;margin:16px 0 0;text-align:center">Bookmark this page to check your order status anytime.</p>
            </aside>
        </div>
    </div>
</section>
@endsection

@push('scripts')
@if (session('pixel_purchase') === $order->order_number)
    @php
        session()->forget('pixel_purchase');
        $pixelItems = $order->items->map(fn ($i) => ['id' => $i->sku ?: $i->product_id, 'name' => $i->product_name, 'price' => (float) $i->price, 'qty' => $i->quantity])->values();
    @endphp
    <script>window.knTrack && knTrack('purchase', { value: {{ (float) $order->total }}, orderId: @json($order->order_number), eventId: @json('order-'.$order->order_number), items: @json($pixelItems) });</script>
@endif
<script>
document.addEventListener('change', (e) => {
    if (e.target.name === 'payment_method_id') {
        document.querySelectorAll('[data-advance-account]').forEach((a) => a.classList.toggle('selected', a.contains(e.target)));
    }
});
</script>
@endpush
