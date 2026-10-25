@php
    $nav = [
        ['Overview', null],
        ['Dashboard', 'admin.dashboard', 'grid', null, 'admin.dashboard'],
        ['Sales', null],
        ['Orders', 'admin.orders.index', 'receipt', $adminBadges['orders'], 'admin.orders.*'],
        ['Payment Methods', 'admin.payment-methods.index', 'card', null, 'admin.payment-methods.*'],
        ['Coupons', 'admin.coupons.index', 'tag', null, 'admin.coupons.*'],
        ['Catalog', null],
        ['Products', 'admin.products.index', 'package', null, 'admin.products.*'],
        ['Categories', 'admin.categories.index', 'folder', null, 'admin.categories.*'],
        ['Reviews', 'admin.reviews.index', 'star', $adminBadges['reviews'], 'admin.reviews.*'],
        ['Marketing', null],
        ['Traffic', 'admin.marketing.traffic', 'trend', null, 'admin.marketing.traffic*'],
        ['Keywords', 'admin.marketing.keywords.index', 'search', null, 'admin.marketing.keywords.*'],
        ['SEO Guide', 'admin.marketing.seo', 'award', null, 'admin.marketing.seo*'],
        ['Tracking & Pixels', 'admin.marketing.tracking', 'eye', null, 'admin.marketing.tracking*'],
        ['Ad Studio', 'admin.ad-studio.index', 'play', null, 'admin.ad-studio.*'],
        ['Content', null],
        ['Pages', 'admin.pages.index', 'file', null, 'admin.pages.*'],
        ['Messages', 'admin.messages.index', 'inbox', $adminBadges['messages'], 'admin.messages.*'],
        ['Account', null],
        ['Settings', 'admin.settings.edit', 'settings', null, 'admin.settings.*'],
        ['My Profile', 'admin.profile', 'user', null, 'admin.profile'],
    ];
    $storeName = setting('store_name', config('app.name'));
    $nameParts = preg_split('/\s+/', $storeName, 2);
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ setting('theme', 'emerald') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Dashboard') · {{ $storeName }} Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    @stack('head')
</head>
<body>
<div class="admin">
    <aside class="sidebar" id="sidebar">
        <div class="side-brand">
            @if (setting('brand_mark'))<img src="{{ media_url(setting('brand_mark')) }}" alt="" style="width:40px;height:40px;border-radius:50%;margin-bottom:10px">@endif
            <span class="name">{{ strtoupper($nameParts[0]) }}</span>
            <small>{{ $nameParts[1] ?? 'Store' }} · Admin</small>
        </div>
        <nav class="side-nav">
            @foreach ($nav as $item)
                @if ($item[1] === null)
                    <div class="side-label">{{ $item[0] }}</div>
                @else
                    <a href="{{ route($item[1]) }}" class="{{ request()->routeIs($item[4]) ? 'active' : '' }}">
                        <x-icon :name="$item[2]" /> {{ $item[0] }}
                        @if ($item[3])<span class="count">{{ $item[3] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="side-foot">
            <a href="{{ route('home') }}" target="_blank" class="flex"><x-icon name="external" style="width:16px" /> View store</a>
        </div>
    </aside>
    <div class="side-backdrop" data-toggle-sidebar></div>

    <div class="main">
        <header class="topbar">
            <div class="top-title">
                <button class="icon-action menu-btn" type="button" data-toggle-sidebar aria-label="Menu"><x-icon name="menu" /></button>
                <div class="top-heading">
                    <div class="crumb">@yield('crumb', 'Admin')</div>
                    <h1>@yield('title', 'Dashboard')</h1>
                </div>
            </div>
            {{-- Page buttons; on phones they move to their own row under the title. --}}
            @hasSection('actions')
                <div class="top-actions">@yield('actions')</div>
            @endif
            <div class="top-user">
                @if ($adminBadges['payments'])
                    <a href="{{ route('admin.orders.index', ['payment_status' => 'pending_verification']) }}" class="btn btn-light btn-sm" title="Payments awaiting verification">
                        <x-icon name="wallet" /> {{ $adminBadges['payments'] }}<span class="hide-sm"> to verify</span>
                    </a>
                @endif
                <a href="{{ route('admin.profile') }}" class="user-chip" title="My profile">
                    <span class="av">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="n">{{ auth()->user()->name }}</span>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}" class="inline-form">
                    @csrf
                    <button class="icon-action" type="submit" title="Log out"><x-icon name="logout" /></button>
                </form>
            </div>
        </header>

        <main class="content">
            @if (session('success'))<div class="alert alert-success" data-autohide>{{ session('success') }}</div>@endif
            @if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
@stack('scripts')
</body>
</html>
