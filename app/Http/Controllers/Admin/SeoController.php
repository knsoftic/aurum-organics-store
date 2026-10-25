<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use App\Models\SeoKeyword;
use App\Models\Setting;
use App\Services\Seo\SearchConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin → Marketing → SEO Guide: automatic audit + on-page / off-page checklist. */
class SeoController extends Controller
{
    public function index()
    {
        $site = $this->siteChecks();
        $products = Product::active()->withCount(['images', 'approvedReviews'])->get()->map(fn ($p) => [
            'model' => $p,
            'edit' => route('admin.products.edit', $p),
            'checks' => $this->contentChecks($p->meta_title ?: $p->name.' — '.setting('store_name'), $p->meta_description ?: $p->short_description, $p->focus_keyword,
                $p->name.' '.$p->short_description.' '.$p->description.' '.implode(' ', $p->highlights ?? []), $p->slug, [
                    ['Photos', $p->images_count + 1 >= 3 ? 'ok' : 'warn', ($p->images_count + 1).' photo(s) — 3 or more help Google Images and buyers.'],
                    ['Reviews', $p->approved_reviews_count > 0 ? 'ok' : 'warn', $p->approved_reviews_count > 0 ? $p->approved_reviews_count.' approved review(s): star rating can show in Google.' : 'No approved reviews yet — stars in Google need at least one.'],
                ]),
        ]);
        $pages = Page::where('is_active', true)->get()->map(fn ($p) => [
            'model' => $p,
            'edit' => route('admin.pages.edit', $p),
            'checks' => $this->contentChecks($p->meta_title ?: $p->title.' — '.setting('store_name'), $p->meta_description ?: Str::limit(strip_tags((string) $p->content), 160), $p->focus_keyword, $p->title.' '.$p->content, $p->slug, [], false),
        ]);

        $all = collect($site)->concat($products->flatMap(fn ($p) => $p['checks']))->concat($pages->flatMap(fn ($p) => $p['checks']));
        $score = $all->count() ? round($all->sum(fn ($c) => ['ok' => 1, 'warn' => .5, 'bad' => 0][$c[1]]) / $all->count() * 100) : 0;

        return view('admin.marketing.seo', [
            'score' => $score,
            'site' => $site,
            'products' => $products,
            'pages' => $pages,
            'tasks' => setting('seo_tasks', []),
            'domain' => preg_replace('#^https?://#', '', rtrim(url('/'), '/')),
            'gscEmail' => SearchConsole::serviceEmail(),
        ]);
    }

    /** Ticks a guide task on or off (saved for everyone using the admin). */
    public function checklist(Request $request)
    {
        $data = $request->validate(['task' => 'required|string|max:40', 'done' => 'required|boolean']);
        $tasks = collect(setting('seo_tasks', []))->reject(fn ($t) => $t === $data['task']);
        if ($data['done']) {
            $tasks->push($data['task']);
        }
        Setting::setMany(['seo_tasks' => $tasks->values()->all()]);

        return response()->json(['ok' => true, 'done' => $tasks->count()]);
    }

