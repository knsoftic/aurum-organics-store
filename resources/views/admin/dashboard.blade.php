@extends('layouts.admin')

@section('title', 'Dashboard')
@section('crumb', now()->format('l, d F Y'))

@section('actions')
    <a href="{{ route('admin.products.create') }}" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add product</a>
@endsection

@section('content')
<div class="grid grid-4">
    <div class="card stat dark">
        <div class="ico"><x-icon name="trend" /></div>
        <div class="label">Sales this month</div>
        <div class="value">{{ money($stats['revenue_month']) }}</div>
        <div class="sub">Today: {{ money($stats['revenue_today']) }}</div>
    </div>
    <div class="card stat">
        <div class="ico"><x-icon name="receipt" /></div>
        <div class="label">Orders today</div>
        <div class="value">{{ $stats['orders_today'] }}</div>
        <div class="sub">{{ $stats['orders_total'] }} orders all time</div>
    </div>
    <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="card stat">
        <div class="ico"><x-icon name="clock" /></div>
        <div class="label">Pending orders</div>
        <div class="value">{{ $stats['orders_pending'] }}</div>
        <div class="sub">Waiting for confirmation</div>
    </a>
    <a href="{{ route('admin.orders.index', ['payment_status' => 'pending_verification']) }}" class="card stat">
        <div class="ico"><x-icon name="wallet" /></div>
        <div class="label">Payments to verify</div>
        <div class="value">{{ $stats['payments_pending'] }}</div>
        <div class="sub">Collected this month: {{ money($stats['collected']) }}</div>
    </a>
</div>

<div class="grid grid-main" style="margin-top:20px">
    <div>
        <div class="card">
            <div class="card-head"><h3>Sales — last 14 days</h3><span class="muted" style="font-size:12.5px">Excludes cancelled &amp; returned</span></div>
            <div class="card-body"><canvas id="salesChart" height="110"></canvas></div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Recent orders</h3><a href="{{ route('admin.orders.index') }}" class="btn btn-light btn-sm">View all</a></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th>Status</th><th class="num">Total</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr onclick="location='{{ route('admin.orders.show', $order) }}'" style="cursor:pointer">
                                <td><div class="cell-main">#{{ $order->order_number }}</div><div class="cell-sub">{{ $order->created_at->diffForHumans() }}</div></td>
                                <td><div>{{ $order->customer_name }}</div><div class="cell-sub">{{ $order->city }}</div></td>
                                <td><span class="badge b-{{ \App\Models\Order::statusColor($order->payment_status) }}">{{ $order->payment_status_label }}</span><div class="cell-sub">{{ $order->payment_method_name }}</div></td>
                                <td><span class="badge b-{{ \App\Models\Order::statusColor($order->status) }}">{{ $order->status_label }}</span></td>
                                <td class="num cell-main">{{ money($order->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">No orders yet. Share your store link to get your first sale!</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        @if ($pendingPayments->isNotEmpty())
            <div class="card">
                <div class="card-head"><h3>Payments awaiting verification</h3></div>
                <div class="card-body">
                    @foreach ($pendingPayments as $payment)
                        <a href="{{ route('admin.orders.show', $payment->order_id) }}#payments" class="flex between" style="padding:8px 0;border-bottom:1px solid var(--line)">
                            <span><strong>#{{ $payment->order?->order_number }}</strong><span class="cell-sub" style="display:block">{{ $payment->method_name }} · {{ $payment->reference ?: 'no ref' }}</span></span>
                            <strong>{{ money($payment->amount) }}</strong>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head"><h3>Orders by status</h3></div>
            <div class="card-body bar-list">
                @php $maxStatus = max(1, $statusBreakdown->max() ?? 1); @endphp
                @foreach (\App\Models\Order::STATUSES as $key => $label)
                    <div class="item">
                        <span>{{ $label }}</span><strong>{{ $statusBreakdown[$key] ?? 0 }}</strong>
                        <div class="bar"><span style="width: {{ round(($statusBreakdown[$key] ?? 0) / $maxStatus * 100) }}%"></span></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Best sellers</h3></div>
            <div class="card-body">
                @foreach ($topProducts as $product)
                    <div class="flex" style="padding:6px 0">
                        <img src="{{ $product->image_url }}" class="thumb" alt="">
                        <div style="flex:1"><div class="cell-main">{{ $product->name }}</div><div class="cell-sub">{{ (int) $product->sold }} sold · {{ $product->track_stock ? $product->stock.' in stock' : 'Unlimited' }}</div></div>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($lowStock->isNotEmpty() || $stats['reviews_pending'] || $stats['messages_unread'])
            <div class="card">
                <div class="card-head"><h3>Needs attention</h3></div>
                <div class="card-body" style="display:grid;gap:8px">
                    @foreach ($lowStock as $product)
                        <a href="{{ route('admin.products.edit', $product) }}" class="alert alert-info" style="margin:0"><strong>{{ $product->name }}</strong> — only {{ $product->stock }} left</a>
                    @endforeach
                    @if ($stats['reviews_pending'])
                        <a href="{{ route('admin.reviews.index', ['status' => 'pending']) }}" class="alert alert-info" style="margin:0">{{ $stats['reviews_pending'] }} review(s) waiting for approval</a>
                    @endif
                    @if ($stats['messages_unread'])
                        <a href="{{ route('admin.messages.index') }}" class="alert alert-info" style="margin:0">{{ $stats['messages_unread'] }} unread message(s)</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const data = @json($chart);
    const el = document.getElementById('salesChart');
    if (!window.Chart || !el) return;
    const ctx = el.getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(181,143,80,.35)');
    gradient.addColorStop(1, 'rgba(181,143,80,0)');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map((d) => d.label),
            datasets: [{
                label: 'Revenue', data: data.map((d) => d.revenue), borderColor: '#b58f50', backgroundColor: gradient,
                fill: true, tension: .35, borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#1f3a2e',
            }],
        },
        options: {
            plugins: { legend: { display: false }, tooltip: { callbacks: {
                label: (c) => ' {{ setting('currency_symbol', 'Rs.') }} ' + c.parsed.y.toLocaleString() + ' · ' + data[c.dataIndex].orders + ' orders',
            } } },
            scales: { y: { beginAtZero: true, grid: { color: '#f0ebe0' }, ticks: { callback: (v) => v.toLocaleString() } }, x: { grid: { display: false } } },
        },
    });
})();
</script>
@endpush
