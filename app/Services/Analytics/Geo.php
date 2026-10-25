<?php

namespace App\Services\Analytics;

use DateTimeZone;
use Illuminate\Http\Request;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Visitor location without paid APIs:
 * Cloudflare's country header, then a MaxMind GeoLite2 database (if uploaded), then the browser's time zone.
 */
class Geo
{
    public const DB_PATH = 'app/geoip/GeoLite2.mmdb';

    protected static ?Reader $reader = null;

    /** @return array{country: ?string, region: ?string, city: ?string} */
    public static function lookup(Request $request, ?string $timezone): array
    {
        $out = ['country' => null, 'region' => null, 'city' => null];

        $cf = strtoupper((string) $request->header('CF-IPCountry'));
        if (preg_match('/^[A-Z]{2}$/', $cf) && ! in_array($cf, ['XX', 'T1'], true)) {
            $out['country'] = $cf;
        }

        if ($record = self::database($request->ip())) {
            $out['country'] ??= $record['country']['iso_code'] ?? $record['registered_country']['iso_code'] ?? null;
            $out['region'] = $record['subdivisions'][0]['names']['en'] ?? null;
            $out['city'] = $record['city']['names']['en'] ?? null;
        }

        if (! $out['country'] && $timezone) {
            try {
                $code = (new DateTimeZone($timezone))->getLocation()['country_code'] ?? null;
                $out['country'] = $code && $code !== '??' ? $code : null;
            } catch (Throwable) {
                // unknown time zone name
            }
        }

        return $out;
    }

    public static function hasDatabase(): bool
    {
        return is_file(storage_path(self::DB_PATH));
    }

    protected static function database(?string $ip): ?array
    {
        if (! $ip || ! self::hasDatabase() || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            self::$reader ??= new Reader(storage_path(self::DB_PATH));

            return self::$reader->get($ip);
        } catch (Throwable) {
            return null;
        }
    }
}
