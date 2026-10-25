@extends('layouts.admin')

@section('title', 'Payment Methods')

@section('actions')
    <a href="{{ route('admin.payment-methods.create', ['type' => 'manual']) }}" class="btn btn-gold btn-sm"><x-icon name="plus" /> Add manual account</a>
    <a href="{{ route('admin.payment-methods.create', ['type' => 'gateway']) }}" class="btn btn-light btn-sm"><x-icon name="card" /> Add online gateway</a>
@endsection

@section('content')
<div class="grid grid-3" style="margin-bottom:20px">
    <div class="card card-body">
        <div class="flex"><img src="{{ asset('images/payments/cod.svg') }}" width="34" alt=""><strong>Cash on Delivery</strong></div>
        <p class="muted" style="margin:10px 0 0;font-size:13px">Customer pays the rider. Optionally ask for an advance (e.g. delivery charges) through one of your manual accounts.</p>
    </div>
    <div class="card card-body">
        <div class="flex"><img src="{{ asset('images/payments/easypaisa.svg') }}" width="34" alt=""><strong>Manual accounts</strong></div>
        <p class="muted" style="margin:10px 0 0;font-size:13px">EasyPaisa, JazzCash, bank transfer, USDT TRC20… Customer sends money, uploads a screenshot + TID, you approve it from the order.</p>
    </div>
    <div class="card card-body">
        <div class="flex"><img src="{{ asset('images/payments/card.svg') }}" width="34" alt=""><strong>Online gateways</strong></div>
        <p class="muted" style="margin:10px 0 0;font-size:13px">Automatic payments via a gateway driver. Installed drivers: <strong>{{ implode(', ', $drivers) ?: 'none' }}</strong>. New drivers plug into <code>config/payments.php</code>.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Method</th><th>Type</th><th>Customer perk / fee</th><th class="num">Orders</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($methods as $method)
                    <tr>
                        <td>
                            <div class="flex">
                                <img src="{{ $method->logo_url }}" class="thumb" style="width:38px;height:38px" alt="">
                                <div>
                                    <div class="cell-main">{{ $method->name }}</div>
                                    <div class="cell-sub">
                                        @if ($method->isManual() && $method->details)
                                            {{ collect($method->details)->pluck('value')->take(2)->implode(' · ') }}
                                        @elseif ($method->isGateway())
                                            Driver: {{ $drivers[$method->driver] ?? $method->driver.' (missing)' }}
                                        @else
                                            {{ $method->description }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge plain b-gray">{{ \App\Models\PaymentMethod::types()[$method->type] ?? $method->type }}</span></td>
                        <td class="cell-sub">
                            @if ((float) $method->discount_percent > 0) {{ rtrim(rtrim($method->discount_percent, '0'), '.') }}% discount @endif
                            @if ($method->feeFor() > 0) +{{ money($method->feeFor()) }} fee @endif
                            @if ($method->requiresAdvance()) Advance: {{ $method->advance_type === 'percent' ? rtrim(rtrim($method->advance_value, '0'), '.').'%' : money($method->advance_value) }} @endif
                            @if ((float) $method->discount_percent <= 0 && $method->feeFor() <= 0 && ! $method->requiresAdvance()) — @endif
                        </td>
                        <td class="num">{{ $method->orders_count }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.payment-methods.toggle', $method) }}">
                                @csrf @method('PATCH')
                                <label class="switch" onclick="this.closest('form').submit()"><input type="checkbox" @checked($method->is_active)><span class="track"></span></label>
                            </form>
                        </td>
                        <td class="actions">
                            <a href="{{ route('admin.payment-methods.edit', $method) }}" class="icon-action" title="Edit"><x-icon name="edit" /></a>
                            <form method="POST" action="{{ route('admin.payment-methods.destroy', $method) }}" class="inline-form" data-confirm="Delete {{ $method->name }}?">
                                @csrf @method('DELETE')
                                <button class="icon-action danger" title="Delete"><x-icon name="trash" /></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No payment methods yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
