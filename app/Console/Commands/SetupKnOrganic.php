<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * One-time brand setup for knorganic.site: KN Organic "Magical Hair Oil".
 * Replaces the demo honey catalogue, branding, theme, pages and FAQ.
 * Orders, customers and admin accounts are never touched.
 */
class SetupKnOrganic extends Command
{
    protected $signature = 'knorganic:setup {--force : Run without asking for confirmation}';

    protected $description = 'Brand the store as KN Organic (Magical Hair Oil): theme, product, photos, videos, pages';

    /** Seeded demo products this command removes (or hides, if they already have orders). */
    protected const DEMO_PRODUCTS = ['royal-sidr-honey', 'wild-forest-honey', 'acacia-blossom-honey', 'black-seed-honey'];

    /** Seeded demo contact values that must never reach real customers. */
    protected const DEMO_SETTINGS = [
        'store_phone' => '+92 300 1234567',
        'whatsapp_number' => '03001234567',
        'store_email' => 'hello@aurumorganics.pk',
        'store_address' => 'Gulberg III, Lahore, Pakistan',
        'notify_email' => 'orders@aurumorganics.pk',
        'social_facebook' => 'https://facebook.com',
        'social_instagram' => 'https://instagram.com',
    ];

    protected const GALLERY = [
        'gift-box.jpg', 'lifestyle-coconut.jpg', 'studio.jpg', 'poster.jpg', 'ingredients.jpg',
        'lifestyle-comb.jpg', 'lifestyle-flatlay.jpg', 'spray.jpg', 'box-details.jpg',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This replaces the store branding, products and pages with KN Organic. Continue?', true)) {
            return self::FAILURE;
        }

        $this->copyAssets();

        DB::transaction(function () {
            $category = $this->category();
            $this->removeDemoProducts();
            $product = $this->product($category);
            $this->settings($product);
            $this->pages();
            $disabled = $this->disableDemoPaymentAccounts();

            $this->newLine();
            $this->info('✔ KN Organic setup complete.');
            $this->line('  Product: '.$product->name.' — '.money($product->price).' (free delivery)');
            if ($disabled) {
                $this->warn('  Disabled until you add your real account details: '.implode(', ', $disabled));
                $this->warn('  → Admin → Payment Methods → edit → enter details → enable.');
            }
            $this->warn('  Set your real WhatsApp, phone and email in Admin → Settings → General.');
        });

