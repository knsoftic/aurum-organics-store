<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use HandlesUploads;

    /** Text settings and their validation rules. */
    protected const FIELDS = [
        // General
        'store_name' => 'required|string|max:80',
        'tagline' => 'nullable|string|max:150',
        'store_email' => 'nullable|email|max:150',
        'store_phone' => 'nullable|string|max:40',
        'whatsapp_number' => 'nullable|string|max:30',
        'store_address' => 'nullable|string|max:300',
        'currency_symbol' => 'required|string|max:8',
        'notify_email' => 'nullable|email|max:150',
        // Storefront
        'store_mode' => 'required|in:single,multi',
        'featured_product_id' => 'nullable|integer',
        'theme' => 'required|in:emerald,noir,ivory,rose',
        'announcement_text' => 'nullable|string|max:200',
        'hero_eyebrow' => 'nullable|string|max:80',
        'hero_title' => 'nullable|string|max:150',
        'hero_subtitle' => 'nullable|string|max:400',
        'hero_button' => 'nullable|string|max:40',
        'about_eyebrow' => 'nullable|string|max:80',
        'about_title' => 'nullable|string|max:150',
        'about_text' => 'nullable|string|max:3000',
        // Checkout & shipping
        'shipping_fee' => 'required|numeric|min:0',
        'free_shipping_min' => 'nullable|numeric|min:0',
        'delivery_time' => 'nullable|string|max:80',
        'min_order_amount' => 'nullable|numeric|min:0',
        'order_prefix' => 'required|alpha_num|max:6',
        'low_stock_threshold' => 'nullable|integer|min:0',
        'checkout_note' => 'nullable|string|max:500',
        'return_policy_short' => 'nullable|string|max:200',
        // SEO & social
        'meta_title' => 'nullable|string|max:160',
        'meta_description' => 'nullable|string|max:300',
        'social_facebook' => 'nullable|url|max:255',
        'social_instagram' => 'nullable|url|max:255',
        'social_tiktok' => 'nullable|url|max:255',
        'social_youtube' => 'nullable|url|max:255',
        'footer_text' => 'nullable|string|max:300',
    ];

    protected const BOOLEANS = ['announcement_active', 'auto_approve_reviews', 'show_whatsapp_button'];

    protected const IMAGES = ['logo', 'favicon', 'hero_image', 'about_image'];

    public function edit()
    {
        return view('admin.settings.edit', [
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'faqs' => setting('faqs', []),
        ]);
    }

    public function update(Request $request)
    {
        $rules = self::FIELDS + [
            'faqs' => 'nullable|array|max:20',
            'faqs.*.q' => 'nullable|string|max:200',
            'faqs.*.a' => 'nullable|string|max:1000',
        ];
        foreach (self::IMAGES as $image) {
            $rules[$image] = $image === 'favicon' ? 'nullable|image|mimes:png,jpg,jpeg,webp,ico|max:1024' : 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096';
        }

        $data = $request->validate($rules);

        $values = collect($data)->only(array_keys(self::FIELDS))->all();
        foreach (self::BOOLEANS as $flag) {
            $values[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        foreach (self::IMAGES as $image) {
            $values[$image] = $this->replaceUpload($request->file($image), setting($image), 'branding', $request->boolean('remove_'.$image));
        }
        $values['faqs'] = collect($data['faqs'] ?? [])
            ->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))
            ->map(fn ($f) => ['q' => trim($f['q']), 'a' => trim($f['a'])])->values()->all();

        Setting::setMany($values);

        return back()->with('success', 'Settings saved.')->withFragment($request->input('tab', ''));
    }
}
