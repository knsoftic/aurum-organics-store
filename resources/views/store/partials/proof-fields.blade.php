{{-- Transaction reference + sender + screenshot upload (shared by checkout and order page). --}}
<div class="form-grid">
    <div class="field">
        <label for="reference">Transaction ID / Reference</label>
        <input id="reference" class="input @error('reference') is-invalid @enderror" name="reference" value="{{ old('reference') }}" placeholder="e.g. 0123456789" maxlength="120">
        @error('reference')<div class="field-error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="sender_account">Sent from <span class="opt">(account / number / wallet)</span></label>
        <input id="sender_account" class="input" name="sender_account" value="{{ old('sender_account') }}" placeholder="e.g. 0300 1234567" maxlength="120">
    </div>
    <div class="field full">
        <label>Payment screenshot</label>
        <div class="file-drop">
            <input type="file" name="proof" accept="image/*,.pdf">
            <x-icon name="upload" />
            <span data-file-name data-default="Tap to upload screenshot">Tap to upload screenshot</span>
            <small>JPG, PNG or PDF · max 5 MB</small>
        </div>
        @error('proof')<div class="field-error">{{ $message }}</div>@enderror
    </div>
</div>
