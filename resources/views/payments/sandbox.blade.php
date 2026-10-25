<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure Payment — {{ $merchant }}</title>
    <style>
        :root { --ink: #1b2430; --muted: #6b7280; --line: #e5e7eb; --brand: #3b3f6b; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: linear-gradient(135deg, #eef0f7, #f7f7fb); font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color: var(--ink); padding: 20px; }
        .card { width: 100%; max-width: 420px; background: #fff; border-radius: 16px; box-shadow: 0 30px 60px -30px rgba(30, 35, 80, .35); overflow: hidden; }
        .head { background: var(--brand); color: #fff; padding: 24px 28px; }
        .head small { opacity: .7; letter-spacing: .1em; text-transform: uppercase; font-size: 11px; }
        .head h1 { margin: 6px 0 0; font-size: 20px; font-weight: 600; }
        .amount { display: flex; justify-content: space-between; align-items: baseline; padding: 22px 28px; border-bottom: 1px solid var(--line); }
        .amount strong { font-size: 28px; }
        .body { padding: 24px 28px 28px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin: 0 0 6px; text-transform: uppercase; letter-spacing: .06em; }
        .input { width: 100%; padding: 12px 14px; border: 1px solid var(--line); border-radius: 8px; font-size: 15px; margin-bottom: 14px; background: #fafafa; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .btn { width: 100%; border: 0; border-radius: 10px; padding: 14px; font-size: 15px; font-weight: 600; cursor: pointer; }
        .pay { background: var(--brand); color: #fff; margin-top: 6px; }
        .cancel { background: transparent; color: var(--muted); margin-top: 8px; }
        .note { background: #fff8e6; color: #8a6100; border-radius: 8px; padding: 10px 12px; font-size: 12.5px; margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="card">
    <div class="head">
        <small>Secure checkout · Sandbox</small>
        <h1>{{ $merchant }}</h1>
    </div>
    <div class="amount">
        <span>Order #{{ $order->order_number }}</span>
        <strong>{{ money($payment->amount) }}</strong>
    </div>
    <div class="body">
        <div class="note">Test gateway — no real card is charged. Replace this driver with a live gateway (JazzCash, EasyPaisa, Stripe…) before going live.</div>
        <label>Card number</label>
        <input class="input" value="4242 4242 4242 4242" readonly>
        <div class="row">
            <div><label>Expiry</label><input class="input" value="12 / 30" readonly></div>
            <div><label>CVC</label><input class="input" value="123" readonly></div>
        </div>
        <form method="POST" action="{{ $callback }}">
            @csrf
            <input type="hidden" name="payment_id" value="{{ $payment->id }}">
            <input type="hidden" name="result" value="success">
            <input type="hidden" name="signature" value="{{ $signatures['success'] }}">
            <button class="btn pay" type="submit">Pay {{ money($payment->amount) }}</button>
        </form>
        <form method="POST" action="{{ $callback }}">
            @csrf
            <input type="hidden" name="payment_id" value="{{ $payment->id }}">
            <input type="hidden" name="result" value="cancel">
            <input type="hidden" name="signature" value="{{ $signatures['cancel'] }}">
            <button class="btn cancel" type="submit">Cancel and return to store</button>
        </form>
    </div>
</div>
</body>
</html>
