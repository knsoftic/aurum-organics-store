@extends('layouts.store')

@section('title', $product->meta_title ?: $product->name)
@section('meta_description', $product->meta_description ?: $product->short_description)
@section('og_image', $product->image_url)

@section('og_type', 'product')

@php
    $gallery = $product->gallery;
    $lowStock = $product->track_stock && $product->stock > 0 && $product->stock <= (int) setting('low_stock_threshold', 5);
    $ratingTotal = max(1, $product->rating_count);
    $trackItem = ['id' => $product->sku ?: $product->id, 'name' => $product->name, 'price' => (float) $product->price, 'qty' => 1];

    // Product rich result: price, stock, delivery, returns and star rating in Google.
    $currency = setting('currency_code', 'PKR');
    $shippingFee = (float) setting('shipping_fee', 0);
    // "2–4 working days" → 2 and 4; "7-day returns" → 7 (taken from Settings, never invented).
    preg_match_all('/\d+/', (string) setting('delivery_time', '2–5 days'), $deliveryDays);
    $transit = array_map('intval', array_slice($deliveryDays[0], 0, 2)) ?: [2, 5];
    $returnDays = preg_match('/(\d+)\s*-?\s*day/i', (string) setting('return_policy_short'), $m) ? (int) $m[1] : null;
    $productSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'image' => array_values(array_unique($gallery)),
        'description' => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($product->description ?: $product->short_description))), 4900),
        'sku' => $product->sku,
        'category' => $product->category?->name,
        'brand' => ['@type' => 'Brand', 'name' => setting('store_name')],
        'offers' => [
            '@type' => 'Offer',
            'url' => route('product.show', $product),
            'priceCurrency' => $currency,
            'price' => number_format((float) $product->price, 2, '.', ''),
            'availability' => $product->isInStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => ['@id' => url('/').'#organization'],
            'shippingDetails' => [
                '@type' => 'OfferShippingDetails',
                'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => $shippingFee, 'currency' => $currency],
                'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'PK'],
                'deliveryTime' => ['@type' => 'ShippingDeliveryTime',
                    'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                    'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => $transit[0], 'maxValue' => $transit[1] ?? $transit[0], 'unitCode' => 'DAY']],
            ],
        ] + ($returnDays ? ['hasMerchantReturnPolicy' => [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'PK',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $returnDays,
        ]] : []),
        'aggregateRating' => $product->rating_count > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => $product->rating, 'reviewCount' => $product->rating_count, 'bestRating' => 5] : null,
        'review' => $reviews->take(5)->map(fn ($r) => [
            '@type' => 'Review',
            'author' => ['@type' => 'Person', 'name' => $r->name],
            'datePublished' => $r->created_at->toDateString(),
            'reviewRating' => ['@type' => 'Rating', 'ratingValue' => $r->rating, 'bestRating' => 5],
            'reviewBody' => \Illuminate\Support\Str::limit($r->comment, 500),
        ])->values()->all() ?: null,
    ]);
    $breadcrumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_values(array_filter([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
        $product->category ? ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => route('shop', ['category' => $product->category->slug])] : null,
        ['@type' => 'ListItem', 'position' => $product->category ? 3 : 2, 'name' => $product->name, 'item' => route('product.show', $product)],
    ]))];
@endphp

@push('preload')<x-preload-img :src="$gallery[0]" sizes="(max-width: 900px) min(620px, 100vw), 50vw" />@endpush

