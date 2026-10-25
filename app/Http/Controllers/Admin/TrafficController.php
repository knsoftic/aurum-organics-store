<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Models\Visit;
use App\Models\VisitEvent;
use App\Services\Analytics\Geo;
use App\Services\Analytics\Source;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin → Marketing → Traffic: the store's own visitor analytics and logs. */
class TrafficController extends Controller
{
    public const RANGES = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'custom' => 'Custom'];

    public function index(Request $request)
    {
        [$from, $to, $range] = $this->period($request);
        $hourly = $from->diffInHours($to) <= 48;
        $base = fn () => $this->visits($from, $to);

        $kpi = $this->kpis($from, $to);
        $span = $from->diffInSeconds($to) + 1;
        $previous = $this->kpis($from->copy()->subSeconds($span), $from->copy()->subSecond());

        // Chart: visitors / visits / page views per day (or per hour for a single day).
        $format = $hourly ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
        $rows = $base()->selectRaw("DATE_FORMAT(started_at, '{$format}') as bucket, COUNT(DISTINCT visitor_id) as visitors, COUNT(*) as visits, SUM(pageviews) as pageviews, SUM(order_id IS NOT NULL) as orders")
            ->groupBy('bucket')->get()->keyBy('bucket');
        $chart = ['labels' => [], 'visitors' => [], 'visits' => [], 'pageviews' => [], 'orders' => []];
        $step = $hourly ? '1 hour' : '1 day';
        foreach (CarbonPeriod::create($hourly ? $from->copy()->startOfHour() : $from->copy()->startOfDay(), $step, $to) as $point) {
            $key = $point->format($hourly ? 'Y-m-d H:00' : 'Y-m-d');
            $row = $rows[$key] ?? null;
            $chart['labels'][] = $point->format($hourly ? 'H:00' : 'd M');
            foreach (['visitors', 'visits', 'pageviews', 'orders'] as $metric) {
                $chart[$metric][] = (int) ($row->{$metric} ?? 0);
            }
        }

        $group = fn (string $column, int $limit = 10) => $base()->selectRaw("{$column} as label, COUNT(*) as visits, COUNT(DISTINCT visitor_id) as visitors, SUM(order_id IS NOT NULL) as orders, SUM(revenue) as revenue")
            ->whereNotNull($column)->where($column, '!=', '')->groupBy($column)->orderByDesc('visits')->limit($limit)->get();

        $pages = PageView::query()->join('visits', 'visits.id', '=', 'page_views.visit_id')
            ->whereBetween('page_views.created_at', [$from, $to])->where('visits.is_bot', false)
            ->selectRaw('page_views.path as label, COUNT(*) as views, COUNT(DISTINCT page_views.visit_id) as visits')
            ->groupBy('page_views.path')->orderByDesc('views')->limit(12)->get();

        $visitIds = fn () => $base()->select('id');
        $funnel = [
            'Visits' => $kpi['visits'],
            'Viewed a product' => PageView::whereIn('visit_id', $visitIds())->where('path', 'like', '/product/%')->distinct()->count('visit_id'),
            'Added to bag' => VisitEvent::whereIn('visit_id', $visitIds())->where('name', 'add_to_cart')->distinct()->count('visit_id'),
            'Started checkout' => VisitEvent::whereIn('visit_id', $visitIds())->where('name', 'begin_checkout')->distinct()->count('visit_id'),
            'Placed order' => VisitEvent::whereIn('visit_id', $visitIds())->where('name', 'purchase')->distinct()->count('visit_id'),
        ];

        $hours = array_fill(0, 24, 0);
        foreach ($base()->selectRaw('HOUR(started_at) as h, COUNT(*) as c')->groupBy('h')->pluck('c', 'h') as $h => $c) {
            $hours[(int) $h] = (int) $c;
        }

        return view('admin.marketing.traffic', [
            'range' => $range, 'from' => $from, 'to' => $to,
            'kpi' => $kpi, 'previous' => $previous, 'chart' => $chart,
            'live' => Visit::where('last_seen_at', '>=', now()->subMinutes(5))->where('is_bot', false)->distinct()->count('visitor_id'),
            'channels' => $group('medium', 10),
            'sources' => $base()->selectRaw("source as label, medium, COUNT(*) as visits, COUNT(DISTINCT visitor_id) as visitors, SUM(order_id IS NOT NULL) as orders, SUM(revenue) as revenue")
                ->groupBy('source', 'medium')->orderByDesc('visits')->limit(12)->get(),
            'campaigns' => $group('utm_campaign', 10),
            'referrers' => $group('referrer_host', 10),
            'countries' => $group('country', 12),
            'cities' => $group('city', 10),
            'devices' => $group('device', 5),
            'browsers' => $group('browser', 8),
            'systems' => $group('os', 8),
            'landing' => $group('landing_path', 10),
            'pages' => $pages,
            'funnel' => $funnel,
            'hours' => $hours,
            'clicks' => VisitEvent::whereIn('visit_id', $visitIds())->whereIn('name', ['whatsapp', 'contact'])->selectRaw('name, COUNT(*) as c')->groupBy('name')->pluck('c', 'name'),
            'hasGeo' => Geo::hasDatabase(),
        ]);
    }

    /** Every visit, newest first, with filters ("advanced log"). */
    public function log(Request $request)
    {
        [$from, $to, $range] = $this->period($request, '7d');

        $visits = $this->visits($from, $to)
            ->with(['events:id,visit_id,name,value,label'])
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->query('source')))
            ->when($request->filled('medium'), fn ($q) => $q->where('medium', $request->query('medium')))
            ->when($request->filled('country'), fn ($q) => $q->where('country', strtoupper($request->query('country'))))
            ->when($request->filled('device'), fn ($q) => $q->where('device', $request->query('device')))
            ->when($request->query('only') === 'orders', fn ($q) => $q->whereNotNull('order_id'))
            ->when($request->query('only') === 'cart', fn ($q) => $q->whereHas('events', fn ($e) => $e->where('name', 'add_to_cart')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('ip', 'like', $term)->orWhere('landing_path', 'like', $term)->orWhere('city', 'like', $term)
                    ->orWhere('utm_campaign', 'like', $term)->orWhere('referrer', 'like', $term));
            })
            ->latest('started_at')->paginate(40)->withQueryString();

        return view('admin.marketing.traffic-log', [
            'visits' => $visits, 'range' => $range, 'from' => $from, 'to' => $to,
            'sources' => Visit::query()->distinct()->orderBy('source')->pluck('source'),
            'countries' => Visit::query()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
        ]);
    }

    public function visit(Visit $visit)
    {
        $visit->load(['pages', 'events', 'order']);

        return view('admin.marketing.traffic-visit', [
            'visit' => $visit,
            'history' => Visit::where('visitor_id', $visit->visitor_id)->whereKeyNot($visit->id)->latest('started_at')->limit(10)->get(),
        ]);
    }

    /** Recent application errors from storage/logs/laravel.log. */
    public function systemLog(Request $request)
    {
        $file = storage_path('logs/laravel.log');
        $entries = [];
        if (is_file($file)) {
            $size = filesize($file);
            $handle = fopen($file, 'r');
            fseek($handle, max(0, $size - 400 * 1024));
            $text = stream_get_contents($handle);
            fclose($handle);

            $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/m', $text, -1, PREG_SPLIT_NO_EMPTY);
            foreach (array_reverse($parts) as $part) {
                if (! preg_match('/^\[([^\]]+)\]\s+\w+\.(\w+):\s*(.*)$/s', trim($part), $m)) {
                    continue;
                }
                $level = strtolower($m[2]);
                if ($request->filled('level') && $request->query('level') !== $level) {
                    continue;
                }
                [$first] = explode("\n", $m[3], 2);
                $entries[] = ['time' => $m[1], 'level' => $level, 'message' => mb_strimwidth($first, 0, 400, '…'), 'detail' => mb_substr($m[3], 0, 6000)];
                if (count($entries) >= 100) {
                    break;
                }
            }
        }

        return view('admin.marketing.system-log', ['entries' => $entries, 'size' => is_file($file) ? filesize($file) : 0]);
    }

    protected function kpis(Carbon $from, Carbon $to): array
    {
        $row = $this->visits($from, $to)->selectRaw('COUNT(*) as visits, COUNT(DISTINCT visitor_id) as visitors, COALESCE(SUM(pageviews),0) as pageviews,
            SUM(pageviews <= 1) as bounces, AVG(TIMESTAMPDIFF(SECOND, started_at, last_seen_at)) as avg_seconds, SUM(order_id IS NOT NULL) as orders, COALESCE(SUM(revenue),0) as revenue')->first();
        $visits = (int) $row->visits;

        return [
            'visits' => $visits,
            'visitors' => (int) $row->visitors,
            'pageviews' => (int) $row->pageviews,
            'bounce' => $visits ? round($row->bounces / $visits * 100) : 0,
            'duration' => (int) round((float) $row->avg_seconds),
            'orders' => (int) $row->orders,
            'revenue' => (float) $row->revenue,
            'conversion' => $visits ? round($row->orders / $visits * 100, 2) : 0,
        ];
    }

    protected function visits(Carbon $from, Carbon $to): Builder
    {
        return Visit::query()->whereBetween('started_at', [$from, $to])->where('is_bot', false);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    protected function period(Request $request, string $default = '30d'): array
    {
        $range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : $default;

        return match ($range) {
            'today' => [now()->startOfDay(), now(), $range],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay(), $range],
            '7d' => [now()->subDays(6)->startOfDay(), now(), $range],
            '90d' => [now()->subDays(89)->startOfDay(), now(), $range],
            'custom' => [
                rescue(fn () => Carbon::parse($request->query('from'))->startOfDay(), now()->subDays(29)->startOfDay(), false),
                rescue(fn () => Carbon::parse($request->query('to'))->endOfDay(), now(), false),
                $range,
            ],
            default => [now()->subDays(29)->startOfDay(), now(), '30d'],
        };
    }

    public static function mediumLabel(?string $medium): string
    {
        return Source::MEDIUMS[$medium] ?? ucfirst((string) $medium);
    }
}
