@extends('layouts.admin')

@section('title', $coupon->exists ? 'Edit coupon' : 'New coupon')
@section('crumb')<a href="{{ route('admin.coupons.index') }}">Coupons</a>@endsection

@section('content')
<form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="card" style="max-width:760px">
    @csrf
    @if ($coupon->exists) @method('PUT') @endif
    <div class="card-body form-grid">
        <div class="field"><label>Code</label><input class="input" name="code" value="{{ old('code', $coupon->code) }}" required style="text-transform:uppercase" placeholder="e.g. EID20"></div>
        <div class="field"><label>Type</label>
            <select class="select" name="type">
                <option value="percent" @selected(old('type', $coupon->type) === 'percent')>Percentage (%)</option>
                <option value="fixed" @selected(old('type', $coupon->type) === 'fixed')>Fixed amount ({{ setting('currency_symbol', 'Rs.') }})</option>
            </select>
        </div>
        <div class="field"><label>Value</label><input class="input" type="number" step="0.01" min="0" name="value" value="{{ old('value', $coupon->value) }}" required></div>
        <div class="field"><label>Minimum order <span class="muted">(optional)</span></label><input class="input" type="number" step="0.01" min="0" name="min_order" value="{{ old('min_order', $coupon->min_order) }}"></div>
        <div class="field"><label>Usage limit <span class="muted">(optional)</span></label><input class="input" type="number" min="1" name="max_uses" value="{{ old('max_uses', $coupon->max_uses) }}"></div>
        <div class="field" style="align-self:end"><label class="switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active))><span class="track"></span> Active</label></div>
        <div class="field"><label>Starts <span class="muted">(optional)</span></label><input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d\TH:i')) }}"></div>
        <div class="field"><label>Expires <span class="muted">(optional)</span></label><input class="input" type="datetime-local" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d\TH:i')) }}"></div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-light">Cancel</a>
        <button class="btn btn-primary">{{ $coupon->exists ? 'Save changes' : 'Create coupon' }}</button>
    </div>
</form>
@endsection
