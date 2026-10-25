@extends('layouts.admin')

@section('title', 'Settings')

@php
    $v = fn ($key, $default = null) => old($key, setting($key, $default));
    $faqRows = old('faqs', $faqs);
@endphp

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <input type="hidden" name="tab" value="general">

    <div class="tabs" data-tabs>
        <button type="button" data-tab="general">General</button>
        <button type="button" data-tab="storefront">Storefront</button>
        <button type="button" data-tab="checkout">Checkout &amp; Delivery</button>
        <button type="button" data-tab="seo">SEO &amp; Social</button>
    </div>

    {{-- GENERAL --}}
    <div class="tab-panel" data-tab-panel="general">
        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h3>Store</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Store name</label><input class="input" name="store_name" value="{{ $v('store_name') }}" required></div>
                    <div class="field"><label>Currency symbol</label><input class="input" name="currency_symbol" value="{{ $v('currency_symbol', 'Rs.') }}" required></div>
                    <div class="field full"><label>Tagline</label><input class="input" name="tagline" value="{{ $v('tagline') }}"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Contact</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Phone</label><input class="input" name="store_phone" value="{{ $v('store_phone') }}"></div>
                    <div class="field"><label>WhatsApp number</label><input class="input" name="whatsapp_number" value="{{ $v('whatsapp_number') }}" placeholder="03001234567"></div>
                    <div class="field"><label>Email</label><input class="input" type="email" name="store_email" value="{{ $v('store_email') }}"></div>
                    <div class="field"><label>New-order alerts to</label><input class="input" type="email" name="notify_email" value="{{ $v('notify_email') }}"><div class="hint">Admin email notified on every new order.</div></div>
                    <div class="field full"><label>Address</label><input class="input" name="store_address" value="{{ $v('store_address') }}"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Branding</h3></div>
                <div class="card-body" style="display:grid;gap:16px">
                    @foreach ([['logo', 'Logo', 'Leave empty to use the elegant text logo.'], ['favicon', 'Favicon', 'Square PNG, 64×64 or larger.']] as [$key, $label, $hint])
                        <div class="field">
                            <label>{{ $label }}</label>
                            <div class="upload-box">
                                <img id="prev-{{ $key }}" src="{{ setting($key) ? media_url(setting($key)) : asset('images/placeholder.svg') }}" alt="">
                                <div>
                                    <input type="file" name="{{ $key }}" accept="image/*" data-preview="#prev-{{ $key }}">
                                    <div class="hint">{{ $hint }}</div>
                                    @if (setting($key))<label class="switch" style="margin-top:6px;font-weight:400"><input type="checkbox" name="remove_{{ $key }}" value="1"><span class="track"></span> Remove</label>@endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- STOREFRONT --}}
    <div class="tab-panel" data-tab-panel="storefront">
        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h3>Store mode &amp; theme</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Store mode</label>
                        <select class="select" name="store_mode">
                            <option value="multi" @selected($v('store_mode') === 'multi')>Multi product — collection grid on the home page</option>
                            <option value="single" @selected($v('store_mode') === 'single')>Single product — home page spotlights one product</option>
                        </select>
                    </div>
                    <div class="field full"><label>Featured / hero product</label>
                        <select class="select" name="featured_product_id">
                            <option value="">Automatic (first featured product)</option>
                            @foreach ($products as $p)<option value="{{ $p->id }}" @selected((string) $v('featured_product_id') === (string) $p->id)>{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field full"><label>Colour theme</label>
                        <select class="select" name="theme">
                            @foreach (['emerald' => 'Emerald & Gold', 'noir' => 'Noir & Gold', 'ivory' => 'Ivory & Bronze', 'rose' => 'Rosewood & Champagne'] as $key => $label)
                                <option value="{{ $key }}" @selected($v('theme', 'emerald') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field full">
                        <label class="switch"><input type="checkbox" name="announcement_active" value="1" @checked($v('announcement_active'))><span class="track"></span> Show announcement bar</label>
                        <input class="input" name="announcement_text" value="{{ $v('announcement_text') }}" style="margin-top:10px" placeholder="Free delivery on orders above…">
                    </div>
                    <div class="field full"><label class="switch"><input type="checkbox" name="show_whatsapp_button" value="1" @checked($v('show_whatsapp_button', '1'))><span class="track"></span> Floating WhatsApp button</label></div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Hero section</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Small heading</label><input class="input" name="hero_eyebrow" value="{{ $v('hero_eyebrow') }}"></div>
                    <div class="field full"><label>Main heading</label><input class="input" name="hero_title" value="{{ $v('hero_title') }}"><div class="hint">Wrap words in *stars* to show them in gold italics, e.g. *Liquid gold*, harvested.</div></div>
                    <div class="field full"><label>Text</label><textarea class="textarea" name="hero_subtitle" style="min-height:80px">{{ $v('hero_subtitle') }}</textarea></div>
                    <div class="field"><label>Button text</label><input class="input" name="hero_button" value="{{ $v('hero_button') }}"></div>
                    <div class="field full">
                        <label>Hero image</label>
                        <div class="upload-box">
                            <img id="prev-hero" src="{{ setting('hero_image') ? media_url(setting('hero_image')) : asset('images/placeholder.svg') }}" alt="">
                            <div>
                                <input type="file" name="hero_image" accept="image/*" data-preview="#prev-hero">
                                <div class="hint">Transparent PNG or tall image works best. Empty = featured product image.</div>
                                @if (setting('hero_image'))<label class="switch" style="margin-top:6px;font-weight:400"><input type="checkbox" name="remove_hero_image" value="1"><span class="track"></span> Remove</label>@endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>Our story section</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Small heading</label><input class="input" name="about_eyebrow" value="{{ $v('about_eyebrow') }}"></div>
                    <div class="field"><label>Heading</label><input class="input" name="about_title" value="{{ $v('about_title') }}"></div>
                    <div class="field full"><label>Text</label><textarea class="textarea" name="about_text" style="min-height:140px">{{ $v('about_text') }}</textarea><div class="hint">Leave a blank line between paragraphs.</div></div>
                    <div class="field full">
                        <label>Image</label>
                        <div class="upload-box">
                            <img id="prev-about" src="{{ setting('about_image') ? media_url(setting('about_image')) : asset('images/placeholder.svg') }}" alt="">
                            <div>
                                <input type="file" name="about_image" accept="image/*" data-preview="#prev-about">
                                @if (setting('about_image'))<label class="switch" style="margin-top:6px;font-weight:400"><input type="checkbox" name="remove_about_image" value="1"><span class="track"></span> Remove</label>@endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>FAQ</h3><button type="button" class="btn btn-light btn-sm" data-add-row="#faqRows"><x-icon name="plus" /> Add question</button></div>
                <div class="card-body">
                    <div id="faqRows" data-repeater data-next="{{ count($faqRows) + 1 }}">
                        <div data-rows>
                            @foreach ($faqRows as $i => $faq)
                                <div class="repeater-row faq" data-row>
                                    <div style="display:grid;gap:6px">
                                        <input class="input" name="faqs[{{ $i }}][q]" value="{{ $faq['q'] ?? '' }}" placeholder="Question">
                                        <textarea class="textarea" name="faqs[{{ $i }}][a]" style="min-height:60px" placeholder="Answer">{{ $faq['a'] ?? '' }}</textarea>
                                    </div>
                                    <button type="button" class="icon-action danger" data-remove-row title="Remove"><x-icon name="trash" /></button>
                                </div>
                            @endforeach
                        </div>
                        <template>
                            <div class="repeater-row faq" data-row>
                                <div style="display:grid;gap:6px">
                                    <input class="input" name="faqs[__INDEX__][q]" placeholder="Question">
                                    <textarea class="textarea" name="faqs[__INDEX__][a]" style="min-height:60px" placeholder="Answer"></textarea>
                                </div>
                                <button type="button" class="icon-action danger" data-remove-row title="Remove"><x-icon name="trash" /></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CHECKOUT --}}
    <div class="tab-panel" data-tab-panel="checkout">
        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h3>Delivery charges</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Flat delivery fee</label><input class="input" type="number" step="0.01" min="0" name="shipping_fee" value="{{ $v('shipping_fee', 0) }}" required></div>
                    <div class="field"><label>Free delivery above</label><input class="input" type="number" step="0.01" min="0" name="free_shipping_min" value="{{ $v('free_shipping_min') }}"><div class="hint">Leave empty or 0 to disable.</div></div>
                    <div class="field"><label>Delivery time text</label><input class="input" name="delivery_time" value="{{ $v('delivery_time') }}" placeholder="2–4 working days"></div>
                    <div class="field"><label>Returns text</label><input class="input" name="return_policy_short" value="{{ $v('return_policy_short') }}"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Orders</h3></div>
                <div class="card-body form-grid">
                    <div class="field"><label>Order number prefix</label><input class="input" name="order_prefix" value="{{ $v('order_prefix', 'AO') }}" maxlength="6" required></div>
                    <div class="field"><label>Minimum order amount</label><input class="input" type="number" step="0.01" min="0" name="min_order_amount" value="{{ $v('min_order_amount') }}"></div>
                    <div class="field"><label>Low-stock alert at</label><input class="input" type="number" min="0" name="low_stock_threshold" value="{{ $v('low_stock_threshold', 5) }}"></div>
                    <div class="field" style="align-self:end"><label class="switch"><input type="checkbox" name="auto_approve_reviews" value="1" @checked($v('auto_approve_reviews'))><span class="track"></span> Auto-publish reviews</label></div>
                    <div class="field full"><label>Note shown at checkout</label><input class="input" name="checkout_note" value="{{ $v('checkout_note') }}"></div>
                </div>
            </div>
            <div class="card card-body" style="grid-column:1/-1">
                <div class="flex between wrap">
                    <span>Payment options (COD, EasyPaisa, JazzCash, bank, USDT, online gateways) are managed separately.</span>
                    <a href="{{ route('admin.payment-methods.index') }}" class="btn btn-light btn-sm"><x-icon name="card" /> Payment Methods</a>
                </div>
            </div>
        </div>
    </div>

    {{-- SEO --}}
    <div class="tab-panel" data-tab-panel="seo">
        <div class="grid grid-2">
            <div class="card">
                <div class="card-head"><h3>Search engines</h3></div>
                <div class="card-body form-grid">
                    <div class="field full"><label>Home page title</label><input class="input" name="meta_title" value="{{ $v('meta_title') }}"></div>
                    <div class="field full"><label>Meta description</label><textarea class="textarea" name="meta_description" style="min-height:80px">{{ $v('meta_description') }}</textarea></div>
                    <div class="field full"><label>Footer text</label><input class="input" name="footer_text" value="{{ $v('footer_text') }}"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3>Social links</h3></div>
                <div class="card-body form-grid" style="grid-template-columns:1fr">
                    @foreach (['facebook', 'instagram', 'tiktok', 'youtube'] as $network)
                        <div class="field"><label>{{ ucfirst($network) }}</label><input class="input" type="url" name="social_{{ $network }}" value="{{ $v('social_'.$network) }}" placeholder="https://"></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card sticky-actions" style="margin-top:20px">
        <div class="form-actions" style="border-top:0">
            <a href="{{ route('home') }}" target="_blank" class="btn btn-light"><x-icon name="eye" /> Preview store</a>
            <button class="btn btn-primary">Save settings</button>
        </div>
    </div>
</form>
@endsection
