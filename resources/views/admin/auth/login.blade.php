@php $storeName = setting('store_name', config('app.name')); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Admin Login · {{ $storeName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="auth">
    <div class="auth-art">
        <div>
            <div style="font-family:var(--serif);font-size:28px;letter-spacing:.3em">{{ strtoupper(explode(' ', $storeName)[0]) }}</div>
            <div style="font-size:10px;letter-spacing:.4em;text-transform:uppercase;color:var(--gold-2);margin-top:6px">Store Admin</div>
        </div>
        <div>
            <h2>Run your store with <em>quiet confidence.</em></h2>
            <p style="color:rgba(245,239,227,.65);max-width:420px">Orders, payments, products and settings — everything in one elegant place.</p>
        </div>
        <small style="color:rgba(245,239,227,.4)">© {{ date('Y') }} {{ $storeName }}</small>
    </div>
    <div class="auth-form">
        <div class="auth-card">
            <h1 style="font-family:var(--serif);font-weight:500;font-size:38px;margin-bottom:6px">Welcome back</h1>
            <p class="muted" style="margin-bottom:28px">Sign in to manage your store.</p>

            @if ($errors->any())
                <div class="alert alert-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}" class="form-grid" style="grid-template-columns:1fr">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
                </div>
                <label class="switch"><input type="checkbox" name="remember" value="1"><span class="track"></span> Keep me signed in</label>
                <button type="submit" class="btn btn-primary btn-block" style="padding:12px">Sign in</button>
            </form>
            <p style="margin-top:26px;font-size:13px"><a href="{{ route('home') }}" class="muted">← Back to store</a></p>
        </div>
    </div>
</div>
</body>
</html>
