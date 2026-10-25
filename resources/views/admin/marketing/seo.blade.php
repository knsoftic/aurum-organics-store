@extends('layouts.admin')

@section('title', 'SEO Guide')
@section('crumb', 'Marketing')

@php
    $mark = ['ok' => '✓', 'warn' => '!', 'bad' => '✕'];
    $scoreColor = $score >= 80 ? 'var(--success)' : ($score >= 55 ? '#c98a1b' : 'var(--danger)');
    $sections = [
        'setup' => ['1 · Pehla hafta: Google ko site batayein (Technical setup)', [
            ['gsc-add', 'Google Search Console mein property banayein: <a href="https://search.google.com/search-console" target="_blank" rel="noopener">search.google.com/search-console</a> → Add property → <strong>URL prefix</strong> → <code>https://'.$domain.'/</code> → “HTML tag” code copy karke <a href="'.route('admin.marketing.tracking').'">Tracking &amp; Pixels</a> mein paste karein → Save → Verify.'],
            ['gsc-sitemap', 'Search Console → <strong>Sitemaps</strong> → <code>sitemap.xml</code> likh kar Submit karein.'],
            ['gsc-index', 'Search Console → <strong>URL Inspection</strong> → home page aur product page ka link daal kar “Request indexing” dabayein.'],
            ['bing', '<a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools</a> → “Import from Google Search Console” (2 minute ka kaam).'],
            ['ga4', 'Google Analytics 4 property banayein aur Measurement ID (G-…) Tracking &amp; Pixels mein daalein. (Built-in Traffic report bhi chal rahi hai.)'],
            ['gbp', '<a href="https://business.google.com" target="_blank" rel="noopener">Google Business Profile</a> banayein: brand naam, category “Hair care / Beauty products”, phone, website, logo, product photos. “Service area” mein Pakistan ke shehar likhein.'],
            ['https', 'Live site par <code>APP_URL=https://'.$domain.'</code> aur <code>APP_DEBUG=false</code> hon (.env file).'],
        ]],
        'onpage' => ['2 · On-page SEO: har product aur page par', [
            ['focus', 'Har product ka ek <strong>focus keyword</strong> chunein (Keywords page se) — jaise “organic hair oil in Pakistan” — aur product form ke SEO box mein likhein.'],
            ['title', '<strong>Meta title</strong> formula: <code>Keyword – Fayda | Brand</code>, 50–60 characters. Misal: <em>Organic Hair Oil for Hair Growth | KN Organic</em>.'],
            ['desc', '<strong>Meta description</strong> (120–160 characters): kya hai + sab se bara fayda + wajah click karne ki. Misal: <em>14+ herbs wala 100% natural hair oil. Hair fall kam, baal mazboot. Rs 2,200, free delivery &amp; Cash on Delivery poore Pakistan mein.</em>'],
            ['content', 'Product description kam az kam <strong>300–600 alfaaz</strong>: kya hai, kis ke liye, fayde, ingredients, istemal ka tareeqa, kitne din mein nateeja, aur 4–5 FAQs. Keyword 2–5 dafa qudrati andaz mein, saath Roman Urdu alfaaz (“baal girna”, “balon ka tail”).'],
            ['headings', 'Description mein chhote headings (H3) aur bullet points rakhein — log aur Google dono asaani se parhte hain.'],
            ['images', 'Photos upload karne se pehle file ka naam theek karein: <code>magical-hair-oil-200ml.jpg</code> (IMG_1234.jpg nahi). 200 KB se choti, WebP/JPG. Alt text khud lag jata hai.'],
            ['reviews', 'Asli customers se reviews mangwayein (order ke baad WhatsApp par link). Approved review hone par Google mein ⭐ rating dikh sakti hai.'],
            ['internal', 'About Us, policies aur har article page se product page ka link dein (anchor text mein keyword).'],
            ['faq', 'Home page FAQ (Settings → Storefront) mein woh sawal likhein jo log Google par poochte hain — FAQ schema khud lag jata hai.'],
            ['speed', 'Speed check: <a href="https://pagespeed.web.dev/analysis?url='.urlencode('https://'.$domain).'" target="_blank" rel="noopener">PageSpeed Insights</a> — mobile score 70+ rakhein (bari photos/videos compress karein).'],
        ]],
        'content' => ['3 · Content plan: organic traffic ka asal zariya', [
            ['articles', 'Admin → Pages se har mahine 2–4 helpful articles banayein, har ek ka apna keyword. Ideas: “Baal girne ki 10 wajohat aur ghar ka ilaj”, “Rosemary oil ke fayde baalon ke liye”, “Baalon mein tail lagane ka sahi tareeqa”, “Garmiyon mein baalon ki hifazat”.'],
            ['article-format', 'Har article: 800+ alfaaz, apni photos, aakhir mein product ka link aur “Order now — Cash on Delivery” line. Focus keyword title aur pehle paragraph mein.'],
            ['urdu', 'Roman Urdu aur Urdu searches bohot aati hain — kuch articles Urdu/Roman Urdu mein bhi likhein.'],
            ['refresh', 'Har 3 mahine purane articles update karein (naye sawal, nayi photos) — Google taaza content pasand karta hai.'],
        ]],
        'offpage' => ['4 · Off-page SEO: bahar se links aur brand', [
            ['social', 'Facebook, Instagram, TikTok, YouTube, Pinterest profiles mukammal karein: same naam, logo, bio mein website link. Links Settings → SEO &amp; Social mein daalein.'],
            ['youtube', 'Ad Studio ki videos YouTube Shorts / TikTok / Reels par lagayein: title mein keyword, description mein website link.'],
            ['pinterest', 'Pinterest par product photos pin karein, har pin product page se linked.'],
            ['directories', 'Business listings: Google Business Profile, Bing Places, Facebook Page, aur Pakistani business directories — har jagah naam, phone, website <strong>bilkul ek jaise</strong>.'],
            ['marketplace', 'Daraz / doosre marketplaces par brand store — “KN Organic” brand searches barhti hain.'],
            ['influencers', 'Beauty/hair-care bloggers aur YouTubers ko product bhej kar honest review — description mein aap ki website ka link (sab se taaqatwar backlinks).'],
            ['qa', 'Quora / Reddit / Facebook groups mein baalon ke sawalon ke mufeed jawab dein; jahan munasib ho article ka link.'],
            ['pr', 'Local news/lifestyle websites par brand story ya “Pakistani organic brand” feature.'],
            ['avoid', 'Links <strong>khareedna</strong>, spam comments ya PBN bilkul nahi — Google penalty lag sakti hai.'],
            ['links-report', 'Har mahine Search Console → <strong>Links</strong> mein dekhein kaun si sites aap ko link kar rahi hain.'],
        ]],
        'ads' => ['5 · Ads tracking (Meta, TikTok, Google)', [
            ['pixels', 'Meta Pixel, TikTok Pixel aur Google Ads conversion label Tracking &amp; Pixels mein daalein. Purchase event order value ke saath khud jaata hai.'],
            ['utm', 'Har ad / post ke link mein UTM tags lagayein (neeche link builder) — Traffic page par har campaign ke visits aur orders alag nazar aayenge.'],
            ['helper', 'Chrome mein Meta Pixel Helper aur TikTok Pixel Helper install karke store kholein — har event check karein.'],
            ['domain', 'Meta Business → Domains mein domain verify karein (code Tracking &amp; Pixels mein).'],
        ]],
        'monthly' => ['6 · Har mahine ka review', [
            ['m-keywords', 'Keywords page: jo keywords position 11–20 (page 2) par hain unke page ka content behtar karein — ye sab se jaldi page 1 par aate hain.'],
            ['m-ctr', 'Achhi position lekin kam CTR? Meta title/description zyada dilchasp banayein (price, COD, free delivery).'],
            ['m-traffic', 'Traffic page: kaun sa channel orders la raha hai — budget wahan barhayein. Busiest hours mein ads aur posts chalayein.'],
            ['m-audit', 'Is page ka SEO score dekhein aur laal/peele points theek karein.'],
        ]],
    ];
    $totalTasks = collect($sections)->sum(fn ($s) => count($s[1]));
    $doneTasks = collect($sections)->flatMap(fn ($s) => collect($s[1])->pluck(0))->intersect($tasks)->count();
