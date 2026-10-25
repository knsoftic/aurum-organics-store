@extends('layouts.admin')

@section('title', 'Tracking & Pixels')
@section('crumb', 'Marketing')

@php
    $v = fn ($key, $default = '') => old($key, setting($key, $default));
    $on = fn ($key, $default = '0') => old($key, setting($key, $default)) === '1';
    $active = collect([
        'Google Search Console' => setting('verify_google'),
        'Bing' => setting('verify_bing'),
        'Google Analytics 4' => setting('ga4_id'),
        'Tag Manager' => setting('gtm_id'),
        'Google Ads' => setting('gads_id'),
        'AdSense' => setting('adsense_enabled') === '1' ? setting('adsense_client') : null,
        'Meta Pixel' => setting('meta_pixel_id'),
        'TikTok Pixel' => setting('tiktok_pixel_id'),
        'Snapchat Pixel' => setting('snap_pixel_id'),
        'Pinterest Tag' => setting('pinterest_tag_id'),
        'X (Twitter) Pixel' => setting('x_pixel_id'),
    ]);
    $field = function (string $name, string $label, string $placeholder, string $hint = '') use ($v) {
        return '<div class="field"><label>'.e($label).'</label><input class="input" name="'.$name.'" value="'.e($v($name)).'" placeholder="'.e($placeholder).'" autocomplete="off">'
            .($hint ? '<div class="hint">'.$hint.'</div>' : '').'</div>';
    };
@endphp

