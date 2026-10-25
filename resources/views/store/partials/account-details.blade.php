{{-- Account rows for a manual payment method, with copy buttons. Expects $method. --}}
@if ($method->details)
    <div class="account-box">
        @foreach ($method->details as $row)
            <div class="account-row">
                <span>{{ $row['label'] }}</span>
                <strong>{{ $row['value'] }}</strong>
                <button type="button" class="copy-btn" data-copy="{{ $row['value'] }}"><x-icon name="copy" /> Copy</button>
            </div>
        @endforeach
    </div>
@endif
@if ($method->qr_url)
    <img src="{{ $method->qr_url }}" alt="{{ $method->name }} QR code" class="qr" style="margin-bottom:14px">
@endif
