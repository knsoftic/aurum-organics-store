<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\PageView;
use App\Models\Visit;
use App\Models\VisitEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * First-party traffic log. The browser sends a small beacon per page (see tracking-body partial);
 * shop actions (add to bag, checkout, order) are recorded on the server against the same session.
 */
class Tracker
{
    public const SID_COOKIE = 'kn_sid';

    public const VID_COOKIE = 'kn_vid';

    public static function enabled(): bool
    {
        return setting('analytics_enabled', '1') === '1';
    }

    /** Store owners browsing their own shop are not counted. */
    protected function ignored(Request $request): bool
    {
        return ! self::enabled() || auth()->check() || UserAgent::isBot((string) $request->userAgent());
    }

    /** Beacon from the browser: a page view, or a click event such as WhatsApp. */
    public function collect(Request $request, array $data): void
    {
        if ($this->ignored($request)) {
            return;
        }

        $visit = $this->visit($request, $data);

        if (! empty($data['e'])) {
            $this->addEvent($visit, $data['e'], $data['v'] ?? null, $data['l'] ?? null);

            return;
        }

        $path = $this->cleanPath($data['p'] ?? '/');
        PageView::create(['visit_id' => $visit->id, 'path' => $path, 'title' => Str::limit((string) ($data['t'] ?? ''), 190, ''), 'created_at' => now()]);
        $visit->forceFill(['pageviews' => $visit->pageviews + 1, 'exit_path' => $path, 'last_seen_at' => now()])->save();
    }

    /** Server-side shop action for the current browser session (no-op without a tracked session). */
    public function event(Request $request, string $name, float|int|null $value = null, ?string $label = null, ?Order $order = null): void
    {
        if ($this->ignored($request) || ! ($sid = $this->cookie($request, self::SID_COOKIE))) {
            return;
        }

        try {
            $visit = Visit::where('sid', $sid)->first();
            if (! $visit) {
                return;
            }

            $this->addEvent($visit, $name, $value, $label);

            if ($order) {
                $visit->forceFill(['order_id' => $order->id, 'revenue' => $visit->revenue + (float) $order->total])->save();
            }
        } catch (Throwable $e) {
            report($e); // analytics must never block the bag or checkout
        }
    }

    protected function addEvent(Visit $visit, string $name, mixed $value, ?string $label): void
    {
        if (! array_key_exists($name, VisitEvent::NAMES)) {
            return;
        }

        VisitEvent::create([
            'visit_id' => $visit->id,
            'name' => $name,
            'value' => is_numeric($value) ? round((float) $value, 2) : null,
            'label' => $label !== null ? Str::limit(strip_tags($label), 190, '') : null,
            'created_at' => now(),
        ]);
        $visit->forceFill(['last_seen_at' => now()])->save();
    }

    protected function visit(Request $request, array $data): Visit
    {
        $sid = $data['sid'];
        if ($visit = Visit::where('sid', $sid)->first()) {
            return $visit;
        }

        $ua = (string) $request->userAgent();
        $landing = (string) ($data['p'] ?? '/');
        parse_str((string) parse_url($landing, PHP_URL_QUERY), $query);
        $ownHost = preg_replace('/^www\./', '', strtolower($request->getHost()));
        $source = Source::classify($query, $data['r'] ?? null, $ua, $ownHost);
        $geo = Geo::lookup($request, $data['tz'] ?? null);
        $width = isset($data['w']) ? (int) $data['w'] : null;

        $this->prune();

        return Visit::create([
            'sid' => $sid,
            'visitor_id' => $data['vid'],
            'ip' => $request->ip(),
            'country' => $geo['country'],
            'region' => $geo['region'] ? Str::limit($geo['region'], 78, '') : null,
            'city' => $geo['city'] ? Str::limit($geo['city'], 78, '') : null,
            'device' => UserAgent::device($ua, $width),
            'browser' => UserAgent::browser($ua),
            'os' => UserAgent::os($ua),
            'screen_w' => $width ?: null,
            'language' => isset($data['lang']) ? substr((string) $data['lang'], 0, 16) : null,
            'timezone' => isset($data['tz']) ? substr((string) $data['tz'], 0, 60) : null,
            'source' => $source['source'],
            'medium' => $source['medium'],
            'referrer' => ! empty($data['r']) && $source['referrer_host'] ? Str::limit((string) $data['r'], 495, '') : null,
            'referrer_host' => $source['referrer_host'] ? substr($source['referrer_host'], 0, 120) : null,
            'utm_source' => $this->param($query, 'utm_source', 100),
            'utm_medium' => $this->param($query, 'utm_medium', 100),
            'utm_campaign' => $this->param($query, 'utm_campaign', 150),
            'utm_term' => $this->param($query, 'utm_term', 150),
            'utm_content' => $this->param($query, 'utm_content', 150),
            'click_id' => $source['click_id'],
            'landing_path' => $this->cleanPath($landing),
            'pageviews' => 0,
            'user_agent' => Str::limit($ua, 1000, ''),
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /** Path with marketing/click parameters removed so the same page groups together. */
    protected function cleanPath(string $url): string
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $base = rtrim((string) parse_url(url('/'), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $query = array_diff_key($query, array_flip(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'msclkid', 'ScCid', 'key']));
        $path .= $query ? '?'.http_build_query($query) : '';

        return Str::limit($path, 295, '');
    }

    protected function param(array $query, string $key, int $max): ?string
    {
        $value = trim((string) ($query[$key] ?? ''));

        return $value === '' ? null : Str::limit(strip_tags($value), $max - 1, '');
    }

    protected function cookie(Request $request, string $name): ?string
    {
        $value = (string) $request->cookie($name);

        return preg_match('/^[A-Za-z0-9]{8,40}$/', $value) ? $value : null;
    }

    /** Old visits are cleared now and then (Settings → retention). */
    protected function prune(): void
    {
        if (random_int(1, 200) !== 1) {
            return;
        }
        $days = max(30, (int) setting('analytics_retention', 365));
        Visit::where('started_at', '<', now()->subDays($days))->limit(5000)->delete();
    }
}
