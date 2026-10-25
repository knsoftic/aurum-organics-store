{{-- Structured data Google reads for the brand panel, site name and FAQ rich results. --}}
@php
    $storeName = setting('store_name', config('app.name'));
    $sameAs = collect(['facebook', 'instagram', 'tiktok', 'youtube'])->map(fn ($n) => setting('social_'.$n))->filter()->values()->all();
    $organization = array_filter([
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => $storeName,
        'url' => url('/'),
        'logo' => setting('logo') ? media_url(setting('logo')) : (setting('brand_mark') ? media_url(setting('brand_mark')) : null),
        'email' => setting('store_email'),
        'telephone' => setting('store_phone') ?: setting('whatsapp_number'),
        'sameAs' => $sameAs ?: null,
    ]);
    $graph = [$organization, ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => $storeName, 'url' => url('/'), 'publisher' => ['@id' => url('/').'#organization'], 'inLanguage' => 'en-PK']];
    if (request()->routeIs('home') && ($faqs = setting('faqs', []))) {
        $graph[] = ['@type' => 'FAQPage', 'mainEntity' => collect($faqs)->map(fn ($f) => [
            '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ])->values()->all()];
    }
@endphp
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