    protected function siteChecks(): array
    {
        $title = (string) setting('meta_title', setting('store_name'));
        $description = (string) setting('meta_description');
        $socials = collect(['facebook', 'instagram', 'tiktok', 'youtube'])->filter(fn ($n) => setting('social_'.$n))->count();

        return [
            ['HTTPS', str_starts_with(config('app.url'), 'https://') ? 'ok' : 'bad', 'APP_URL should start with https:// so links, sitemap and canonical tags use the secure address.'],
            ['Search Console', setting('verify_google') || SearchConsole::configured() ? 'ok' : 'bad', setting('verify_google') || SearchConsole::configured() ? 'Verification tag added.' : 'Add the Google verification code in Tracking & Pixels.'],
            ['Analytics', setting('ga4_id') || setting('gtm_id') ? 'ok' : 'warn', setting('ga4_id') || setting('gtm_id') ? 'Google Analytics / Tag Manager installed (the built-in Traffic report works too).' : 'Optional: add a GA4 ID — the built-in Traffic report already counts visits.'],
            ['Home page title', $this->lengthState($title, 30, 60), mb_strlen($title).' characters — best 30–60. Settings → SEO & Social.'],
            ['Home page description', $this->lengthState($description, 70, 160), $description ? mb_strlen($description).' characters — best 70–160.' : 'Missing. Settings → SEO & Social → Meta description.'],
            ['Logo & favicon', setting('logo') || setting('brand_mark') ? (setting('favicon') ? 'ok' : 'warn') : 'warn', 'A logo helps the brand panel in Google; a favicon shows next to your result on mobile.'],
            ['Social profiles', $socials >= 2 ? 'ok' : ($socials ? 'warn' : 'bad'), $socials.' social link(s) — they are added to your structured data so Google connects your brand profiles.'],
            ['Contact details', setting('store_phone') || setting('whatsapp_number') ? (setting('store_address') ? 'ok' : 'warn') : 'bad', 'Phone and address on the site build trust (and help local search).'],
            ['Keywords tracked', SeoKeyword::count() >= 10 ? 'ok' : 'warn', SeoKeyword::count().' keyword(s) on the Keywords page.'],
            ['Debug mode off', config('app.debug') ? (app()->environment('local') ? 'warn' : 'bad') : 'ok', config('app.debug') ? 'APP_DEBUG=true shows error details to visitors — set it to false on the live server.' : 'Error details are hidden from visitors.'],
            ['Sitemap & robots', 'ok', 'Generated automatically: /sitemap.xml and /robots.txt.'],
            ['Structured data', 'ok', 'Product, price, stock, reviews, breadcrumbs, organisation and FAQ data are added automatically.'],
        ];
    }

    /** Title, description and keyword-placement checks for one product or page. */
    protected function contentChecks(string $title, ?string $description, ?string $keyword, string $content, string $slug, array $extra = [], bool $needsLongText = true): array
    {
        $description = trim((string) $description);
        $text = mb_strtolower(trim(preg_replace('/\s+/', ' ', strip_tags($content))));
        $words = str_word_count(strip_tags($content));
        $kw = mb_strtolower(trim((string) $keyword));
        $checks = [
            ['Title length', $this->lengthState($title, 30, 60), mb_strlen($title).' characters (best 30–60).'],
            ['Description length', $this->lengthState($description, 70, 160), $description ? mb_strlen($description).' characters (best 70–160).' : 'No meta description.'],
        ];
        if ($needsLongText) {
            $checks[] = ['Text length', $words >= 300 ? 'ok' : ($words >= 150 ? 'warn' : 'bad'), $words.' words — aim for 300+ (benefits, ingredients, how to use, FAQs).'];
        }
        if ($kw === '') {
            $checks[] = ['Focus keyword', 'warn', 'Set a focus keyword to check where it is used.'];
        } else {
            $checks[] = ['Keyword in title', str_contains(mb_strtolower($title), $kw) ? 'ok' : 'bad', '“'.$keyword.'” '.(str_contains(mb_strtolower($title), $kw) ? 'is' : 'is not').' in the meta title.'];
            $checks[] = ['Keyword in description', str_contains(mb_strtolower($description), $kw) ? 'ok' : 'warn', str_contains(mb_strtolower($description), $kw) ? 'Used in the meta description.' : 'Add it to the meta description.'];
            $count = substr_count($text, $kw);
            $checks[] = ['Keyword in text', $count >= 2 ? 'ok' : ($count === 1 ? 'warn' : 'bad'), 'Used '.$count.' time(s) in the text — 2 to 5 natural mentions is ideal.'];
            $slugHit = collect(explode(' ', $kw))->filter(fn ($w) => mb_strlen($w) > 3)->contains(fn ($w) => str_contains($slug, Str::slug($w)));
            $checks[] = ['Keyword in URL', $slugHit ? 'ok' : 'warn', 'URL: /'.$slug];
        }

        return array_merge($checks, $extra);
    }

    protected function lengthState(string $value, int $min, int $max): string
    {
        $len = mb_strlen(trim($value));

        return $len >= $min && $len <= $max ? 'ok' : ($len === 0 ? 'bad' : 'warn');
    }
}
