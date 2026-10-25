@extends('emails.layout')

@section('body')
    <h1 style="font-family:Georgia,serif;font-weight:normal;font-size:26px;margin:0 0 10px">Your order is {{ strtolower($order->status_label) }}</h1>
    <p style="margin:0 0 16px;color:#6c6f66">Hi {{ $order->customer_name }}, here's an update on order <strong>#{{ $order->order_number }}</strong>.</p>
    @if ($note)<p style="margin:0 0 16px">{{ $note }}</p>@endif
    @if ($order->tracking_number)
        <p style="margin:0 0 16px"><strong>Tracking:</strong> {{ $order->courier }} {{ $order->tracking_number }}</p>
    @endif
    <a href="{{ $order->publicUrl() }}" style="display:inline-block;background:#1f3a2e;color:#f5efe3;text-decoration:none;padding:14px 28px;border-radius:4px;font-size:13px;letter-spacing:2px;text-transform:uppercase">Track your order</a>
@endsection
