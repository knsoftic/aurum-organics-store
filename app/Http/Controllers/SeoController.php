<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml, robots.txt and ads.txt, generated from the live store. */
class SeoController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('seo.sitemap', 3600, function () {
            $products = Product::active()->with('images')->get();
            $urls = [['loc' => route('home'), 'lastmod' => optional($products->max('updated_at'))->toAtomString(), 'priority' => '1.0', 'images' => []]];

            if ($products->count() > 1) {
                $urls[] = ['loc' => route('shop'), 'lastmod' => optional($products->max('updated_at'))->toAtomString(), 'priority' => '0.9', 'images' => []];
            }
            foreach ($products as $product) {
                $urls[] = ['loc' => route('product.show', $product), 'lastmod' => $product->updated_at->toAtomString(), 'priority' => '0.9', 'images' => array_slice($product->gallery, 0, 10), 'title' => $product->name];
            }
            foreach (Page::where('is_active', true)->get() as $page) {
                $urls[] = ['loc' => route('page.show', $page), 'lastmod' => $page->updated_at->toAtomString(), 'priority' => '0.5', 'images' => []];
            }
            $urls[] = ['loc' => route('contact'), 'lastmod' => null, 'priority' => '0.4', 'images' => []];

            return view('seo.sitemap', compact('urls'))->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /order/',
            'Disallow: /track-order',
            'Disallow: /payment/',
            'Disallow: /t',
            'Allow: /',
            '',
            'Sitemap: '.route('seo.sitemap'),
        ];
        if ($extra = trim((string) setting('robots_extra'))) {
            array_splice($lines, 9, 0, explode("\n", str_replace("\r", '', $extra)));
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** AdSense needs this file; the publisher line is added automatically. */
    public function adsTxt()
    {
        $body = trim((string) setting('ads_txt'));
        $client = (string) setting('adsense_client');
        if ($body === '' && preg_match('/pub-\d+/', $client, $m)) {
            $body = "google.com, {$m[0]}, DIRECT, f08c47fec0942fa0";
        }
        abort_if($body === '', 404);

        return response($body."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
