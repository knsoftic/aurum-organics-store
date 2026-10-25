{{-- Placed right after <body>: Tag Manager fallback and the store's own visitor counter. --}}
@if ($gtm = trim((string) setting('gtm_id')))
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
@if (\App\Services\Analytics\Tracker::enabled())
<script>
(function () {
    // First-party visit counter (Admin → Marketing → Traffic). A visit ends after 30 idle minutes.
    function id() { var a = new Uint8Array(12); (window.crypto || window.msCrypto).getRandomValues(a); return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
    function get(n) { var m = document.cookie.match('(?:^|; )' + n + '=([A-Za-z0-9]+)'); return m ? m[1] : null; }
    function set(n, v, age) { document.cookie = n + '=' + v + '; path=/; max-age=' + age + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''); }
    var vid = get('{{ \App\Services\Analytics\Tracker::VID_COOKIE }}') || id(), sid = get('{{ \App\Services\Analytics\Tracker::SID_COOKIE }}') || id();
    set('{{ \App\Services\Analytics\Tracker::VID_COOKIE }}', vid, 31536000);
    function touch() { set('{{ \App\Services\Analytics\Tracker::SID_COOKIE }}', sid, 1800); }
    function send(d) {
        touch(); d.sid = sid; d.vid = vid;
        var url = @json(route('track.collect')), body = JSON.stringify(d);
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }))) return;
        } catch (e) {}
        try { fetch(url, { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'application/json' } }); } catch (e) {}
    }
    var tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
    send({ p: location.pathname + location.search, t: document.title, r: document.referrer, w: screen.width, tz: tz, lang: navigator.language });
    window.knBeacon = function (e, v, l) { send({ e: e, v: v, l: l }); };
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a[href*="wa.me"], a[href*="api.whatsapp.com"]');
        if (a) { window.knBeacon('whatsapp'); if (window.knTrack) knTrack('whatsapp', {}); }
    });
})();
</script>
@endif
{!! setting('body_code') !!}