@section('content')
<form method="POST" action="{{ route('admin.marketing.tracking.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="grid grid-main">
        <div>
            <div class="card">
                <div class="card-head"><h3>1 · Site verification (meta tags)</h3></div>
                <div class="card-body form-grid">
                    <p class="muted full" style="margin:0;font-size:12.5px">Paste the code — or the whole <code>&lt;meta …&gt;</code> tag — the platform gives you. Choose the “HTML tag” method when the platform asks how to verify.</p>
                    {!! $field('verify_google', 'Google Search Console', 'google-site-verification code', 'Search Console → Add property → URL prefix → HTML tag.') !!}
                    {!! $field('verify_bing', 'Bing Webmaster Tools', 'msvalidate.01 code', 'Or simply import your site from Google Search Console in Bing.') !!}
                    {!! $field('verify_facebook', 'Facebook / Meta domain', 'facebook-domain-verification code', 'Business Settings → Brand safety → Domains → Meta-tag.') !!}
                    {!! $field('verify_pinterest', 'Pinterest', 'p:domain_verify code') !!}
                    {!! $field('verify_yandex', 'Yandex', 'yandex-verification code') !!}
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>2 · Google Analytics, Tag Manager &amp; Ads</h3></div>
                <div class="card-body form-grid">
                    {!! $field('ga4_id', 'Google Analytics 4 — Measurement ID', 'G-XXXXXXXXXX', 'Analytics → Admin → Data streams → Web → Measurement ID.') !!}
                    {!! $field('gtm_id', 'Google Tag Manager — Container ID', 'GTM-XXXXXXX', 'Optional. If you add GA4 inside Tag Manager, leave the GA4 box empty so visits are not counted twice.') !!}
                    {!! $field('gads_id', 'Google Ads — Conversion ID', 'AW-123456789', 'Google Ads → Goals → Conversions → Purchase → Tag setup → “Use Google tag”.') !!}
                    {!! $field('gads_purchase_label', 'Google Ads — Purchase conversion label', 'AbC-D_efG123', 'The part after the slash in send_to: AW-123456789/<strong>AbC-D_efG123</strong>.') !!}
                    <div class="field"><label>Currency code for conversions</label><input class="input" name="currency_code" value="{{ $v('currency_code', 'PKR') }}" maxlength="3" style="text-transform:uppercase"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>3 · Advertising pixels</h3></div>
                <div class="card-body form-grid">
                    <p class="muted full" style="margin:0;font-size:12.5px">Each pixel gets: page views, <strong>ViewContent</strong> (product page), <strong>AddToCart</strong>, <strong>InitiateCheckout</strong> and <strong>Purchase</strong> with the order value — ready for conversion campaigns.</p>
                    {!! $field('meta_pixel_id', 'Meta (Facebook & Instagram) Pixel ID', '123456789012345', 'Events Manager → Data sources → your pixel → Settings.') !!}
                    {!! $field('tiktok_pixel_id', 'TikTok Pixel ID', 'CABC123DEF456GHI789', 'TikTok Ads Manager → Tools → Events → Web events.') !!}
                    {!! $field('snap_pixel_id', 'Snapchat Pixel ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx') !!}
                    {!! $field('pinterest_tag_id', 'Pinterest Tag ID', '2612345678901') !!}
                    {!! $field('x_pixel_id', 'X (Twitter) Pixel ID', 'o1abc') !!}
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>4 · Google AdSense</h3></div>
                <div class="card-body form-grid">
                    {!! $field('adsense_client', 'Publisher ID', 'ca-pub-1234567890123456', 'AdSense → Account → Account information.') !!}
                    <div class="field" style="display:grid;gap:10px;align-content:center">
                        <label class="switch"><input type="checkbox" name="adsense_enabled" value="1" @checked($on('adsense_enabled'))><span class="track"></span> Load AdSense (verification + Auto ads)</label>
                        <label class="switch"><input type="checkbox" name="adsense_skip_checkout" value="1" @checked($on('adsense_skip_checkout', '1'))><span class="track"></span> No ads on bag, checkout &amp; order pages</label>
                    </div>
                    <div class="field full"><label>ads.txt</label><textarea class="textarea" name="ads_txt" style="min-height:70px;font-family:ui-monospace,monospace;font-size:12.5px" placeholder="Leave empty — the Google line is created from your Publisher ID">{{ $v('ads_txt') }}</textarea>
                        <div class="hint">Served at <a href="{{ route('seo.ads') }}" target="_blank" style="text-decoration:underline">{{ route('seo.ads') }}</a>. Ads on a shop can distract buyers — keep them off checkout.</div></div>
                </div>
            </div>

            <div class="card" id="geo">
                <div class="card-head"><h3>5 · Visitor analytics (built in)</h3></div>
                <div class="card-body form-grid">
                    <div class="field" style="align-self:center"><label class="switch"><input type="checkbox" name="analytics_enabled" value="1" @checked($on('analytics_enabled', '1'))><span class="track"></span> Count visits (Marketing → Traffic)</label>
                        <div class="hint">Your own visits are not counted while you are logged in to the admin.</div></div>
                    <div class="field"><label>Keep visit history for (days)</label><input class="input" type="number" name="analytics_retention" min="30" max="1095" value="{{ $v('analytics_retention', 365) }}"></div>
                    <div class="field full">
                        <label>City-level location (free MaxMind GeoLite2)</label>
                        @if ($geoip)
                            <div class="alert alert-success" style="margin:0 0 10px">Location database installed · {{ number_format($geoip['size'] / 1048576, 1) }} MB · updated {{ \Carbon\Carbon::createFromTimestamp($geoip['date'])->diffForHumans() }}.</div>
                        @else
                            <div class="alert alert-info" style="margin:0 0 10px">Countries already work (from Cloudflare or the visitor's time zone). For <strong>cities</strong>, add a free GeoLite2 database below.</div>
                        @endif
                        <div class="form-grid" style="gap:10px 12px">
                            <div class="field"><label style="font-weight:500">MaxMind account ID</label><input class="input" name="maxmind_account" value="{{ $v('maxmind_account') }}" placeholder="123456"></div>
                            <div class="field"><label style="font-weight:500">Licence key</label><input class="input" type="password" name="maxmind_key" placeholder="{{ setting('maxmind_key') ? '•••••••• saved' : 'Licence key' }}" autocomplete="new-password"></div>
                        </div>
                        <div class="hint">Free account at maxmind.com → GeoLite2 → Manage licence keys. Save, then press “Download database”. Or upload the <code>.mmdb</code> file: <input type="file" name="geoip_file" accept=".mmdb" style="font-size:12.5px;margin-top:6px"></div>
                        @if (setting('maxmind_account') && setting('maxmind_key'))
                            <button type="submit" form="geoipForm" class="btn btn-light btn-sm" style="margin-top:10px"><x-icon name="download" /> Download database</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card" id="gsc">
                <div class="card-head"><h3>6 · Search Console API (keyword rankings)</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Property</label><input class="input" name="gsc_site" value="{{ $v('gsc_site') }}" placeholder="sc-domain:{{ request()->getHost() }}  or  {{ url('/') }}/">
                        <div class="hint">Exactly as shown in Search Console's property list. Domain properties start with <code>sc-domain:</code>.</div></div>
                    <div class="field full"><label>Service-account key (JSON)</label>
                        <textarea class="textarea" name="gsc_credentials" style="min-height:80px;font-family:ui-monospace,monospace;font-size:12px" placeholder="{{ $gscEmail ? 'Saved — paste a new key to replace it' : '{ &quot;type&quot;: &quot;service_account&quot;, … }' }}"></textarea>
                        @if ($gscEmail)
                            <div class="hint">Connected as <strong>{{ $gscEmail }}</strong> — this email must be a user on the property. <label class="switch" style="margin-left:8px;font-weight:400"><input type="checkbox" name="remove_gsc_credentials" value="1"><span class="track"></span> Remove key</label></div>
                        @else
                            <div class="hint">Step-by-step instructions are in <a href="{{ route('admin.marketing.seo') }}#gsc-api" style="text-decoration:underline">SEO Guide → Connect Search Console</a>.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>7 · Custom code (any other platform)</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Inside &lt;head&gt;</label><textarea class="textarea" name="head_code" style="min-height:90px;font-family:ui-monospace,monospace;font-size:12px" placeholder="<script>…</script>">{{ $v('head_code') }}</textarea></div>
                    <div class="field full"><label>Start of &lt;body&gt;</label><textarea class="textarea" name="body_code" style="min-height:70px;font-family:ui-monospace,monospace;font-size:12px">{{ $v('body_code') }}</textarea>
                        <div class="hint">Added to every store page exactly as typed. Only paste code from platforms you trust.</div></div>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-head"><h3>Connected</h3></div>
                <div class="card-body" style="display:grid;gap:8px;font-size:13px">
                    @foreach ($active as $name => $value)
                        <div class="flex between"><span>{{ $name }}</span>@if ($value)<span class="badge b-green">On</span>@else<span class="badge b-gray">Off</span>@endif</div>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Check it works</h3></div>
                <div class="card-body" style="display:grid;gap:10px;font-size:13px">
                    <a class="btn btn-light btn-sm" href="{{ route('seo.sitemap') }}" target="_blank"><x-icon name="external" /> sitemap.xml</a>
                    <a class="btn btn-light btn-sm" href="{{ route('seo.robots') }}" target="_blank"><x-icon name="external" /> robots.txt</a>
                    <a class="btn btn-light btn-sm" href="{{ route('seo.llms') }}" target="_blank" title="Store summary for AI assistants (ChatGPT, Gemini, Perplexity)"><x-icon name="external" /> llms.txt (for AI)</a>
                    <a class="btn btn-light btn-sm" href="https://search.google.com/test/rich-results?url={{ urlencode(url('/')) }}" target="_blank" rel="noopener"><x-icon name="external" /> Google Rich Results test</a>
                    <p class="muted" style="margin:0">Pixels: install the <strong>Meta Pixel Helper</strong> / <strong>TikTok Pixel Helper</strong> Chrome extensions and open the store — they show each event as it fires.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card sticky-actions" style="margin-top:20px">
        <div class="form-actions" style="border-top:0">
            <button class="btn btn-primary">Save tracking settings</button>
        </div>
    </div>
</form>
<form id="geoipForm" method="POST" action="{{ route('admin.marketing.tracking.geoip') }}">@csrf</form>
@endsection
