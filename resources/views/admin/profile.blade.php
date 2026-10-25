@extends('layouts.admin')

@section('title', 'My Profile')

@section('content')
<form method="POST" action="{{ route('admin.profile.update') }}" class="card" style="max-width:720px">
    @csrf @method('PUT')
    <div class="card-head"><h3>Account</h3></div>
    <div class="card-body form-grid">
        <div class="field"><label>Name</label><input class="input" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div class="field"><label>Email (login)</label><input type="email" class="input" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div class="field full"><label>Current password</label><input type="password" class="input" name="current_password" autocomplete="current-password"><div class="hint">Required only when setting a new password.</div></div>
        <div class="field"><label>New password</label><input type="password" class="input" name="password" autocomplete="new-password"></div>
        <div class="field"><label>Confirm new password</label><input type="password" class="input" name="password_confirmation" autocomplete="new-password"></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary">Save changes</button></div>
</form>
@endsection
