@extends('layouts.store')

@php
    // *word* in admin-entered headings renders as gold italic.
    $fancy = fn ($text) => preg_replace('/\*(.+?)\*/', '<em>$1</em>', e($text));
    $heroImage = setting('hero_image') ? media_url(setting('hero_image')) : $hero?->image_url;
    $reviewCount = \App\Models\Review::where('is_approved', true)->count();
@endphp

@section('content')

{{-- ================= HERO ================= --}}
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy reveal">
            <span class="eyebrow">{{ setting('hero_eyebrow', 'Pure · Natural · Organic') }}</span>
            <h1 class="display">{!! $fancy(setting('hero_title', 'Nature, bottled with care.')) !!}</h1>
            <p class="lead">{{ setting('hero_subtitle') }}</p>
            <div class="hero-actions">
                <a href="{{ $singleMode && $hero ? route('product.show', $hero) : route('shop') }}" class="btn btn-primary">
                    {{ setting('hero_button', 'Shop Now') }} <x-icon name="arrow-right" />
                </a>
                <a href="#story" class="link-underline">Our Story</a>
            </div>
            <div class="hero-stats">
                <div><strong>100%</strong><span>Raw &amp; Pure</span></div>
                <div><strong>Lab</strong><span>Tested Batches</span></div>
                <div><strong>COD</strong><span>All Pakistan</span></div>
            </div>
        </div>
        <div class="hero-visual reveal reveal-delay-2">
            @if ($heroImage)
                <img src="{{ $heroImage }}" alt="{{ setting('store_name') }}">
            @endif
            <div class="hero-badge">
                <div class="seal"><x-icon name="award" style="width:22px" /></div>
                <div>
                    <strong>Certified Pure</strong>
                    <small>Every batch tested for purity &amp; quality</small>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ================= MARQUEE ================= --}}
<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        @for ($i = 0; $i < 2; $i++)
            <span>Raw &amp; unfiltered</span><span>Cold extracted</span><span>Single origin</span><span>Lab tested</span><span>Bottled in glass</span><span>Cash on delivery</span>
        @endfor
    </div>
</div>

{{-- ================= COLLECTION / SPOTLIGHT ================= --}}
@if ($singleMode && $hero)
    <section class="section">
        <div class="container spotlight">
            <div class="spotlight-media reveal">
                <img src="{{ $hero->gallery[1] ?? $hero->image_url }}" alt="{{ $hero->name }}">
            </div>
            <div class="reveal reveal-delay-1">
                <span class="eyebrow">{{ $category?->name ?? 'Signature' }}</span>
                <h2 class="section-title" style="margin:16px 0 14px">{{ $hero->name }}</h2>
                @if ($hero->rating_count)
                    <div class="pdp-rating"><x-stars :rating="$hero->rating" /> {{ number_format($hero->rating, 1) }} · {{ $hero->rating_count }} reviews</div>
                @endif
                <div class="pdp-price">
                    {{ money($hero->price) }}
                    @if ($hero->discount_percent)<del>{{ money($hero->compare_price) }}</del><span class="save-tag">Save {{ $hero->discount_percent }}%</span>@endif
                </div>
                <p class="pdp-desc">{{ $hero->short_description }}</p>
                @if ($hero->highlights)
                    <ul class="highlights">
                        @foreach ($hero->highlights as $line)<li><x-icon name="check" /> {{ $line }}</li>@endforeach
                    </ul>
                @endif
                <form action="{{ route('cart.add', $hero) }}" method="POST" class="hero-actions">
                    @csrf
                    <input type="hidden" name="qty" value="1">
                    <button type="submit" name="buy_now" value="1" class="btn btn-gold">Order Now — Cash on Delivery</button>
                    <a href="{{ route('product.show', $hero) }}" class="link-underline">View Details</a>
                </form>
            </div>
        </div>
    </section>
@else
    <section class="section" id="collection">
        <div class="container">
            <div class="section-head reveal">
                <div>
                    <span class="eyebrow">{{ $category?->name ?? 'The Collection' }}</span>
                    <h2 class="section-title">The <em>Collection</em></h2>
                </div>
                <p class="muted" style="max-width:420px;margin:0">{{ $category?->description ?? 'Small-batch harvests, bottled with care.' }}</p>
            </div>
            <div class="product-grid">
                @foreach ($products->take(8) as $i => $product)
                    <div class="reveal reveal-delay-{{ $i % 4 }}">@include('store.partials.product-card', ['product' => $product])</div>
                @endforeach
            </div>
            @if ($products->count() > 8)
                <div class="text-center" style="margin-top:60px"><a href="{{ route('shop') }}" class="btn btn-outline">View All Products</a></div>
            @endif
        </div>
    </section>
