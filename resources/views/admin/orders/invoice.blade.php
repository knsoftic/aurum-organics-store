<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2a23; margin: 0; padding: 40px; font-size: 13px; }
        .inv { max-width: 760px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f3a2e; padding-bottom: 20px; margin-bottom: 26px; }
        .brand { font-family: Georgia, serif; font-size: 28px; letter-spacing: 6px; }
        .muted { color: #6c6f66; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6c6f66; border-bottom: 1px solid #ddd; padding: 8px 0; }
        td { padding: 10px 0; border-bottom: 1px solid #eee; }
        .r { text-align: right; }
        .totals { width: 300px; margin-left: auto; }
        .totals div { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand { font-size: 17px; font-weight: bold; border-top: 1px solid #1f3a2e; margin-top: 6px; padding-top: 10px !important; }
        .cod { margin-top: 26px; padding: 14px 18px; border: 2px dashed #1f3a2e; font-size: 16px; display: flex; justify-content: space-between; }
        .no-print { text-align: center; margin-bottom: 20px; }
        @media print { .no-print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="no-print"><button onclick="window.print()" style="padding:10px 22px;cursor:pointer">Print invoice</button></div>
<div class="inv">
    <div class="head">
        <div>
            <div class="brand">{{ strtoupper(setting('store_name')) }}</div>
            <div class="muted" style="margin-top:6px">{{ setting('store_address') }}<br>{{ setting('store_phone') }} · {{ setting('store_email') }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:20px;font-weight:bold">INVOICE</div>
            <div>#{{ $order->order_number }}</div>
            <div class="muted">{{ $order->created_at->format('d M Y') }}</div>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;gap:20px">
        <div>
            <div class="muted" style="text-transform:uppercase;font-size:11px;letter-spacing:1px">Ship to</div>
            <strong>{{ $order->customer_name }}</strong><br>{{ $order->address }}<br>{{ $order->city }} {{ $order->postal_code }}<br>{{ $order->phone }}
        </div>
        <div style="text-align:right">
            <div class="muted" style="text-transform:uppercase;font-size:11px;letter-spacing:1px">Payment</div>
            {{ $order->payment_method_name }}<br>{{ $order->payment_status_label }}
            @if ($order->tracking_number)<br>{{ $order->courier }} {{ $order->tracking_number }}@endif
        </div>
    </div>

    <table>
        <thead><tr><th>Item</th><th class="r">Price</th><th class="r">Qty</th><th class="r">Total</th></tr></thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr><td>{{ $item->product_name }} <span class="muted">{{ $item->size_label }}</span></td><td class="r">{{ money($item->price) }}</td><td class="r">{{ $item->quantity }}</td><td class="r">{{ money($item->total) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>{{ money($order->subtotal) }}</span></div>
        @if ($order->discount > 0)<div><span>Discount</span><span>− {{ money($order->discount) }}</span></div>@endif
        <div><span>Delivery</span><span>{{ money($order->shipping) }}</span></div>
        @if ($order->payment_discount > 0)<div><span>Payment discount</span><span>− {{ money($order->payment_discount) }}</span></div>@endif
        @if ($order->payment_fee > 0)<div><span>Payment fee</span><span>{{ money($order->payment_fee) }}</span></div>@endif
        <div class="grand"><span>Total</span><span>{{ money($order->total) }}</span></div>
        <div><span>Paid</span><span>{{ money($order->paid_amount) }}</span></div>
    </div>

    <div class="cod"><span>Amount to collect</span><strong>{{ money($order->due_amount) }}</strong></div>
    <p class="muted" style="text-align:center;margin-top:30px">Thank you for shopping with {{ setting('store_name') }}.</p>
</div>
</body>
</html>
