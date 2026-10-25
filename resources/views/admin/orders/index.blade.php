@extends('layouts.admin')

@section('title', 'Orders')

@section('actions')
    <a href="{{ route('admin.orders.export', request()->query()) }}" class="btn btn-light btn-sm"><x-icon name="download" /> Export CSV</a>
@endsection

@section('content')
<div class="tabs">
    <a href="{{ route('admin.orders.index') }}" class="{{ ! request('status') && ! request('payment_status') ? 'active' : '' }}">All <span class="n">{{ $counts->sum() }}</span></a>
    @foreach (\App\Models\Order::STATUSES as $key => $label)
        <a href="{{ route('admin.orders.index', ['status' => $key]) }}" class="{{ request('status') === $key ? 'active' : '' }}">{{ $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
    <a href="{{ route('admin.orders.index', ['payment_status' => 'pending_verification']) }}" class="{{ request('payment_status') === 'pending_verification' ? 'active' : '' }}">Payment to verify <span class="n">{{ $pendingVerification }}</span></a>
</div>

<form class="toolbar" method="GET">
    @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="filters">
        <input class="input" name="q" value="{{ request('q') }}" placeholder="Search order #, name, phone, city…" style="min-width:260px">
        <select class="select" name="payment_status">
            <option value="">Any payment status</option>
            @foreach (\App\Models\Order::PAYMENT_STATUSES as $key => $label)
                <option value="{{ $key }}" @selected(request('payment_status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input class="input" type="date" name="from" value="{{ request('from') }}" title="From">
        <input class="input" type="date" name="to" value="{{ request('to') }}" title="To">
        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
        @if (request()->hasAny(['q', 'payment_status', 'from', 'to']))<a href="{{ route('admin.orders.index', request()->only('status')) }}" class="btn btn-light btn-sm">Reset</a>@endif
    </div>
</form>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Order</th><th>Customer</th><th>Items</th><th>Payment</th><th>Status</th><th class="num">Total</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="cell-main">#{{ $order->order_number }}</a><div class="cell-sub">{{ $order->created_at->format('d M Y, h:i A') }}</div></td>
                        <td><div>{{ $order->customer_name }}</div><div class="cell-sub">{{ $order->phone }} · {{ $order->city }}</div></td>
                        <td class="cell-sub">{{ $order->items->map(fn ($i) => $i->product_name.' ×'.$i->quantity)->implode(', ') }}</td>
                        <td>
                            <span class="badge b-{{ \App\Models\Order::statusColor($order->payment_status) }}">{{ $order->payment_status_label }}</span>
                            <div class="cell-sub">{{ $order->payment_method_name }}</div>
                        </td>
                        <td><span class="badge b-{{ \App\Models\Order::statusColor($order->status) }}">{{ $order->status_label }}</span></td>
                        <td class="num"><div class="cell-main">{{ money($order->total) }}</div>@if ($order->paid_amount > 0)<div class="cell-sub">Paid {{ money($order->paid_amount) }}</div>@endif</td>
                        <td class="actions"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-light btn-sm">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
</div>
@endsection
