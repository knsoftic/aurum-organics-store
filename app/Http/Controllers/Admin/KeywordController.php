<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordStat;
use App\Models\SeoQuery;
use App\Models\Setting;
use App\Services\Seo\SearchConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/** Admin → Marketing → Keywords: tracked search phrases and their Google positions. */
class KeywordController extends Controller
{
    public function index(SearchConsole $gsc)
    {
        // First visit: start with keywords made from the store's own products.
        if (! setting('seo_keywords_seeded') && SeoKeyword::count() === 0) {
            foreach ($this->suggestions() as $s) {
                SeoKeyword::firstOrCreate(['keyword' => $s['keyword']], ['group' => $s['group'], 'priority' => $s['priority'], 'target_path' => $s['path']]);
            }
            Setting::setMany(['seo_keywords_seeded' => '1']);
        }

        // Refresh Search Console data once a day when it is connected.
        $lastSync = setting('gsc_last_sync');
        if (SearchConsole::configured() && (! $lastSync || now()->diffInHours($lastSync, true) >= 24)) {
            try {
                $gsc->sync();
            } catch (Throwable) {
                // shown on the page from gsc_last_error
            }
        }

        $keywords = SeoKeyword::orderBy('priority')->orderBy('keyword')->get();
        $stats = SeoKeywordStat::where('date', '>=', now()->subDays(95))->orderBy('date')->get()->groupBy('seo_keyword_id');
        $rows = $keywords->map(fn ($k) => $this->summary($k, $stats[$k->id] ?? collect()));

        $tracked = $keywords->pluck('keyword')->map(fn ($k) => mb_strtolower($k))->all();

        return view('admin.marketing.keywords', [
            'rows' => $rows,
            'groups' => $keywords->pluck('group')->filter()->unique()->sort()->values(),
            'queries' => SeoQuery::orderByDesc('clicks')->orderByDesc('impressions')->limit(50)->get(),
            'tracked' => $tracked,
            'suggestions' => collect($this->suggestions())->reject(fn ($s) => in_array(mb_strtolower($s['keyword']), $tracked, true))->values(),
            'gsc' => [
                'configured' => SearchConsole::configured(),
                'email' => SearchConsole::serviceEmail(),
                'lastSync' => setting('gsc_last_sync'),
                'error' => setting('gsc_last_error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $keywords = collect(preg_split('/\r?\n|,/', $data['keyword']))->map(fn ($k) => trim($k))->filter()->unique();
        foreach ($keywords as $keyword) {
            SeoKeyword::firstOrCreate(['keyword' => Str::limit($keyword, 190, '')], collect($data)->except('keyword')->all());
        }

        return back()->with('success', $keywords->count() === 1 ? 'Keyword added.' : $keywords->count().' keywords added.');
    }

    public function update(Request $request, SeoKeyword $keyword)
    {
        $keyword->update($this->validated($request, $keyword));

        return back()->with('success', 'Keyword updated.');
    }

    public function destroy(SeoKeyword $keyword)
    {
        $keyword->delete();

        return back()->with('success', 'Keyword removed.');
    }

    /** Position checked by hand on Google (when Search Console is not connected). */
    public function rank(Request $request, SeoKeyword $keyword)
    {
        $data = $request->validate([
            'position' => 'nullable|numeric|min:1|max:100',
            'date' => 'nullable|date|before_or_equal:today',
        ]);
        SeoKeywordStat::updateOrCreate(
            ['seo_keyword_id' => $keyword->id, 'date' => $data['date'] ?? now()->toDateString(), 'source' => 'manual'],
            ['position' => $data['position'] ?? null, 'clicks' => 0, 'impressions' => 0, 'ctr' => 0]
        );

        return back()->with('success', 'Position saved for “'.$keyword->keyword.'”.');
    }

    public function sync(SearchConsole $gsc)
    {
        try {
            $count = $gsc->sync();
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Search Console synced — {$count} search queries found.");
    }

    public function addSuggestions(Request $request)
    {
        $chosen = collect($request->input('keywords', []))->map(fn ($k) => trim((string) $k))->filter();
        $all = collect($this->suggestions())->keyBy(fn ($s) => mb_strtolower($s['keyword']));
        foreach ($chosen as $keyword) {
            $s = $all[mb_strtolower($keyword)] ?? ['group' => null, 'priority' => 2, 'path' => null];
            SeoKeyword::firstOrCreate(['keyword' => Str::limit($keyword, 190, '')], ['group' => $s['group'], 'priority' => $s['priority'], 'target_path' => $s['path']]);
        }

        return back()->with('success', $chosen->count().' keyword(s) added.');
    }

    /** Position, clicks and movement for one keyword. */
    protected function summary(SeoKeyword $keyword, Collection $stats): object
    {
        $gsc = $stats->where('source', 'gsc');
        $recent = $gsc->where('date', '>=', now()->subDays(30)->startOfDay());
        $impressions = $recent->sum('impressions');
        $latest = $stats->sortBy('date')->last();
        $weekAgo = $stats->filter(fn ($s) => $s->date->lte(now()->subDays(7)))->sortBy('date')->last();

        // Average position weighted by impressions, like Search Console shows it.
        $position = $impressions > 0
            ? round($recent->sum(fn ($s) => $s->position * $s->impressions) / $impressions, 1)
            : ($latest?->position ? round($latest->position, 1) : null);

        return (object) [
            'keyword' => $keyword,
            'position' => $position,
            'move' => $latest && $weekAgo && $latest->position && $weekAgo->position ? round($weekAgo->position - $latest->position, 1) : null,
            'clicks' => $recent->sum('clicks'),
            'impressions' => $impressions,
            'ctr' => $impressions ? round($recent->sum('clicks') / $impressions * 100, 1) : null,
            'checked' => $latest?->date,
            'source' => $latest?->source,
            'spark' => $stats->filter(fn ($s) => $s->position)->sortBy('date')->take(-30)->pluck('position')->values()->all(),
        ];
    }

    /**
     * Keyword ideas built from the products in the store, plus proven phrases for
     * the product type (people in Pakistan search in English, Roman Urdu and Urdu).
     */
    protected function suggestions(): array
    {
        $out = [];
        $add = function (string $keyword, string $group, int $priority = 2, ?string $path = null) use (&$out) {
            $key = mb_strtolower(trim($keyword));
            if ($key !== '' && ! isset($out[$key])) {
                $out[$key] = ['keyword' => trim($keyword), 'group' => $group, 'priority' => $priority, 'path' => $path];
            }
        };

        $brand = trim((string) setting('store_name'));
        $products = Product::active()->with('category')->get();
        $text = mb_strtolower($products->map(fn ($p) => $p->name.' '.$p->category?->name.' '.$p->short_description.' '.$p->description)->implode(' '));

        foreach ($products as $p) {
            $path = '/product/'.$p->slug;
            $name = mb_strtolower($p->name);
            $add($name, 'Product', 1, $path);
            $add($name.' price in pakistan', 'Product', 1, $path);
            if ($brand) {
                $add(mb_strtolower($brand.' '.$p->name), 'Brand', 1, $path);
            }
            $add('buy '.$name.' online', 'Product', 2, $path);
            if ($p->category) {
                $cat = mb_strtolower($p->category->name);
                $add($cat.' in pakistan', 'Category', 2, $path);
                $add('best '.$cat.' in pakistan', 'Category', 2, $path);
            }
        }
        if ($brand) {
            $add(mb_strtolower($brand), 'Brand', 1, '/');
        }

        if (str_contains($text, 'hair oil') || str_contains($text, 'hair')) {
            $path = $products->first() ? '/product/'.$products->first()->slug : '/';
            foreach ([
                ['best hair oil in pakistan', 'Category', 1], ['organic hair oil', 'Category', 1], ['herbal hair oil', 'Category', 2],
                ['natural hair oil in pakistan', 'Category', 2], ['hair oil price in pakistan', 'Category', 2], ['chemical free hair oil', 'Category', 3],
                ['hair growth oil', 'Benefit', 1], ['hair growth oil in pakistan', 'Benefit', 1], ['best oil for hair growth', 'Benefit', 1],
                ['hair fall control oil', 'Benefit', 1], ['oil for hair fall', 'Benefit', 2], ['oil for thick hair', 'Benefit', 2],
                ['hair oil for men', 'Audience', 2], ['hair oil for women', 'Audience', 2],
                ['amla hair oil', 'Ingredient', 3], ['bhringraj hair oil', 'Ingredient', 3], ['rosemary hair oil', 'Ingredient', 2], ['coconut hair oil', 'Ingredient', 3],
                ['balon ka tail', 'Roman Urdu', 2], ['baal lambe karne ka tail', 'Roman Urdu', 2], ['baal girne ka tail', 'Roman Urdu', 2],
                ['بالوں کا تیل', 'Urdu', 2], ['بال لمبے کرنے کا تیل', 'Urdu', 3],
            ] as [$keyword, $group, $priority]) {
                $add($keyword, $group, $priority, $path);
            }
        }

        return array_values($out);
    }

    protected function validated(Request $request, ?SeoKeyword $keyword = null): array
    {
        $data = $request->validate([
            // Adding accepts several keywords (one per line); editing changes a single one.
            'keyword' => array_filter(['required', 'string', $keyword ? 'max:190' : 'max:2000', $keyword ? Rule::unique('seo_keywords', 'keyword')->ignore($keyword) : null]),
            'target_path' => 'nullable|string|max:300',
            'group' => 'nullable|string|max:60',
            'priority' => 'required|integer|in:1,2,3',
            'notes' => 'nullable|string|max:2000',
        ]);
        if ($keyword) {
            $data['keyword'] = Str::limit(trim($data['keyword']), 190, '');
        }
        if (! empty($data['target_path'])) {
            $path = parse_url($data['target_path'], PHP_URL_PATH) ?: $data['target_path'];
            $data['target_path'] = '/'.ltrim($path, '/');
        }
        $data['is_active'] = true;

        return $data;
    }
}