        return self::SUCCESS;
    }

    protected function copyAssets(): void
    {
        $from = database_path('seeders/assets/knorganic');
        $to = public_path('uploads/knorganic');

        File::ensureDirectoryExists($to);
        File::copyDirectory($from, $to);
        $this->line('  Copied '.count(File::files($from)).' photos/videos to public/uploads/knorganic');
    }

    protected function category(): Category
    {
        $category = Category::where('slug', 'hair-care')->first()
            ?? Category::where('slug', 'raw-honey')->first()
            ?? new Category();

        $category->fill([
            'name' => 'Hair Care',
            'slug' => 'hair-care',
            'description' => 'Natural hair care by KN Organic — pure by nature, made with care.',
            'is_active' => true,
            'sort_order' => 0,
        ])->save();

        return $category;
    }

    protected function removeDemoProducts(): void
    {
        foreach (Product::whereIn('slug', self::DEMO_PRODUCTS)->get() as $product) {
            $product->reviews()->delete();

            if ($product->orderItems()->exists()) {
                $product->update(['is_active' => false, 'is_featured' => false]);
                $this->line("  Hidden demo product with orders: {$product->name}");
                continue;
            }

            foreach (array_filter([$product->image, ...$product->images->pluck('path')]) as $path) {
                Storage::disk('uploads')->delete($path);
            }
            $product->delete();
            $this->line("  Removed demo product: {$product->name}");
        }
    }

    protected function product(Category $category): Product
    {
        $short = 'A 100% organic hair oil enriched with 14+ natural herbs and oils — nourishes the scalp, strengthens roots, helps reduce hair fall and adds natural shine.';

        $product = Product::updateOrCreate(['slug' => 'magical-hair-oil'], [
            'category_id' => $category->id,
            'name' => 'Magical Hair Oil',
            'sku' => 'KN-MHO-200',
            'size_label' => '200 ml',
            'badge' => 'New',
            'short_description' => $short,
            'highlights' => [
                'Enriched with 14+ natural herbs & oils',
                'Nourishes the scalp and strengthens hair roots',
                'Helps reduce hair fall and supports healthy growth',
                'Adds natural shine and softness',
                'No parabens, sulfates, silicones or mineral oil',
                'Suitable for men & women · all hair types',
            ],
            'description' => '<p>'.$short.'</p>'
                .'<h3>Why you\'ll love it</h3><ul><li>Nourishes the scalp</li><li>Promotes hair growth</li><li>Reduces hair fall</li><li>Adds natural shine</li><li>Strengthens hair roots</li></ul>'
                .'<h3>Key ingredients</h3><p>Coconut Oil, Olive Oil, Castor Oil, Amla, Bhringraj, Hibiscus, Fenugreek, Argan Oil, Almond Oil, Jojoba Oil, Brahmi, Tulsi, Rosemary, Lavender &amp; more.</p>'
                .'<h3>How to use</h3><ol><li>Apply a few pumps directly to your scalp and along the hair.</li><li>Massage gently with your fingertips for 3–5 minutes.</li><li>Leave on for at least 1 hour, or overnight for deeper nourishment.</li><li>Wash with a mild shampoo. Use 2–3 times a week.</li></ol>'
                .'<h3>Caution</h3><p>For external use only. Avoid contact with eyes. Do a small patch test before first use. Keep out of reach of children. Store in a cool, dry place away from direct sunlight.</p>'
                .'<p><strong>Net volume:</strong> 200 ml (6.76 fl. oz.) · For all hair types · Suitable for men &amp; women</p>',
            'price' => 2200,
            'compare_price' => null,
            'stock' => 100,
            'track_stock' => false,
            'image' => 'knorganic/magical-hair-oil.jpg',
            'videos' => collect(range(1, 6))->map(fn ($i) => "knorganic/video-{$i}.mp4")
                ->filter(fn ($path) => File::exists(public_path('uploads/'.$path)))->values()->all(),
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 0,
            'meta_title' => 'Magical Hair Oil 200ml — KN Organic | Natural Hair Oil in Pakistan',
            'meta_description' => 'KN Organic Magical Hair Oil with 14+ natural herbs. Nourishes the scalp, helps reduce hair fall and adds shine. Rs. 2,200 with free delivery and Cash on Delivery all over Pakistan.',
        ]);

        $product->images()->delete();
        foreach (self::GALLERY as $i => $file) {
            $product->images()->create(['path' => 'knorganic/'.$file, 'sort_order' => $i + 1]);
        }

        return $product;
    }

    protected function settings(Product $product): void
    {
        $values = [
            'store_name' => 'KN Organic',
            'tagline' => 'Pure by nature, made with care',
            'theme' => 'lavender',
            'store_mode' => 'single',
            'featured_product_id' => (string) $product->id,
            'logo' => '',
            'brand_mark' => 'knorganic/logo-mark.png',
            'favicon' => 'knorganic/favicon.png',
            'order_prefix' => 'KN',

            'announcement_active' => '1',
            'announcement_text' => 'FREE delivery all over Pakistan  ·  Cash on Delivery available',

            'hero_eyebrow' => '100% Organic Hair Oil · 200 ml',
            'hero_title' => 'Nourished roots, *beautiful you.*',
            'hero_subtitle' => 'Magical Hair Oil blends 14+ natural herbs and oils — coconut, amla, bhringraj, hibiscus, rosemary and more — to nourish your scalp, strengthen your roots and bring back natural shine.',
            'hero_button' => 'Order Now — Rs. 2,200',
            'hero_image' => 'knorganic/hero.jpg',
            'hero_image_style' => 'arch',
            'hero_badge_title' => 'Free Delivery',
            'hero_badge_text' => 'All over Pakistan · Cash on Delivery',
            'hero_stats' => [
                ['value' => '14+', 'label' => 'Herbs & Oils'],
                ['value' => '0%', 'label' => 'Parabens'],
                ['value' => 'Free', 'label' => 'Delivery'],
            ],
            'marquee_items' => implode("\n", [
                'Nourish · Strengthen · Shine',
                '14+ natural herbs',
                'No parabens · No sulfates',
                'For men & women',
                'For all hair types',
                'Free delivery all over Pakistan',
            ]),
            'features' => [
                ['icon' => 'leaf', 'title' => '14+ Natural Herbs', 'text' => 'Coconut, amla, bhringraj, hibiscus, rosemary, argan and more in every bottle.'],
                ['icon' => 'drop', 'title' => 'Chemical Free', 'text' => 'No parabens, sulfates, silicones or mineral oil.'],
                ['icon' => 'user', 'title' => 'For Men & Women', 'text' => 'Gentle, nourishing care for all hair types.'],
                ['icon' => 'truck', 'title' => 'Free Delivery', 'text' => 'Free delivery all over Pakistan with Cash on Delivery.'],
            ],

            'about_eyebrow' => 'Our Story',
            'about_title' => 'Pure by nature, *made with care*',
            'about_text' => "At KN Organic we believe beautiful hair starts with a healthy scalp. Magical Hair Oil brings together the herbs and oils that have been part of South Asian hair care for generations.\n\n"
                ."Every bottle combines coconut, olive, castor, argan, almond and jojoba oils with amla, bhringraj, hibiscus, fenugreek, brahmi, tulsi, rosemary and lavender — and leaves out what your hair doesn't need: no parabens, sulfates, silicones or mineral oil.",
            'about_image' => 'knorganic/gift-box.jpg',

            'video_eyebrow' => 'See it in action',
            'video_title' => 'The *magic* in every drop',

            'shipping_fee' => '0',
            'free_shipping_min' => '0',
            'delivery_time' => '2–4 working days',
            'return_policy_short' => '7-day easy returns on sealed bottles',

            'meta_title' => 'KN Organic — Magical Hair Oil | 100% Natural Hair Oil in Pakistan',
            'meta_description' => 'Magical Hair Oil by KN Organic — enriched with 14+ natural herbs to nourish the scalp, help reduce hair fall and add shine. Rs. 2,200 with free delivery and Cash on Delivery all over Pakistan.',
            'footer_text' => 'Pure by nature, made with care. Natural hair care from KN Organic.',

            'faqs' => [
                ['q' => 'What is Magical Hair Oil?', 'a' => 'A 100% organic hair oil from KN Organic, enriched with 14+ natural herbs and oils including coconut, amla, bhringraj, hibiscus, rosemary and argan. It nourishes the scalp, strengthens roots, helps reduce hair fall and adds natural shine.'],
                ['q' => 'How do I use it?', 'a' => 'Apply a few pumps to your scalp and hair, massage gently for 3–5 minutes and leave it on for at least 1 hour (or overnight). Wash with a mild shampoo. Use 2–3 times a week.'],
                ['q' => 'When will I see results?', 'a' => 'Hair grows slowly, so consistency matters. Use it 2–3 times a week for at least 8–12 weeks. Results vary from person to person.'],
                ['q' => 'Is it suitable for men, women and all hair types?', 'a' => 'Yes. Magical Hair Oil is made for men and women and works for all hair types.'],
                ['q' => 'Does it contain any chemicals?', 'a' => 'No parabens, sulfates, silicones or mineral oil. As with any natural product, do a small patch test first if you have sensitive skin.'],
                ['q' => 'How much is delivery and how long does it take?', 'a' => 'Delivery is FREE all over Pakistan. Orders usually arrive in 2–4 working days, and you can pay with Cash on Delivery.'],
            ],
        ];

        foreach (self::DEMO_SETTINGS as $key => $demo) {
            if (setting($key) === $demo) {
                $values[$key] = '';
            }
        }

        Setting::setMany($values);
    }

    protected function pages(): void
    {
        $pages = [
            'about-us' => ['About Us', '<p>KN Organic was created with one simple belief: healthy, beautiful hair starts with natural care.</p><p>Our Magical Hair Oil combines 14+ herbs and oils that have been trusted in South Asian hair care for generations — coconut, olive, castor, argan, almond and jojoba oils with amla, bhringraj, hibiscus, fenugreek, brahmi, tulsi, rosemary and lavender.</p><h3>Our promise</h3><ul><li>100% organic, natural ingredients</li><li>No parabens, sulfates, silicones or mineral oil</li><li>Suitable for men &amp; women and all hair types</li><li>Free delivery and Cash on Delivery all over Pakistan</li></ul><p><em>Pure by nature, made with care.</em></p>'],
            'shipping-policy' => ['Shipping Policy', '<p>We deliver all over Pakistan through trusted courier partners.</p><ul><li><strong>Delivery is FREE</strong> on every order.</li><li>Orders are dispatched within 24 hours (Mon–Sat).</li><li>Delivery usually takes 2–4 working days depending on your city.</li><li>Cash on Delivery is available everywhere we deliver.</li></ul><p>You will receive your tracking number by SMS/WhatsApp once your parcel is booked.</p>'],
            'return-refund-policy' => ['Return & Refund Policy', '<p>Your satisfaction is our priority.</p><ul><li>Sealed, unused bottles can be returned within 7 days of delivery.</li><li>If a bottle arrives damaged or leaking, send us a photo on WhatsApp within 48 hours and we will replace it free of charge.</li><li>Refunds for advance payments are processed to the original account within 5 working days of receiving the return.</li></ul><p>For hygiene reasons, opened bottles cannot be returned unless they arrived damaged.</p>'],
        ];

        foreach ($pages as $slug => [$title, $content]) {
            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_active' => true, 'show_in_footer' => true]);
        }
    }

    /** Manual accounts still holding the seeded demo numbers are switched off so nobody pays a stranger. */
    protected function disableDemoPaymentAccounts(): array
    {
        $disabled = [];

        foreach (PaymentMethod::where('type', PaymentMethod::TYPE_MANUAL)->get() as $method) {
            $values = collect($method->details ?? [])->pluck('value')->implode(' ');
            $isDemo = str_contains($values, '1234567') || str_contains($values, 'TXyZ1234') || str_contains($values, 'PK00MEZN');

            if ($isDemo && $method->is_active) {
                $method->update(['is_active' => false]);
                $disabled[] = $method->name;
            }
        }

        return $disabled;
    }
}
