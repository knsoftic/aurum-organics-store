@php
    $storeName = setting('store_name', config('app.name'));
    $pageTitle = trim($__env->yieldContent('title'));
    $metaDescription = trim($__env->yieldContent('meta_description')) ?: setting('meta_description');
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ setting('theme', 'emerald') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>document.documentElement.classList.add('js')</script>
    <title>{{ $pageTitle ? (str_contains($pageTitle, $storeName) ? $pageTitle : $pageTitle.' — '.$storeName) : setting('meta_title', $storeName) }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $pageTitle ?: setting('meta_title', $storeName) }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    @hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
    <link rel="icon" href="{{ setting('favicon') ? media_url(setting('favicon')) : asset('favicon.ico') }}">
    <meta name="theme-color" content="{{ ['lavender' => '#3b1a6b', 'noir' => '#161616', 'ivory' => '#4b4537', 'rose' => '#4a2a2f'][setting('theme')] ?? '#1f3a2e' }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/store.css') }}?v={{ filemtime(public_path('css/store.css')) }}">
    @stack('head')
</head>
<body class="@yield('body_class')">

@if (setting('announcement_active') && setting('announcement_text'))
    <div class="announce">{{ setting('announcement_text') }}</div>
@endif

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <div style="display:flex;align-items:center;gap:6px">
            <button class="icon-btn menu-toggle" type="button" data-open-menu aria-label="Open menu"><x-icon name="menu" /></button>
            <nav class="nav" aria-label="Main">
                <a href="{{ route('shop') }}" class="{{ request()->routeIs('shop', 'product.show') ? 'active' : '' }}">Shop</a>
                <a href="{{ route('home') }}#story">Our Story</a>
                <a href="{{ route('home') }}#faq">FAQ</a>
            </nav>
        </div>

        @include('store.partials.brand')

        <div class="header-actions">
            <nav class="nav" aria-label="Secondary" style="margin-right:18px">
                <a href="{{ route('track') }}" class="{{ request()->routeIs('track') ? 'active' : '' }}">Track Order</a>
                <a href="{{ route('contact') }}" class="{{ request()->routeIs('contact') ? 'active' : '' }}">Contact</a>
            </nav>
            <button class="icon-btn" type="button" data-open-cart aria-label="Open bag">
                <x-icon name="bag" />
                <span class="cart-count" data-cart-count data-count="{{ $cartCount }}">{{ $cartCount }}</span>
            </button>
        </div>
    </div>
</header>

<div class="mobile-nav" id="mobileNav" aria-hidden="true">
    <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <span class="eyebrow">Menu</span>
            <button class="icon-btn" type="button" data-close-menu aria-label="Close menu"><x-icon name="close" /></button>
        </div>
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('shop') }}">Shop</a>
        <a href="{{ route('home') }}#story" data-close-menu>Our Story</a>
        <a href="{{ route('track') }}">Track Order</a>
        <a href="{{ route('contact') }}">Contact</a>
        @if ($wa = whatsapp_link())
            <a href="{{ $wa }}" target="_blank" rel="noopener" style="font-family:var(--sans);font-size:15px;border:0;margin-top:auto;color:var(--success)">Chat on WhatsApp →</a>
        @endif
    </div>
</div>

<main>
    @yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                @include('store.partials.brand')
                <p style="max-width:320px;font-weight:300">{{ setting('footer_text', setting('tagline')) }}</p>
                <div class="socials">
                    @foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $network)
                        @if (setting('social_'.$network))
                            <a href="{{ setting('social_'.$network) }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><x-icon :name="$network" /></a>
                        @endif
                    @endforeach
                    @if ($wa = whatsapp_link())
                        <a href="{{ $wa }}" target="_blank" rel="noopener" aria-label="WhatsApp"><x-icon name="whatsapp" /></a>
                    @endif
                </div>
            </div>
            <div>
                <h4>Shop</h4>
                <ul>
                    <li><a href="{{ route('shop') }}">All Products</a></li>
                    @foreach ($navCategories as $navCategory)
                        <li><a href="{{ route('shop', ['category' => $navCategory->slug]) }}">{{ $navCategory->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('cart.index') }}">Your Bag</a></li>
                    <li><a href="{{ route('track') }}">Track Your Order</a></li>
                </ul>
            </div>
            <div>
                <h4>Information</h4>
                <ul>
                    @foreach ($footerPages as $footerPage)
                        <li><a href="{{ route('page.show', $footerPage->slug) }}">{{ $footerPage->title }}</a></li>
                    @endforeach
                    <li><a href="{{ route('contact') }}">Contact Us</a></li>
                </ul>
            </div>
            <div>
                @if (setting('store_phone') || setting('store_email') || setting('store_address'))<h4>Get in touch</h4>@endif
                <ul>
                    @if (setting('store_phone'))<li style="display:flex;gap:10px"><x-icon name="phone" style="width:18px;flex:none;color:var(--gold-2)" /> <a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store_phone')) }}">{{ setting('store_phone') }}</a></li>@endif
                    @if (setting('store_email'))<li style="display:flex;gap:10px"><x-icon name="mail" style="width:18px;flex:none;color:var(--gold-2)" /> <a href="mailto:{{ setting('store_email') }}">{{ setting('store_email') }}</a></li>@endif
                    @if (setting('store_address'))<li style="display:flex;gap:10px"><x-icon name="pin" style="width:18px;flex:none;color:var(--gold-2)" /> <span>{{ setting('store_address') }}</span></li>@endif
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>© {{ date('Y') }} {{ $storeName }}. All rights reserved.</span>
            <div class="footer-pay">
                @foreach ($footerPayments as $footerPayment)
                    <img src="{{ $footerPayment->logo_url }}" alt="{{ $footerPayment->name }}" title="{{ $footerPayment->name }}">
                @endforeach
            </div>
        </div>
    </div>
</footer>

{{-- Cart drawer --}}
<aside class="drawer" id="cartDrawer" aria-label="Shopping bag" aria-hidden="true">
    <div class="drawer-head">
        <h3>Your Bag</h3>
        <button class="icon-btn" type="button" data-close-cart aria-label="Close bag"><x-icon name="close" /></button>
    </div>
    <div class="drawer-body" id="cartDrawerBody">
        @include('store.partials.cart-drawer-body', ['items' => $cartItems, 'totals' => $cartTotals, 'freeShippingRemaining' => $cartFreeShippingRemaining])
    </div>
</aside>
<div class="overlay" id="overlay"></div>

<div class="toast" id="toast" role="status" aria-live="polite"><x-icon name="check-circle" /><span></span></div>

@if (setting('show_whatsapp_button', '1') && ($wa = whatsapp_link('Hi! I would like to know more about your products.')))
    <a href="{{ $wa }}" class="wa-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><x-icon name="whatsapp" /></a>
@endif

<script>
    window.Store = { csrf: '{{ csrf_token() }}', flash: @json(session('success') ?? session('error')), flashType: '{{ session('error') ? 'error' : 'success' }}' };
</script>
<script src="{{ asset('js/store.js') }}?v={{ filemtime(public_path('js/store.js')) }}"></script>
@stack('scripts')
</body>
</html>
