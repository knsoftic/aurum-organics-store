@extends('layouts.admin')

@section('title', $method->exists ? 'Edit '.$method->name : 'New payment method')
@section('crumb')<a href="{{ route('admin.payment-methods.index') }}">Payment Methods</a>@endsection

@php
    $details = old('details', $method->details ?: []);
    $currency = setting('currency_symbol', 'Rs.');
@endphp

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $method->exists ? route('admin.payment-methods.update', $method) : route('admin.payment-methods.store') }}">
    @csrf
    @if ($method->exists) @method('PUT') @endif

    <div class="grid grid-main">
        <div>
            <div class="card">
                <div class="card-head"><h3>General</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Type</label>
                        <select class="select" name="type" id="type">
                            @foreach (\App\Models\PaymentMethod::types() as $key => $label)
                                <option value="{{ $key }}" @selected(old('type', $method->type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field"><label>Display name</label><input class="input" name="name" value="{{ old('name', $method->name) }}" required placeholder="e.g. EasyPaisa, Meezan Bank, USDT (TRC20)"></div>
                    <div class="field"><label>Code</label><input class="input" name="code" value="{{ old('code', $method->code) }}" placeholder="auto, e.g. easypaisa"><div class="hint">Internal identifier (letters, numbers, _ -).</div></div>
                    <div class="field"><label>Sort order</label><input class="input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $method->sort_order ?? 0) }}"></div>
                    <div class="field full"><label>Short description <span class="muted">(shown under the name at checkout)</span></label><input class="input" name="description" value="{{ old('description', $method->description) }}" maxlength="255"></div>
                    <div class="field full"><label>Instructions for the customer</label><textarea class="textarea" name="instructions" style="min-height:80px">{{ old('instructions', $method->instructions) }}</textarea></div>
                </div>
            </div>

            {{-- Manual: account details --}}
            <div class="card" data-show-when="#type=manual">
                <div class="card-head"><h3>Account details</h3><span class="muted" style="font-size:12.5px">Shown to the customer with copy buttons</span></div>
                <div class="card-body">
                    <div id="detailRows" data-repeater data-next="{{ count($details) + 1 }}">
                        <div data-rows>
                            @foreach ($details as $i => $row)
                                <div class="repeater-row" data-row>
                                    <input class="input" name="details[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Label, e.g. Account Title">
                                    <input class="input" name="details[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}" placeholder="Value, e.g. 0345 1234567">
                                    <button type="button" class="icon-action danger" data-remove-row title="Remove"><x-icon name="trash" /></button>
                                </div>
                            @endforeach
                        </div>
                        <template>
                            <div class="repeater-row" data-row>
                                <input class="input" name="details[__INDEX__][label]" placeholder="Label, e.g. IBAN / Wallet Address / Network">
                                <input class="input" name="details[__INDEX__][value]" placeholder="Value">
                                <button type="button" class="icon-action danger" data-remove-row title="Remove"><x-icon name="trash" /></button>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn btn-light btn-sm" data-add-row="#detailRows"><x-icon name="plus" /> Add row</button>
                    <p class="hint muted" style="margin:12px 0 0;font-size:12px">Examples — EasyPaisa: Account Title, Mobile Number · Bank: Bank, Account Title, Account Number, IBAN · USDT: Network (TRC20), Wallet Address.</p>

                    <div class="field" style="margin-top:20px">
                        <label>QR code <span class="muted">(optional)</span></label>
                        <div class="upload-box">
                            <img id="qrPreview" src="{{ $method->qr_url ?? asset('images/placeholder.svg') }}" alt="">
                            <div>
                                <input type="file" name="qr_image" accept="image/*" data-preview="#qrPreview">
                                @if ($method->qr_image)<label class="switch" style="margin-top:8px;font-weight:400"><input type="checkbox" name="remove_qr" value="1"><span class="track"></span> Remove QR</label>@endif
                            </div>
                        </div>
                    </div>
                    <div class="flex wrap" style="margin-top:18px;gap:24px">
                        <label class="switch"><input type="checkbox" name="require_reference" value="1" @checked(old('require_reference', $method->require_reference))><span class="track"></span> Require transaction ID</label>
                        <label class="switch"><input type="checkbox" name="require_proof" value="1" @checked(old('require_proof', $method->require_proof))><span class="track"></span> Require payment screenshot</label>
                    </div>
                </div>
            </div>

            {{-- COD: advance --}}
            <div class="card" data-show-when="#type=cod">
                <div class="card-head"><h3>Advance payment for COD</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Ask for advance</label>
                        <select class="select" name="advance_type" id="advance_type">
                            <option value="none" @selected(old('advance_type', $method->advance_type) === 'none')>No advance — pay everything on delivery</option>
                            <option value="fixed" @selected(old('advance_type', $method->advance_type) === 'fixed')>Fixed amount (e.g. delivery charges)</option>
                            <option value="percent" @selected(old('advance_type', $method->advance_type) === 'percent')>Percentage of order total</option>
                        </select>
                    </div>
                    <div class="field" data-show-when="#advance_type=fixed,percent"><label>Advance value</label><input class="input" type="number" step="0.01" min="0" name="advance_value" value="{{ old('advance_value', $method->advance_value) }}"><div class="hint">{{ $currency }} amount or % depending on the option.</div></div>
                    <div class="full alert alert-info" style="margin:0">With an advance, the customer pays it to one of your <strong>manual accounts</strong> at checkout (TID + screenshot). The rest is collected on delivery.</div>
                </div>
            </div>

            {{-- Gateway --}}
            <div class="card" data-show-when="#type=gateway">
                <div class="card-head"><h3>Gateway driver &amp; credentials</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Driver</label>
                        <select class="select" name="driver" id="driver">
                            <option value="">— Select —</option>
                            @foreach ($drivers as $key => $label)
                                <option value="{{ $key }}" @selected(old('driver', $method->driver) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="hint">To add JazzCash / EasyPaisa merchant API, Safepay, PayFast or Stripe, a developer adds a driver class — see <code>docs/PAYMENT_GATEWAYS.md</code>.</div>
                    </div>
                    @foreach ($driverFields as $driverKey => $fields)
                        @foreach ($fields as $key => $field)
                            <div class="field full" data-show-when="#driver={{ $driverKey }}">
                                <label>{{ $field['label'] }}</label>
                                @php $fieldType = $field['type'] ?? 'text'; $value = old('config.'.$key, $method->driver === $driverKey ? $method->configValue($key) : null); @endphp
                                @if ($fieldType === 'textarea')
                                    <textarea class="textarea" name="config[{{ $key }}]">{{ $value }}</textarea>
                                @elseif ($fieldType === 'select')
                                    <select class="select" name="config[{{ $key }}]">@foreach ($field['options'] ?? [] as $ok => $ol)<option value="{{ $ok }}" @selected($value == $ok)>{{ $ol }}</option>@endforeach</select>
                                @else
                                    <input class="input" type="{{ $fieldType === 'password' ? 'password' : 'text' }}" name="config[{{ $key }}]" value="{{ $fieldType === 'password' ? '' : $value }}" placeholder="{{ $fieldType === 'password' && $value ? '•••••••• (saved — leave blank to keep)' : '' }}" autocomplete="off">
                                @endif
                                @if (! empty($field['help']))<div class="hint">{{ $field['help'] }}</div>@endif
                            </div>
                        @endforeach
                    @endforeach
                    @if ($method->exists && $method->isGateway())
                        <div class="full alert alert-info" style="margin:0">
                            Callback URL: <code>{{ route('payment.callback', $method->code) }}</code><br>
                            Webhook URL: <code>{{ route('payment.webhook', $method->code) }}</code>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-head"><h3>Status</h3></div>
                <div class="card-body"><label class="switch"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $method->is_active))><span class="track"></span> Available at checkout</label></div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Logo</h3></div>
                <div class="card-body">
                    <div class="upload-box">
                        <img id="logoPreview" src="{{ $method->exists ? $method->logo_url : asset('images/payments/wallet.svg') }}" alt="">
                        <div>
                            <input type="file" name="logo" accept="image/*" data-preview="#logoPreview">
                            @if ($method->logo)<label class="switch" style="margin-top:8px;font-weight:400"><input type="checkbox" name="remove_logo" value="1"><span class="track"></span> Use default icon</label>@endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Pricing rules</h3></div>
                <div class="card-body" style="display:grid;gap:14px">
                    <div class="field"><label>Discount for this method (%)</label><input class="input" type="number" step="0.01" min="0" max="100" name="discount_percent" value="{{ old('discount_percent', $method->discount_percent ?? 0) }}"><div class="hint">e.g. 5% off for paying in advance.</div></div>
                    <div class="field"><label>Extra fee ({{ $currency }})</label><input class="input" type="number" step="0.01" min="0" name="fee_amount" value="{{ old('fee_amount', $method->fee_amount ?? 0) }}"><div class="hint">e.g. COD handling charge.</div></div>
                    <div class="field"><label>Minimum order ({{ $currency }})</label><input class="input" type="number" step="0.01" min="0" name="min_order" value="{{ old('min_order', $method->min_order) }}"></div>
                    <div class="field"><label>Maximum order ({{ $currency }})</label><input class="input" type="number" step="0.01" min="0" name="max_order" value="{{ old('max_order', $method->max_order) }}"><div class="hint">e.g. disable COD above a certain amount.</div></div>
                </div>
            </div>

            <div class="card sticky-actions">
                <div class="card-body" style="display:grid;gap:10px">
                    <button class="btn btn-primary btn-block">{{ $method->exists ? 'Save changes' : 'Create method' }}</button>
                    <a href="{{ route('admin.payment-methods.index') }}" class="btn btn-light btn-block">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