@push('head')
<script type="application/ld+json">{!! json_encode($productSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@push('scripts')
<script>window.knTrack && knTrack('view_item', { value: {{ (float) $product->price }}, items: [@json($trackItem)] });</script>
@endpush

@section('content')
<div class="container">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a><span class="sep">/</span>
        <a href="{{ route('shop') }}">{{ $product->category?->name ?? 'Shop' }}</a><span class="sep">/</span>
        <span>{{ $product->name }}</span>
    </nav>

    <div class="pdp">
        {{-- Gallery --}}
        <div class="gallery" data-gallery>
            @if (count($gallery) > 1)
                <div class="gallery-thumbs">
                    @foreach ($gallery as $i => $url)
                        @php $full = \App\Services\ImageOptimizer::responsive($url); @endphp
                        <button type="button" class="{{ $i === 0 ? 'active' : '' }}" data-gallery-thumb data-src="{{ $full['src'] }}" data-srcset="{{ $full['srcset'] }}" aria-label="Image {{ $i + 1 }}">
                            <img src="{{ media_webp($url, 200) }}" alt="{{ $product->name }} — photo {{ $i + 1 }}" width="84" height="84" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            @endif
            <div class="gallery-main" style="{{ count($gallery) > 1 ? '' : 'grid-column:1/-1' }}">
                @if ($product->badge)<span class="badge">{{ $product->badge }}</span>@endif
                <x-img :src="$gallery[0]" :alt="$product->name" sizes="(max-width: 900px) min(620px, 100vw), 50vw" priority data-gallery-main />
            </div>
        </div>

        {{-- Info --}}
        <div class="pdp-info">
            <span class="cat">{{ $product->category?->name }}{{ $product->size_label ? ' · '.$product->size_label : '' }}</span>
            <h1>{{ $product->name }}</h1>
            @if ($product->rating_count)
                <a href="#reviews" class="pdp-rating"><x-stars :rating="$product->rating" /> {{ number_format($product->rating, 1) }} · {{ $product->rating_count }} {{ \Illuminate\Support\Str::plural('review', $product->rating_count) }}</a>
            @endif

            <div class="pdp-price">
                {{ money($product->price) }}
                @if ($product->discount_percent)
                    <del>{{ money($product->compare_price) }}</del>
                    <span class="save-tag">Save {{ $product->discount_percent }}%</span>
                @endif
            </div>

            @if ($product->short_description)<p class="pdp-desc">{{ $product->short_description }}</p>@endif

            @if ($product->highlights)
                <ul class="highlights">
                    @foreach ($product->highlights as $line)
                        <li><x-icon name="check" /> {{ $line }}</li>
                    @endforeach
                </ul>
            @endif

            @if (! $product->isInStock())
                <div class="stock-line out">Currently out of stock</div>
            @elseif ($lowStock)
                <div class="stock-line low">Only {{ $product->stock }} left — order soon</div>
            @else
                <div class="stock-line">In stock · ready to ship</div>
            @endif

            @if ($product->isInStock())
                <form action="{{ route('cart.add', $product) }}" method="POST" data-ajax-cart>
                    @csrf
                    <div class="buy-row">
                        <div class="qty">
                            <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
                            <input type="number" name="qty" value="1" min="1" max="{{ $product->maxPurchasable() }}" aria-label="Quantity">
                            <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                        </div>
                        <button type="submit" class="btn btn-primary"><x-icon name="bag" /> Add to Bag</button>
                    </div>
                    <button type="submit" name="buy_now" value="1" class="btn btn-gold btn-block">Buy It Now</button>
                </form>
            @endif

            @if ($wa = whatsapp_link('Hi! I would like to order '.$product->name.' ('.money($product->price).').'))
                <a href="{{ $wa }}" target="_blank" rel="noopener" class="wa-order"><x-icon name="whatsapp" style="width:20px" /> Order on WhatsApp</a>
            @endif

            <div class="assurances">
                <div class="assurance"><x-icon name="cash" /><div><strong>Cash on Delivery</strong>All over Pakistan</div></div>
                <div class="assurance"><x-icon name="truck" /><div><strong>Fast Delivery</strong>{{ setting('delivery_time', '2–4 working days') }}</div></div>
                <div class="assurance"><x-icon name="refresh" /><div><strong>Easy Returns</strong>{{ setting('return_policy_short', '7-day returns') }}</div></div>
            </div>

            <div class="accordion">
                @if ($product->description)
                    <details open>
                        <summary><h2 class="acc-title">Description</h2></summary>
                        <div class="content rich">{!! safe_html($product->description) !!}</div>
                    </details>
                @endif
                <details>
                    <summary><h2 class="acc-title">Delivery &amp; Payment</h2></summary>
                    <div class="content">
                        <p>Delivery charges: {{ (float) setting('shipping_fee') > 0 ? money(setting('shipping_fee')) : 'Free' }}{{ setting('free_shipping_min') ? ' · Free delivery on orders above '.money(setting('free_shipping_min')) : '' }}.</p>
                        <p>Estimated delivery: {{ setting('delivery_time', '2–4 working days') }}.</p>
                        <p style="margin:0">Pay with Cash on Delivery, or pay in advance via {{ \App\Models\PaymentMethod::active()->where('type', '!=', 'cod')->pluck('name')->join(', ', ' or ') ?: 'bank transfer' }}.</p>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>

@if ($product->video_urls)
    <section class="section bg-dark video-section" id="videos">
        <div class="container">
            <div class="section-head center">
                <span class="eyebrow center">{{ setting('video_eyebrow', 'See it in action') }}</span>
                <h2 class="section-title">{!! preg_replace('/\*(.+?)\*/', '<em>$1</em>', e(setting('video_title', 'Watch the *difference*'))) !!}</h2>
            </div>
            @include('store.partials.videos', ['urls' => $product->video_urls])
        </div>
    </section>
@endif

{{-- Reviews --}}
<section class="section bg-cream" id="reviews">
    <div class="container reviews-wrap">
        <aside class="rating-summary">
            <span class="eyebrow">Reviews</span>
            <div style="display:flex;align-items:flex-end;gap:12px;margin:16px 0 8px">
                <span class="rating-big">{{ $product->rating_count ? number_format($product->rating, 1) : '—' }}</span>
                <span class="muted" style="padding-bottom:8px">/ 5</span>
            </div>
            <x-stars :rating="$product->rating" />
            <p class="muted" style="font-size:13px;margin:8px 0 18px">Based on {{ $product->rating_count }} {{ \Illuminate\Support\Str::plural('review', $product->rating_count) }}</p>
            @for ($star = 5; $star >= 1; $star--)
                @php $count = (int) ($ratingBreakdown[$star] ?? 0); @endphp
                <div class="bar-row"><span>{{ $star }}★</span><div class="bar"><span style="width: {{ round($count / $ratingTotal * 100) }}%"></span></div><span>{{ $count }}</span></div>
            @endfor
            <a href="#write-review" class="btn btn-outline btn-block btn-sm" style="margin-top:20px">Write a Review</a>
        </aside>

        <div>
            <h2 class="section-title" style="margin-bottom:10px">What customers <em>say</em></h2>
            @forelse ($reviews as $review)
                <article class="review">
                    <div class="review-head">
                        <div>
                            <strong>{{ $review->name }}</strong>
                            @if ($review->city)<span class="muted"> · {{ $review->city }}</span>@endif
                            <div><x-stars :rating="$review->rating" /> <span class="verified">✓ Verified buyer</span></div>
                        </div>
                        <span class="muted" style="font-size:13px">{{ $review->created_at->format('d M Y') }}</span>
                    </div>
                    <p style="margin:0">{{ $review->comment }}</p>
                </article>
            @empty
                <p class="muted" style="padding:20px 0">No reviews yet — be the first to share your experience.</p>
            @endforelse

            <div id="write-review" style="margin-top:50px;background:var(--white);border:1px solid var(--line);padding:34px;border-radius:var(--radius)">
                <h3 style="font-size:28px">Share your experience</h3>
                @if ($errors->any())
                    <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                <form method="POST" action="{{ route('reviews.store', $product) }}" class="form-grid">
                    @csrf
                    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-label="Leave this field empty">
                    <div class="field full">
                        <label>Your rating</label>
                        <div class="star-input">
                            @for ($s = 5; $s >= 1; $s--)
                                <input type="radio" name="rating" id="rate{{ $s }}" value="{{ $s }}" @checked(old('rating', 5) == $s)>
                                <label for="rate{{ $s }}"><x-icon name="star" /><span class="sr-only">{{ $s }} stars</span></label>
                            @endfor
                        </div>
                    </div>
                    <div class="field"><label for="r-name">Name</label><input id="r-name" class="input" name="name" value="{{ old('name') }}" required maxlength="80"></div>
                    <div class="field"><label for="r-city">City <span class="opt">(optional)</span></label><input id="r-city" class="input" name="city" value="{{ old('city') }}" maxlength="60"></div>
                    <div class="field full"><label for="r-comment">Review</label><textarea id="r-comment" class="textarea" name="comment" required minlength="10" maxlength="1500">{{ old('comment') }}</textarea></div>
                    <div class="full"><button class="btn btn-primary" type="submit">Submit Review</button></div>
                </form>
            </div>
        </div>
    </div>
</section>

@if ($related->isNotEmpty())
    <section class="section">
        <div class="container">
            <div class="section-head center">
                <span class="eyebrow center">You may also like</span>
                <h2 class="section-title">Complete the <em>ritual</em></h2>
            </div>
            <div class="product-grid {{ $related->count() <= 3 ? 'cols-3' : '' }}">
                @foreach ($related as $item)
                    @include('store.partials.product-card', ['product' => $item])
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection
