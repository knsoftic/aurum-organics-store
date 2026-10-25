@extends('layouts.admin')

@section('title', 'Order #'.$order->order_number)
@section('crumb')<a href="{{ route('admin.orders.index') }}">Orders</a> / {{ $order->created_at->format('d M Y, h:i A') }}@endsection

@section('actions')
    <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="btn btn-light btn-sm"><x-icon name="print" /> Invoice</a>
    <a href="{{ $order->publicUrl() }}" target="_blank" class="btn btn-light btn-sm"><x-icon name="eye" /> Customer view</a>
@endsection

@php
    $waText = 'Assalam o Alaikum '.$order->customer_name.'! Your order #'.$order->order_number.' ('.money($order->total).') from '.setting('store_name').' — ';
    $phoneDigits = preg_replace('/\D+/', '', $order->phone);
    $waPhone = str_starts_with($phoneDigits, '0') ? '92'.substr($phoneDigits, 1) : $phoneDigits;
@endphp

@section('content')
<div class="grid grid-main">
    <div>
        {{-- Items --}}
        <div class="card">
            <div class="card-head">
                <h3>Items</h3>
                <div class="flex">
                    <span class="badge b-{{ \App\Models\Order::statusColor($order->status) }}">{{ $order->status_label }}</span>
                    <span class="badge b-{{ \App\Models\Order::statusColor($order->payment_status) }}">{{ $order->payment_status_label }}</span>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td style="width:60px"><img src="{{ $item->image_url }}" class="thumb" alt=""></td>
                                <td><div class="cell-main">{{ $item->product_name }}</div><div class="cell-sub">{{ $item->sku }} {{ $item->size_label ? '· '.$item->size_label : '' }}</div></td>
                                <td class="num">{{ money($item->price) }} × {{ $item->quantity }}</td>
                                <td class="num cell-main">{{ money($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body totals-list" style="border-top:1px solid var(--line);max-width:380px;margin-left:auto">
                <div class="row"><span class="muted">Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
                @if ($order->discount > 0)<div class="row"><span class="muted">Coupon ({{ $order->coupon_code }})</span><span>− {{ money($order->discount) }}</span></div>@endif
                <div class="row"><span class="muted">Delivery</span><span>{{ money($order->shipping) }}</span></div>
                @if ($order->payment_discount > 0)<div class="row"><span class="muted">Payment discount</span><span>− {{ money($order->payment_discount) }}</span></div>@endif
                @if ($order->payment_fee > 0)<div class="row"><span class="muted">Payment fee</span><span>{{ money($order->payment_fee) }}</span></div>@endif
                <div class="row grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
                <div class="row"><span class="muted">Paid</span><span style="color:var(--success)">{{ money($order->paid_amount) }}</span></div>
                <div class="row"><span class="muted">{{ $order->payment_type === 'cod' ? 'To collect (COD)' : 'Balance due' }}</span><strong>{{ money($order->due_amount) }}</strong></div>
                @if ($order->advance_amount > 0)<div class="row"><span class="muted">Advance required</span><span>{{ money($order->advance_amount) }}</span></div>@endif
            </div>
        </div>

        {{-- Payments --}}
        <div class="card" id="payments">
            <div class="card-head">
                <h3>Payments</h3>
                <span class="muted" style="font-size:12.5px">{{ $order->payment_method_name }}</span>
            </div>
            <div class="card-body">
                @forelse ($order->payments as $payment)
                    <div class="payment-card {{ $payment->status === 'pending' ? 'pending' : '' }}">
                        <div class="flex between wrap">
                            <div>
                                <strong style="font-size:16px">{{ money($payment->amount) }}</strong>
                                <span class="muted"> · {{ $payment->method_name }} · {{ $payment->purpose_label }}</span>
                            </div>
                            <span class="badge b-{{ \App\Models\Order::statusColor($payment->status) }}">{{ ucfirst($payment->status) }}</span>
                        </div>
                        <dl class="dl" style="margin-top:12px">
                            @if ($payment->reference)<dt>Reference / TID</dt><dd>{{ $payment->reference }}</dd>@endif
                            @if ($payment->sender_account)<dt>Sent from</dt><dd>{{ $payment->sender_account }}</dd>@endif
                            <dt>Submitted</dt><dd>{{ $payment->created_at->format('d M Y, h:i A') }}</dd>
                            @if ($payment->verified_at)<dt>{{ $payment->status === 'rejected' ? 'Rejected' : 'Verified' }}</dt><dd>{{ $payment->verified_at->format('d M Y, h:i A') }}{{ $payment->verifier ? ' by '.$payment->verifier->name : ' (automatic)' }}</dd>@endif
                            @if ($payment->note)<dt>Note</dt><dd>{{ $payment->note }}</dd>@endif
                        </dl>
                        @if ($payment->proof_path)
                            @if (str_ends_with(strtolower($payment->proof_path), '.pdf'))
                                <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank" class="btn btn-light btn-sm" style="margin-top:10px"><x-icon name="file" /> Open PDF receipt</a>
                            @else
                                <a href="{{ route('admin.payments.proof', $payment) }}" target="_blank"><img src="{{ route('admin.payments.proof', $payment) }}" class="proof" alt="Payment screenshot"></a>
                            @endif
                        @endif
                        @if ($payment->status === 'pending')
                            <div class="flex wrap" style="margin-top:14px">
                                <form method="POST" action="{{ route('admin.payments.approve', $payment) }}" class="inline-form" data-confirm="Approve this payment of {{ money($payment->amount) }}?">
                                    @csrf
                                    <button class="btn btn-success btn-sm"><x-icon name="check" /> Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="flex" style="flex:1;min-width:240px" data-confirm="Reject this payment?">
                                    @csrf
                                    <input class="input" name="note" placeholder="Reason (shown to customer)" style="padding:6px 10px">
                                    <button class="btn btn-danger btn-sm">Reject</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="muted" style="margin:0 0 6px">No payments recorded yet.</p>
                @endforelse

                @if ($order->due_amount > 0)
                    <details style="margin-top:12px">
                        <summary class="btn btn-light btn-sm" style="list-style:none">+ Record a payment</summary>
                        <form method="POST" action="{{ route('admin.orders.payments.record', $order) }}" class="form-grid" style="margin-top:14px">
                            @csrf
                            <div class="field"><label>Amount</label><input class="input" type="number" step="0.01" name="amount" value="{{ $order->due_amount }}" max="{{ $order->due_amount }}" required></div>
                            <div class="field"><label>Method</label>
                                <select class="select" name="payment_method_id">
                                    @foreach ($methods as $m)<option value="{{ $m->id }}" @selected($m->id === $order->payment_method_id)>{{ $m->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field"><label>Type</label>
                                <select class="select" name="purpose">
                                    @foreach (\App\Models\Payment::PURPOSES as $key => $label)<option value="{{ $key }}" @selected($key === ($order->payment_type === 'cod' ? 'cod' : 'full'))>{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field"><label>Reference</label><input class="input" name="reference"></div>
                            <div class="field full"><label>Note</label><input class="input" name="note"></div>
                            <div class="full"><button class="btn btn-primary btn-sm">Save payment</button></div>
                        </form>
                    </details>
                @endif
                @if ($order->paid_amount > 0 && $order->payment_status !== 'refunded')
                    <form method="POST" action="{{ route('admin.orders.refunded', $order) }}" style="margin-top:12px" data-confirm="Mark this order's payment as refunded?">
                        @csrf
                        <button class="btn btn-light btn-sm">Mark as refunded</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- History --}}
        <div class="card">
            <div class="card-head"><h3>Timeline</h3></div>
            <div class="card-body">
                <ul class="timeline">
                    @foreach ($order->histories as $history)
                        <li>
                            @if ($history->status)<span class="badge b-{{ \App\Models\Order::statusColor($history->status) }}" style="margin-right:6px">{{ \App\Models\Order::STATUSES[$history->status] ?? $history->status }}</span>@endif
                            {{ $history->note }}
                            <small>{{ $history->created_at->format('d M Y, h:i A') }} · {{ $history->user?->name ?? 'System' }}{{ $history->notify_customer ? ' · visible to customer' : '' }}</small>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <div>
        {{-- Status --}}
        <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="card">
            @csrf @method('PATCH')
            <div class="card-head"><h3>Update status</h3></div>
            <div class="card-body" style="display:grid;gap:12px">
                <select class="select" name="status">
                    @foreach (\App\Models\Order::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($order->status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <textarea class="textarea" name="note" placeholder="Note (optional)" style="min-height:70px"></textarea>
                <label class="switch"><input type="checkbox" name="notify" value="1" checked><span class="track"></span> Show note to customer / email</label>
                @if ($order->payment_type === 'cod' && $order->due_amount > 0)
                    <div class="alert alert-info" style="margin:0;font-size:12.5px">Marking as <strong>Delivered</strong> records {{ money($order->due_amount) }} cash collected.</div>
                @endif
                <button class="btn btn-primary">Update</button>
            </div>
        </form>

        {{-- Customer --}}
        <div class="card">
            <div class="card-head"><h3>Customer</h3></div>
            <div class="card-body">
                <div class="cell-main" style="font-size:16px">{{ $order->customer_name }}</div>
                <p class="muted" style="margin:6px 0 14px">{{ $order->address }}<br>{{ $order->city }} {{ $order->postal_code }}</p>
                <div class="flex wrap">
                    <a href="tel:{{ $order->phone }}" class="btn btn-light btn-sm"><x-icon name="phone" /> {{ $order->phone }}</a>
                    <a href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" class="btn btn-light btn-sm" style="color:#1f8f4e"><x-icon name="whatsapp" /> WhatsApp</a>
                </div>
                @if ($order->email)<p style="margin:12px 0 0"><a href="mailto:{{ $order->email }}">{{ $order->email }}</a></p>@endif
                @if ($order->notes)<div class="alert alert-info" style="margin:14px 0 0"><strong>Customer note:</strong> {{ $order->notes }}</div>@endif
            </div>
        </div>

        {{-- Shipping + edit --}}
        <form method="POST" action="{{ route('admin.orders.details', $order) }}" class="card">
            @csrf @method('PATCH')
            <div class="card-head"><h3>Shipping &amp; details</h3></div>
            <div class="card-body form-grid" style="grid-template-columns:1fr">
                <div class="field"><label>Courier</label><input class="input" name="courier" value="{{ old('courier', $order->courier) }}" placeholder="TCS, Leopards, M&P, PostEx…" list="couriers">
                    <datalist id="couriers">@foreach (['TCS', 'Leopards Courier', 'M&P', 'PostEx', 'Trax', 'Call Courier', 'BlueEx', 'Pakistan Post'] as $c)<option value="{{ $c }}">@endforeach</datalist>
                </div>
                <div class="field"><label>Tracking number</label><input class="input" name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}"></div>
                <details>
                    <summary class="muted" style="cursor:pointer;font-size:13px">Edit customer details</summary>
                    <div style="display:grid;gap:12px;margin-top:12px">
                        <input class="input" name="customer_name" value="{{ old('customer_name', $order->customer_name) }}" placeholder="Name" required>
                        <input class="input" name="phone" value="{{ old('phone', $order->phone) }}" placeholder="Phone" required>
                        <input class="input" name="email" value="{{ old('email', $order->email) }}" placeholder="Email">
                        <input class="input" name="city" value="{{ old('city', $order->city) }}" placeholder="City" required>
                        <textarea class="textarea" name="address" placeholder="Address" required style="min-height:70px">{{ old('address', $order->address) }}</textarea>
                    </div>
                </details>
                <div class="field"><label>Internal note</label><textarea class="textarea" name="admin_note" style="min-height:70px" placeholder="Only visible to admins">{{ old('admin_note', $order->admin_note) }}</textarea></div>
                <button class="btn btn-primary">Save</button>
            </div>
        </form>

        <div class="card">
            <div class="card-body">
                <dl class="dl">
                    <dt>IP address</dt><dd>{{ $order->ip_address ?: '—' }}</dd>
                    <dt>Placed</dt><dd>{{ $order->created_at->format('d M Y, h:i A') }}</dd>
                </dl>
                @if (in_array($order->status, \App\Models\Order::STOCK_RELEASED, true))
                    <form method="POST" action="{{ route('admin.orders.destroy', $order) }}" style="margin-top:14px" data-confirm="Permanently delete this order? This cannot be undone.">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm btn-block"><x-icon name="trash" /> Delete order</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