@endphp

@section('content')
<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h3>SEO audit</h3><span class="muted" style="font-size:12.5px">Checked live from your store</span></div>
            <div class="card-body">
                <div class="flex" style="gap:18px;margin-bottom:10px">
                    <div style="width:84px;height:84px;border-radius:50%;display:grid;place-items:center;font-size:26px;font-weight:700;color:{{ $scoreColor }};border:6px solid {{ $scoreColor }}">{{ $score }}</div>
                    <div><strong style="font-size:15px">SEO score: {{ $score }}/100</strong><p class="muted" style="margin:4px 0 0;font-size:13px">Fix the red and amber points below, then work through the guide. Rankings usually move 4–12 weeks after changes.</p></div>
                </div>
                <h4 style="margin:16px 0 4px">Whole site</h4>
                @foreach ($site as [$name, $state, $note])
                    <div class="check-row"><span class="st {{ $state }}">{{ $mark[$state] }}</span><div><strong>{{ $name }}</strong><div class="muted" style="font-size:12.5px">{{ $note }}</div></div></div>
                @endforeach
            </div>
        </div>

        @foreach ([['Products', $products], ['Pages', $pages]] as [$heading, $items])
            @foreach ($items as $item)
                @php $bad = collect($item['checks'])->where(1, '!=', 'ok')->count(); @endphp
                <details class="card" style="margin-top:20px" @if ($loop->parent->first && $loop->first) open @endif>
                    <summary class="card-head" style="cursor:pointer;list-style:none">
                        <h3>{{ $heading === 'Products' ? '🛍️' : '📄' }} {{ $item['model']->name ?? $item['model']->title }}</h3>
                        <span>@if ($bad)<span class="badge b-amber">{{ $bad }} to improve</span>@else<span class="badge b-green">All good</span>@endif <a href="{{ $item['edit'] }}" class="btn btn-light btn-sm" style="margin-left:6px">Edit SEO</a></span>
                    </summary>
                    <div class="card-body">
                        @foreach ($item['checks'] as [$name, $state, $note])
                            <div class="check-row"><span class="st {{ $state }}">{{ $mark[$state] }}</span><div><strong>{{ $name }}</strong><div class="muted" style="font-size:12.5px">{{ $note }}</div></div></div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        @endforeach

        <div class="card guide" style="margin-top:20px">
            <div class="card-head"><h3>Guide: organic traffic step by step</h3><span class="muted" style="font-size:12.5px" data-task-count>{{ $doneTasks }}/{{ $totalTasks }} done</span></div>
            <div class="card-body">
                @foreach ($sections as $key => [$title, $items])
                    <h2 id="{{ $key }}" style="{{ $loop->first ? '' : 'margin-top:26px' }}">{{ $title }}</h2>
                    @foreach ($items as [$id, $html])
                        <label class="task {{ in_array($id, $tasks, true) ? 'done' : '' }}"><input type="checkbox" data-task="{{ $id }}" @checked(in_array($id, $tasks, true))><span>{!! $html !!}</span></label>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="card guide" id="gsc-api" style="margin-top:20px">
            <div class="card-head"><h3>Connect Search Console (real keyword rankings)</h3></div>
            <div class="card-body">
                <p>Is se Keywords page par Google ki asli position, clicks aur impressions roz khud aa jayenge. Ek dafa 10 minute ka kaam hai (free):</p>
                <ol>
                    <li><a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">console.cloud.google.com</a> par naya project banayein (naam: <code>store-seo</code>).</li>
                    <li><strong>APIs &amp; Services → Library</strong> → “Google Search Console API” search karein → <strong>Enable</strong>.</li>
                    <li><strong>IAM &amp; Admin → Service accounts → Create service account</strong> → naam <code>seo-reader</code> → Create → Done (koi role zaroori nahi).</li>
                    <li>Naye service account par click → <strong>Keys → Add key → Create new key → JSON</strong> → file download ho jayegi.</li>
                    <li>Search Console → aap ki property → <strong>Settings → Users and permissions → Add user</strong> → service account ka email (…@….iam.gserviceaccount.com) → Permission: <em>Restricted</em> → Add.</li>
                    <li><a href="{{ route('admin.marketing.tracking') }}#gsc">Tracking &amp; Pixels → Search Console API</a>: property ka naam (jaise <code>sc-domain:{{ $domain }}</code> ya <code>https://{{ $domain }}/</code>) likhein, JSON file Notepad mein khol kar poora text paste karein → Save.</li>
                    <li><a href="{{ route('admin.marketing.keywords.index') }}">Keywords</a> → <strong>Sync Search Console</strong>. Nayi site ka data aane mein 2–4 hafte lag sakte hain.</li>
                </ol>
                @if ($gscEmail)<div class="alert alert-success" style="margin:0">Connected as {{ $gscEmail }}.</div>@endif
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="position:sticky;top:84px">
            <div class="card-head"><h3>Ad link builder (UTM)</h3></div>
            <div class="card-body" style="display:grid;gap:10px" data-utm>
                <div class="field"><label>Page</label><input class="input" data-utm-url value="{{ url('/') }}"></div>
                <div class="field"><label>Platform (utm_source)</label>
                    <select class="select" data-utm-source>@foreach (['facebook', 'instagram', 'tiktok', 'google', 'youtube', 'whatsapp', 'snapchat', 'influencer'] as $s)<option>{{ $s }}</option>@endforeach</select></div>
                <div class="field"><label>Type (utm_medium)</label>
                    <select class="select" data-utm-medium><option value="paid">paid (ads)</option><option value="social">social (free post)</option><option value="email">email</option><option value="referral">referral</option></select></div>
                <div class="field"><label>Campaign name</label><input class="input" data-utm-campaign value="eid-offer" placeholder="eid-offer"></div>
                <div class="field"><label>Your link</label><textarea class="textarea" data-utm-out readonly style="min-height:70px;font-size:12px;font-family:ui-monospace,monospace"></textarea></div>
                <button type="button" class="btn btn-primary btn-sm" data-utm-copy><x-icon name="copy" /> Copy link</button>
                <p class="muted" style="margin:0;font-size:12px">Use this link in the ad. Traffic → Campaigns then shows visits and orders for each campaign.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    document.querySelectorAll('[data-task]').forEach((box) => box.addEventListener('change', async () => {
        box.closest('.task').classList.toggle('done', box.checked);
        const res = await fetch(@json(route('admin.marketing.seo.checklist')), {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ task: box.dataset.task, done: box.checked }),
        }).then((r) => r.json()).catch(() => null);
        if (res) document.querySelector('[data-task-count]').textContent = res.done + '/{{ $totalTasks }} done';
    }));

    const wrap = document.querySelector('[data-utm]');
    const q = (s) => wrap.querySelector(s);
    const build = () => {
        try {
            const u = new URL(q('[data-utm-url]').value.trim());
            u.searchParams.set('utm_source', q('[data-utm-source]').value);
            u.searchParams.set('utm_medium', q('[data-utm-medium]').value);
            const c = q('[data-utm-campaign]').value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            if (c) u.searchParams.set('utm_campaign', c); else u.searchParams.delete('utm_campaign');
            q('[data-utm-out]').value = u.toString();
        } catch (e) { q('[data-utm-out]').value = 'Enter a full page address starting with https://'; }
    };
    wrap.addEventListener('input', build); wrap.addEventListener('change', build); build();
    q('[data-utm-copy]').addEventListener('click', () => { navigator.clipboard.writeText(q('[data-utm-out]').value); q('[data-utm-copy]').lastChild.textContent = ' Copied!'; });
})();
</script>
@endpush
