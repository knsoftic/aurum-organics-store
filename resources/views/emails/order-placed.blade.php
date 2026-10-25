@extends('emails.layout')

@section('body')
    @if ($forAdmin)
        <h1 style="font-family:Georgia,serif;font-weight:normal;font-size:26px;margin:0 0 10px">New order #{{ $order->order_number }}</h1>
        <p style="margin:0 0 20px;color:#6c6f66">{{ $order->customer_name }} · {{ $order->phone }} · {{ $order->city }}</p>
    @else
        <h1 style="font-family:Georgia,serif;font-weight:normal;font-size:26px;margin:0 0 10px">Thank you for your order, {{ $order->customer_name }}!</h1>
        <p style="margin:0 0 20px;color:#6c6f66">Your order <strong>#{{ $order->order_number }}</strong> has been received. We'll call you to confirm it before dispatch.</p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e4dac7">
        @foreach ($order->items as $item)
            <tr>
                <td style="padding:12px 0;border-bottom:1px solid #e4dac7">{{ $item->product_name }} × {{ $item->quantity }}</td>
                <td style="padding:12px 0;border-bottom:1px solid #e4dac7;text-align:right">{{ money($item->total) }}</td>
            </tr>
        @endforeach
        <tr><td style="padding:8px 0;color:#6c6f66">Subtotal</td><td style="text-align:right">{{ money($order->subtotal) }}</td></tr>
        @if ($order->discount + $order->payment_discount > 0)
            <tr><td style="padding:8px 0;color:#6c6f66">Discount</td><td style="text-align:right">− {{ money($order->discount + $order->payment_discount) }}</td></tr>
        @endif
        <tr><td style="padding:8px 0;color:#6c6f66">Delivery</td><td style="text-align:right">{{ $order->shipping > 0 ? money($order->shipping) : 'Free' }}</td></tr>
        <tr><td style="padding:12px 0;font-size:18px"><strong>Total</strong></td><td style="text-align:right;font-size:18px"><strong>{{ money($order->total) }}</strong></td></tr>
    </table>

    <p style="margin:20px 0 6px"><strong>Payment:</strong> {{ $order->payment_method_name }} ({{ $order->payment_status_label }})</p>
    <p style="margin:0 0 24px"><strong>Deliver to:</strong> {{ $order->address }}, {{ $order->city }}</p>

    <a href="{{ $forAdmin ? route('admin.orders.show', $order) : $order->publicUrl() }}" style="display:inline-block;background:#1f3a2e;color:#f5efe3;text-decoration:none;padding:14px 28px;border-radius:4px;font-size:13px;letter-spacing:2px;text-transform:uppercase">
        {{ $forAdmin ? 'Open in admin' : 'View your order' }}
    </a>
@endsection
