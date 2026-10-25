<?php

namespace App\Services\Seo;

use App\Models\SeoKeyword;
use App\Models\SeoKeywordStat;
use App\Models\SeoQuery;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Google Search Console (read-only) through a service account:
 * which searches show the site, clicks, impressions and average position.
 */
class SearchConsole
{
    protected const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    protected const API = 'https://searchconsole.googleapis.com/webmasters/v3';

    public static function credentials(): ?array
    {
        try {
            $json = setting('gsc_credentials') ? json_decode(Crypt::decryptString(setting('gsc_credentials')), true) : null;
        } catch (Throwable) {
            return null;
        }

        return is_array($json) && ! empty($json['client_email']) && ! empty($json['private_key']) ? $json : null;
    }

    public static function serviceEmail(): ?string
    {
        return self::credentials()['client_email'] ?? null;
    }

    public static function configured(): bool
    {
        return self::credentials() && trim((string) setting('gsc_site')) !== '';
    }

    /** Properties the service account can read (used to show a helpful error). */
    public function sites(): array
    {
        $response = Http::withToken($this->token())->timeout(30)->get(self::API.'/sites');
        $this->throwIfFailed($response);

        return collect($response->json('siteEntry', []))->pluck('siteUrl')->all();
    }

    /** Pulls the latest report and stores keyword positions. Returns the number of queries found. */
    public function sync(): int
    {
        if (! self::configured()) {
            throw new RuntimeException('Connect Google Search Console first (Marketing → Tracking & Pixels).');
        }

        // Search Console data arrives with a ~2 day delay.
        $end = now()->subDays(2)->startOfDay();
        $start = $end->copy()->subDays(27);

        try {
            $rows = $this->query(['startDate' => $start->toDateString(), 'endDate' => $end->toDateString(), 'dimensions' => ['query'], 'rowLimit' => 1000]);
            $daily = $this->query(['startDate' => $end->copy()->subDays(89)->toDateString(), 'endDate' => $end->toDateString(), 'dimensions' => ['date', 'query'], 'rowLimit' => 25000]);
        } catch (Throwable $e) {
            Setting::setMany(['gsc_last_error' => $e->getMessage()]);
            throw $e;
        }

        DB::transaction(function () use ($rows, $daily, $start, $end) {
            SeoQuery::query()->delete();
            foreach (array_chunk($rows, 200) as $chunk) {
                SeoQuery::insert(array_map(fn ($r) => [
                    'query' => mb_substr($r['keys'][0], 0, 250),
                    'clicks' => (int) $r['clicks'],
                    'impressions' => (int) $r['impressions'],
                    'ctr' => round($r['ctr'], 4),
                    'position' => round($r['position'], 2),
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk));
            }

            $keywords = SeoKeyword::where('is_active', true)->get()->keyBy(fn ($k) => mb_strtolower(trim($k->keyword)));
            foreach ($daily as $r) {
                $keyword = $keywords[mb_strtolower(trim($r['keys'][1]))] ?? null;
                if ($keyword) {
                    SeoKeywordStat::updateOrCreate(
                        ['seo_keyword_id' => $keyword->id, 'date' => $r['keys'][0], 'source' => 'gsc'],
                        ['position' => round($r['position'], 2), 'clicks' => (int) $r['clicks'], 'impressions' => (int) $r['impressions'], 'ctr' => round($r['ctr'], 4)]
                    );
                }
            }
        });

        Setting::setMany(['gsc_last_sync' => now()->toDateTimeString(), 'gsc_last_error' => '']);

        return count($rows);
    }

    protected function query(array $body): array
    {
        $site = rawurlencode(trim((string) setting('gsc_site')));
        $response = Http::withToken($this->token())->timeout(60)->post(self::API."/sites/{$site}/searchAnalytics/query", $body);
        $this->throwIfFailed($response);

        return $response->json('rows', []);
    }

    /** OAuth access token from a signed service-account JWT (cached ~50 minutes). */
    protected function token(): string
    {
        $creds = self::credentials() ?? throw new RuntimeException('The Search Console key is missing.');

        return Cache::remember('gsc.token.'.md5($creds['client_email']), 3000, function () use ($creds) {
            $now = time();
            $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $unsigned = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $creds['client_email'],
                'scope' => self::SCOPE,
                'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (! openssl_sign($unsigned, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The private key in the service-account file could not be read.');
            }

            $response = Http::asForm()->timeout(30)->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$b64($signature),
            ]);
            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Google refused the service-account key: '.($response->json('error_description') ?: $response->json('error') ?: 'HTTP '.$response->status()));
            }

            return $response->json('access_token');
        });
    }

    protected function throwIfFailed($response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('error.message') ?: 'HTTP '.$response->status();
        if ($response->status() === 403) {
            $message = 'No access to this property. In Search Console → Settings → Users and permissions, add '
                .self::serviceEmail().' as a user, and check the property name ('.setting('gsc_site').').';
        }

        throw new RuntimeException('Search Console: '.$message);
    }
}
