{{-- Verification tags, Google tags, ad pixels and the shared knTrack() event helper.
     Everything is configured in Admin → Marketing → Tracking & Pixels. --}}
@php
    $tag = fn (string $key) => trim((string) setting($key));
    $ga4 = $tag('ga4_id');
    $gads = $tag('gads_id');
    $gtm = $tag('gtm_id');
    $checkoutPage = request()->routeIs('cart.*', 'checkout', 'checkout.*', 'order.*', 'track', 'track.*', 'payment.*');
    $trackConfig = [
        'currency' => setting('currency_code', 'PKR'),
        'adsSendTo' => $gads && $tag('gads_purchase_label') ? $gads.'/'.$tag('gads_purchase_label') : null,
        'gtm' => (bool) $gtm,
    ];
@endphp
@foreach (['verify_google' => 'google-site-verification', 'verify_bing' => 'msvalidate.01', 'verify_yandex' => 'yandex-verification', 'verify_facebook' => 'facebook-domain-verification', 'verify_pinterest' => 'p:domain_verify'] as $key => $metaName)
    @if ($code = $tag($key))<meta name="{{ $metaName }}" content="{{ $code }}">
    @endif
@endforeach
<script>
(function () {
    var C = @json($trackConfig);
    var MAP = {
        view_item: ['ViewContent', 'ViewContent', 'view_item', 'VIEW_CONTENT', 'pagevisit'],
        add_to_cart: ['AddToCart', 'AddToCart', 'add_to_cart', 'ADD_CART', 'addtocart'],
        begin_checkout: ['InitiateCheckout', 'InitiateCheckout', 'begin_checkout', 'START_CHECKOUT', null],
        purchase: ['Purchase', 'CompletePayment', 'purchase', 'PURCHASE', 'checkout'],
        whatsapp: ['Contact', 'Contact', 'whatsapp_click', null, null],
        contact: ['Contact', 'Contact', 'generate_lead', null, 'lead']
    };
    // One call sends the same shop event to every connected platform.
    window.knTrack = function (name, d) {
        var m = MAP[name]; if (!m) return;
        d = d || {};
        var cur = C.currency, items = d.items || [], value = Number(d.value) || 0;
        var ids = items.map(function (i) { return String(i.id); });
        var qty = items.reduce(function (s, i) { return s + (Number(i.qty) || 1); }, 0) || 1;
        var eid = d.eventId || (name + '-' + Date.now());
        try { if (window.fbq) fbq('track', m[0], { value: value, currency: cur, content_ids: ids, content_type: 'product', content_name: items[0] && items[0].name, num_items: qty, order_id: d.orderId, contents: items.map(function (i) { return { id: String(i.id), quantity: Number(i.qty) || 1, item_price: Number(i.price) || 0 }; }) }, { eventID: eid }); } catch (e) {}
        try { if (window.ttq) ttq.track(m[1], { value: value, currency: cur, content_type: 'product', contents: items.map(function (i) { return { content_id: String(i.id), content_name: i.name, quantity: Number(i.qty) || 1, price: Number(i.price) || 0 }; }) }, { event_id: eid }); } catch (e) {}
        try {
            if (window.gtag) {
                gtag('event', m[2], { currency: cur, value: value, transaction_id: d.orderId, items: items.map(function (i) { return { item_id: String(i.id), item_name: i.name, price: Number(i.price) || 0, quantity: Number(i.qty) || 1 }; }) });
                if (name === 'purchase' && C.adsSendTo) gtag('event', 'conversion', { send_to: C.adsSendTo, value: value, currency: cur, transaction_id: d.orderId });
            }
        } catch (e) {}
        try { if (window.snaptr && m[3]) snaptr('track', m[3], { price: value, currency: cur, item_ids: ids, number_items: qty, transaction_id: d.orderId, client_dedup_id: eid }); } catch (e) {}
        try { if (window.pintrk && m[4]) pintrk('track', m[4], { value: value, currency: cur, order_quantity: qty, order_id: d.orderId, event_id: eid, line_items: items.map(function (i) { return { product_id: String(i.id), product_name: i.name, product_price: Number(i.price) || 0, product_quantity: Number(i.qty) || 1 }; }) }); } catch (e) {}
        try { if (C.gtm && window.dataLayer) { dataLayer.push({ ecommerce: null }); dataLayer.push({ event: m[2], ecommerce: { currency: cur, value: value, transaction_id: d.orderId, items: items.map(function (i) { return { item_id: String(i.id), item_name: i.name, price: Number(i.price) || 0, quantity: Number(i.qty) || 1 }; }) } }); } } catch (e) {}
    };
})();
</script>
@if ($gtm)
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
@endif
@if ($ga4 || $gads)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 ?: $gads }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    @if ($ga4)gtag('config', @json($ga4));@endif
    @if ($gads)gtag('config', @json($gads));@endif
</script>
@endif
@if ($id = $tag('meta_pixel_id'))
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($id));fbq('track','PageView');</script>
<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $id }}&ev=PageView&noscript=1"></noscript>
@endif
@if ($id = $tag('tiktok_pixel_id'))
<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};ttq.load(@json($id));ttq.page();}(window,document,'ttq');</script>
@endif
@if ($id = $tag('snap_pixel_id'))
<script>(function(e,t,n){if(e.snaptr)return;var a=e.snaptr=function(){a.handleRequest?a.handleRequest.apply(a,arguments):a.queue.push(arguments)};a.queue=[];var s='script',r=t.createElement(s);r.async=!0;r.src=n;var u=t.getElementsByTagName(s)[0];u.parentNode.insertBefore(r,u);})(window,document,'https://sc-static.net/scevent.min.js');snaptr('init',@json($id),{});snaptr('track','PAGE_VIEW');</script>
@endif
@if ($id = $tag('pinterest_tag_id'))
<script>!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version="3.0";var t=document.createElement("script");t.async=!0,t.src=e;var r=document.getElementsByTagName("script")[0];r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");pintrk('load',@json($id));pintrk('page');</script>
@endif
@if ($id = $tag('x_pixel_id'))
<script>!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments);},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');twq('config',@json($id));</script>
@endif
@if (setting('adsense_enabled') === '1' && ($client = $tag('adsense_client')) && ! ($checkoutPage && setting('adsense_skip_checkout', '1') === '1'))
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $client }}" crossorigin="anonymous"></script>
@endif
{!! setting('head_code') !!}