@endif

{{-- ================= STORY ================= --}}
<section class="section bg-cream" id="story">
    <div class="container story">
        <div class="story-media reveal">
            <img src="{{ setting('about_image') ? media_url(setting('about_image')) : ($hero?->image_url ?? media_url(null)) }}" alt="Our story">
        </div>
        <div class="story-copy reveal reveal-delay-1">
            <span class="eyebrow">{{ setting('about_eyebrow', 'Our Story') }}</span>
            <h2 class="section-title" style="margin:18px 0 26px">{!! $fancy(setting('about_title', 'Crafted by nature')) !!}</h2>
            @foreach (preg_split('/\n\s*\n/', (string) setting('about_text')) as $paragraph)
                @if (trim($paragraph))<p>{{ trim($paragraph) }}</p>@endif
            @endforeach
            <div class="signature">— {{ setting('store_name') }}</div>
        </div>
    </div>
</section>

{{-- ================= FEATURES ================= --}}
<section class="bg-dark">
    <div class="container">
        <div class="features">
            @foreach ([
                ['leaf', '100% Natural', 'No sugar syrups, no additives, no preservatives — ever.'],
                ['flask', 'Lab Tested', 'Every batch is tested for purity before it is bottled.'],
                ['truck', 'Fast Delivery', 'Dispatched in 24 hours, delivered in '.setting('delivery_time', '2–4 days').'.'],
                ['shield', 'Pay Your Way', 'Cash on Delivery, wallets, bank transfer or crypto.'],
            ] as $i => [$icon, $title, $text])
                <div class="feature reveal reveal-delay-{{ $i }}">
                    <div class="ico"><x-icon :name="$icon" /></div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ================= TESTIMONIALS ================= --}}
@if ($reviews->isNotEmpty())
    <section class="section">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow center">Kind Words</span>
                <h2 class="section-title">Loved by <em>{{ $reviewCount > 50 ? number_format($reviewCount).'+' : 'families' }}</em> across Pakistan</h2>
            </div>
            <div class="testimonials">
                @foreach ($reviews->take(3) as $i => $review)
                    <figure class="testimonial reveal reveal-delay-{{ $i }}" style="margin:0">
                        <x-stars :rating="$review->rating" />
                        <blockquote>“{{ \Illuminate\Support\Str::limit($review->comment, 220) }}”</blockquote>
                        <figcaption class="who">
                            <span class="avatar">{{ mb_substr($review->name, 0, 1) }}</span>
                            <span>
                                <strong>{{ $review->name }}</strong>
                                <small>{{ $review->city ? $review->city.' · ' : '' }}{{ $review->product?->name }}</small>
                            </span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ================= PAYMENTS ================= --}}
@if ($paymentMethods->isNotEmpty())
    <section class="section-sm bg-cream">
        <div class="container">
            <div class="section-head center reveal" style="margin-bottom:36px">
                <span class="eyebrow center">Secure Checkout</span>
                <h2 class="section-title">Pay the way <em>you prefer</em></h2>
            </div>
            <div class="pay-strip reveal">
                @foreach ($paymentMethods as $method)
                    <span class="pay-chip"><img src="{{ $method->logo_url }}" alt="">{{ $method->name }}
                        @if ((float) $method->discount_percent > 0)<small class="gold">· Save {{ rtrim(rtrim($method->discount_percent, '0'), '.') }}%</small>@endif
                    </span>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ================= FAQ ================= --}}
@if ($faqs = setting('faqs'))
    <section class="section" id="faq">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow center">Questions</span>
                <h2 class="section-title">Frequently <em>asked</em></h2>
            </div>
            <div class="faq reveal">
                @foreach ($faqs as $i => $faq)
                    <details @if ($i === 0) open @endif>
                        <summary>{{ $faq['q'] }} <span class="plus"></span></summary>
                        <div class="answer">{{ $faq['a'] }}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ================= CTA ================= --}}
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="cta-band reveal">
            <div>
                <span class="eyebrow">Order with confidence</span>
                <h2>Delivered to your door. Pay when it arrives.</h2>
                <p>Cash on Delivery across Pakistan{{ setting('free_shipping_min') ? ' · Free delivery above '.money(setting('free_shipping_min')) : '' }}.</p>
            </div>
            <div class="actions">
                <a href="{{ route('shop') }}" class="btn btn-gold">Shop Now <x-icon name="arrow-right" /></a>
                @if ($wa = whatsapp_link('Hi! I would like to place an order.'))
                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-light"><x-icon name="whatsapp" /> WhatsApp</a>
                @endif
            </div>
        </div>
    </div>
</section>

@endsection
