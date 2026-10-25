@extends('layouts.store')

@section('title', 'Track Your Order')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow center">Order status</span>
        <h1>Track your order</h1>
        <p class="lead" style="margin:0 auto">Enter your order number and the mobile number used at checkout.</p>
    </div>
</section>

<section class="section" style="padding-top:30px">
    <div class="container" style="max-width:560px">
        <div class="panel">
            <form method="POST" action="{{ route('track.lookup') }}" class="form-grid" style="grid-template-columns:1fr">
                @csrf
                <div class="field">
                    <label for="order_number">Order number</label>
                    <input id="order_number" class="input @error('order_number') is-invalid @enderror" name="order_number" value="{{ old('order_number') }}" placeholder="e.g. {{ strtoupper(setting('order_prefix', 'AO')) }}-10001" required>
                    @error('order_number')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="phone">Mobile number</label>
                    <input id="phone" class="input @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone') }}" placeholder="03XX XXXXXXX" inputmode="tel" required>
                    @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-block">Track Order <x-icon name="arrow-right" /></button>
            </form>
        </div>
        <p class="muted text-center" style="font-size:14px;margin-top:20px">Your order number is in your confirmation message and email.</p>
    </div>
</section>
@endsection
