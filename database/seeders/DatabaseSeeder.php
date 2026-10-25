<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->copyAssets();

        User::updateOrCreate(['email' => config('app.admin_email')], [
            'name' => 'Store Admin',
            'password' => config('app.admin_password'),
            'is_admin' => true,
        ]);

        $this->seedSettings();
        $this->seedCatalog();
        $this->seedPaymentMethods();
        $this->seedPages();

        Coupon::updateOrCreate(['code' => 'WELCOME10'], ['type' => 'percent', 'value' => 10, 'min_order' => 2000, 'is_active' => true]);
    }

    protected function copyAssets(): void
    {
        foreach (['products', 'branding'] as $folder) {
            File::ensureDirectoryExists(public_path("uploads/$folder"));
            File::copyDirectory(database_path("seeders/assets/$folder"), public_path("uploads/$folder"));
        }
    }

    protected function seedSettings(): void
    {
        Setting::setMany([
            'store_name' => 'Aurum Organics',
            'tagline' => 'Pure, raw honey — straight from the hive',
            'store_email' => 'hello@aurumorganics.pk',
            'store_phone' => '+92 300 1234567',
            'whatsapp_number' => '03001234567',
            'store_address' => 'Gulberg III, Lahore, Pakistan',
            'currency_symbol' => 'Rs.',
            'notify_email' => 'orders@aurumorganics.pk',
            'store_mode' => 'multi',
            'featured_product_id' => '',
            'theme' => 'emerald',
            'announcement_active' => '1',
            'announcement_text' => 'Free delivery across Pakistan on orders above Rs. 5,000  ·  Cash on Delivery available',
            'hero_eyebrow' => 'Raw · Unfiltered · Lab tested',
            'hero_title' => '*Liquid gold*, harvested with patience.',
            'hero_subtitle' => 'Single-origin honey from the wild Sidr forests and mountain valleys of Pakistan — never heated, never blended, bottled exactly as the bees intended.',
            'hero_button' => 'Shop the Collection',
            'hero_image' => 'branding/hero.svg',
            'about_eyebrow' => 'Our Story',
            'about_title' => 'From ancient forests to *your table*',
            'about_text' => "We partner with small beekeeping families who move their hives with the blossoms — from the Sidr groves of Karak to the wildflower valleys of Swat.\n\nEvery batch is cold-extracted, lab tested for purity and bottled in glass, so what reaches you is honey in its most honest form: thick, fragrant and alive.",
            'about_image' => 'branding/about.svg',
            'shipping_fee' => '250',
            'free_shipping_min' => '5000',
            'delivery_time' => '2–4 working days',
            'min_order_amount' => '0',
            'order_prefix' => 'AO',
            'low_stock_threshold' => '5',
            'auto_approve_reviews' => '0',
            'show_whatsapp_button' => '1',
            'checkout_note' => 'Our team will call you to confirm your order before dispatch.',
            'return_policy_short' => '7-day easy returns on sealed jars',
            'meta_title' => 'Aurum Organics — Pure Raw Honey in Pakistan',
            'meta_description' => 'Shop 100% pure, raw, lab-tested Sidr, Acacia, Wild Forest and Black Seed honey. Cash on Delivery across Pakistan.',
            'social_facebook' => 'https://facebook.com',
            'social_instagram' => 'https://instagram.com',
            'social_tiktok' => '',
            'social_youtube' => '',
            'footer_text' => 'Pure honey, sourced responsibly from Pakistani beekeepers.',
            'faqs' => [
                ['q' => 'Is your honey really 100% pure?', 'a' => 'Yes. Every batch is raw, unheated and unblended, and is lab tested for moisture, sugar adulteration and HMF before bottling.'],
                ['q' => 'Why has my honey crystallised?', 'a' => 'Crystallisation is a natural sign of raw honey. Place the jar in warm (not hot) water for a few minutes and it will return to liquid.'],
                ['q' => 'Do you offer Cash on Delivery?', 'a' => 'Yes, Cash on Delivery is available across Pakistan. You can also pay in advance via EasyPaisa, JazzCash, bank transfer or USDT and save 5%.'],
                ['q' => 'How long does delivery take?', 'a' => 'Orders are dispatched within 24 hours and usually arrive in 2–4 working days depending on your city.'],
                ['q' => 'Can I return a product?', 'a' => 'Sealed jars can be returned within 7 days of delivery. If anything arrives damaged, message us on WhatsApp with a photo and we will replace it free of charge.'],
            ],
        ]);
    }

    protected function seedCatalog(): void
    {
        $category = Category::updateOrCreate(['slug' => 'raw-honey'], [
            'name' => 'Raw Organic Honey',
            'description' => 'Single-origin, cold-extracted honey from Pakistan\'s finest forests and valleys.',
            'is_active' => true,
        ]);

        $products = [
            [
                'slug' => 'royal-sidr-honey', 'name' => 'Royal Sidr Honey', 'sku' => 'AO-SIDR-500', 'badge' => 'Bestseller',
                'price' => 3450, 'compare_price' => 4200, 'stock' => 120, 'is_featured' => true,
                'short_description' => 'The crown jewel of honeys — rich, buttery Sidr (Beri) honey from the wild forests of Karak.',
                'highlights' => ['Single-origin Beri blossom from Karak', 'Cold extracted, never heated', 'Lab tested for purity', 'Thick, buttery texture with caramel notes'],
            ],
            [
                'slug' => 'wild-forest-honey', 'name' => 'Wild Forest Honey', 'sku' => 'AO-WILD-500', 'badge' => null,
                'price' => 2250, 'compare_price' => 2600, 'stock' => 80, 'is_featured' => false,
                'short_description' => 'Deep amber multi-flora honey gathered from the untouched mountain forests of Swat.',
                'highlights' => ['Multi-flora mountain blossoms', 'Rich in natural antioxidants', 'Bold, earthy flavour', 'Perfect for daily wellness'],
            ],
            [
                'slug' => 'acacia-blossom-honey', 'name' => 'Acacia Blossom Honey', 'sku' => 'AO-ACAC-500', 'badge' => 'New',
                'price' => 2650, 'compare_price' => null, 'stock' => 60, 'is_featured' => false,
                'short_description' => 'Light, floral and delicately sweet — the honey that stays liquid the longest.',
                'highlights' => ['Light golden colour', 'Mild floral sweetness', 'Slow to crystallise', 'Ideal for tea and desserts'],
            ],
            [
                'slug' => 'black-seed-honey', 'name' => 'Black Seed Honey', 'sku' => 'AO-KALO-500', 'badge' => 'Limited',
                'price' => 2950, 'compare_price' => 3300, 'stock' => 40, 'is_featured' => false,
                'short_description' => 'Raw honey from Kalonji (Nigella sativa) blossoms — treasured for centuries in traditional remedies.',
                'highlights' => ['Kalonji blossom nectar', 'Distinctive herbal finish', 'Traditional immunity booster', 'Small-batch harvest'],
            ],
        ];

        foreach ($products as $i => $data) {
            $product = Product::updateOrCreate(['slug' => $data['slug']], $data + [
                'category_id' => $category->id,
                'size_label' => '500 g',
                'image' => 'products/'.$data['slug'].'.svg',
                'track_stock' => true,
                'is_active' => true,
                'sort_order' => $i,
                'description' => '<p>'.$data['short_description'].'</p><p>Our beekeepers harvest only capped, fully ripened combs. The honey is extracted by gravity at room temperature, strained through fine mesh to remove wax — never micro-filtered — and poured straight into glass. Pollen, enzymes and aroma stay exactly where nature put them.</p><h3>How to enjoy</h3><ul><li>A spoonful on an empty stomach each morning</li><li>Stirred into warm (not boiling) water with lemon</li><li>Drizzled over yoghurt, paratha or fresh fruit</li></ul><h3>Storage</h3><p>Keep tightly closed at room temperature, away from direct sunlight. Natural crystallisation is a sign of purity.</p>',
                'meta_title' => $data['name'].' — 500 g | Aurum Organics',
                'meta_description' => $data['short_description'],
            ]);

            $product->images()->delete();
            $product->images()->create(['path' => 'products/'.$data['slug'].'-noir.svg', 'sort_order' => 1]);
        }

        $reviews = [
            ['royal-sidr-honey', 'Ayesha Khan', 'Lahore', 5, 'Hands down the best Sidr honey I have bought in Pakistan. Thick, aromatic and you can taste that it is real. The packaging felt like a luxury gift.'],
            ['royal-sidr-honey', 'Usman Tariq', 'Islamabad', 5, 'Ordered with Cash on Delivery, got a confirmation call the same day and the parcel arrived in two days. Will definitely order again.'],
            ['wild-forest-honey', 'Fatima Raza', 'Karachi', 5, 'Beautiful dark honey with a deep flavour. My family has replaced sugar in our tea with this one.'],
            ['acacia-blossom-honey', 'Hamza Ali', 'Faisalabad', 4, 'Very light and floral, my kids love it. Paid through EasyPaisa and got 5% off which was a nice bonus.'],
            ['black-seed-honey', 'Sana Malik', 'Rawalpindi', 5, 'Exactly what my mother asked for — authentic Kalonji honey. You can smell the difference as soon as you open the jar.'],
            ['royal-sidr-honey', 'Bilal Ahmed', 'Multan', 5, 'Bought it as a gift for my parents. Premium jar, premium taste. Worth every rupee.'],
        ];
        Review::query()->delete();
        foreach ($reviews as [$slug, $name, $city, $rating, $comment]) {
            Review::create([
                'product_id' => Product::where('slug', $slug)->value('id'),
                'name' => $name, 'city' => $city, 'rating' => $rating, 'comment' => $comment, 'is_approved' => true,
            ]);
        }
    }

    protected function seedPaymentMethods(): void
    {
        $methods = [
            [
                'code' => 'cod', 'name' => 'Cash on Delivery', 'type' => 'cod',
                'description' => 'Pay in cash when your order arrives at your doorstep.',
                'instructions' => 'Please keep the exact amount ready. Our rider will hand over your parcel on payment.',
                'require_reference' => false, 'require_proof' => false, 'discount_percent' => 0,
            ],
            [
                'code' => 'easypaisa', 'name' => 'EasyPaisa', 'type' => 'manual',
                'description' => 'Send to our EasyPaisa account and save 5%.',
                'instructions' => 'Send the total amount to the EasyPaisa account below, then enter the Transaction ID (TID) and upload a screenshot of the payment.',
                'details' => [['label' => 'Account Title', 'value' => 'Aurum Organics'], ['label' => 'EasyPaisa Number', 'value' => '0345 1234567']],
                'require_reference' => true, 'require_proof' => true, 'discount_percent' => 5,
            ],
            [
                'code' => 'jazzcash', 'name' => 'JazzCash', 'type' => 'manual',
                'description' => 'Send to our JazzCash account and save 5%.',
                'instructions' => 'Send the total amount to the JazzCash account below, then enter the Transaction ID (TID) and upload a screenshot of the payment.',
                'details' => [['label' => 'Account Title', 'value' => 'Aurum Organics'], ['label' => 'JazzCash Number', 'value' => '0300 1234567']],
                'require_reference' => true, 'require_proof' => true, 'discount_percent' => 5,
            ],
            [
                'code' => 'bank_transfer', 'name' => 'Bank Transfer', 'type' => 'manual',
                'description' => 'Direct bank transfer / IBFT — save 5%.',
                'instructions' => 'Transfer the total amount to the bank account below via IBFT or Raast, then share the transaction reference and a screenshot.',
                'details' => [
                    ['label' => 'Bank', 'value' => 'Meezan Bank'],
                    ['label' => 'Account Title', 'value' => 'Aurum Organics'],
                    ['label' => 'Account Number', 'value' => '0123 0101234567'],
                    ['label' => 'IBAN', 'value' => 'PK00MEZN0001230101234567'],
                ],
                'require_reference' => true, 'require_proof' => true, 'discount_percent' => 5,
            ],
            [
                'code' => 'usdt_trc20', 'name' => 'USDT (TRC20)', 'type' => 'manual',
                'description' => 'Pay with crypto on the TRON network — save 5%.',
                'instructions' => 'Send the equivalent amount in USDT on the TRC20 (TRON) network only. Message us on WhatsApp for today\'s rate. Paste the transaction hash (TXID) below and upload a screenshot.',
                'details' => [['label' => 'Network', 'value' => 'TRON (TRC20)'], ['label' => 'Wallet Address', 'value' => 'TXyZ1234AbCdEfGh5678IjKlMnOpQrStUv']],
                'require_reference' => true, 'require_proof' => true, 'discount_percent' => 5,
            ],
            [
                'code' => 'online_card', 'name' => 'Debit / Credit Card', 'type' => 'gateway', 'driver' => 'sandbox',
                'description' => 'Pay securely online (test gateway — replace with a live driver).',
                'instructions' => 'You will be redirected to a secure payment page.',
                'require_reference' => false, 'require_proof' => false, 'discount_percent' => 0, 'is_active' => false,
                'config' => ['merchant_name' => 'Aurum Organics'],
            ],
        ];

        foreach ($methods as $i => $data) {
            PaymentMethod::updateOrCreate(['code' => $data['code']], $data + [
                'is_active' => true,
                'sort_order' => $i,
                'advance_type' => 'none',
                'advance_value' => 0,
                'fee_amount' => 0,
            ]);
        }
    }

    protected function seedPages(): void
    {
        $pages = [
            'about-us' => ['About Us', '<p>Aurum Organics was born from a simple belief: honey should taste the way it did in our grandparents\' homes — thick, fragrant and completely natural.</p><p>We work directly with beekeeping families across Khyber Pakhtunkhwa and Punjab, paying fair prices for small-batch harvests and bottling every jar ourselves in Lahore.</p><h3>Our promise</h3><ul><li>100% raw, unheated and unblended</li><li>Every batch lab tested before bottling</li><li>Glass jars, recyclable packaging</li></ul>'],
            'shipping-policy' => ['Shipping Policy', '<p>We deliver across Pakistan through trusted courier partners.</p><ul><li>Orders are dispatched within 24 hours (Mon–Sat).</li><li>Delivery usually takes 2–4 working days.</li><li>Flat delivery charge of Rs. 250; free delivery on orders above Rs. 5,000.</li></ul><p>You will receive your tracking number by SMS/WhatsApp once your parcel is booked.</p>'],
            'return-refund-policy' => ['Return & Refund Policy', '<p>Your satisfaction is our priority.</p><ul><li>Sealed jars can be returned within 7 days of delivery.</li><li>If a jar arrives broken or leaking, send us a photo on WhatsApp within 48 hours and we will replace it free of charge.</li><li>Refunds for advance payments are processed to the original account within 5 working days.</li></ul>'],
            'privacy-policy' => ['Privacy Policy', '<p>We only collect the information needed to process and deliver your order — your name, phone number, address and (optionally) email.</p><p>We never sell or share your data with third parties other than our courier partners for delivery purposes. Payment screenshots are used only to verify your payment.</p>'],
            'terms-and-conditions' => ['Terms & Conditions', '<p>By placing an order on this website you agree to provide accurate delivery information and to be available to receive your parcel.</p><p>Prices are in Pakistani Rupees and may change without notice. We reserve the right to cancel orders that cannot be verified.</p>'],
        ];

        foreach ($pages as $slug => [$title, $content]) {
            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_active' => true, 'show_in_footer' => true]);
        }
    }
}
