<?php

namespace App\Services\Analytics;

/** Small user-agent reader: device type, browser, operating system and bot check. */
class UserAgent
{
    protected const BOTS = '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|bingpreview|headless|lighthouse|pagespeed|python|curl|wget|go-http|java\/|axios|httpclient|scrapy|semrush|ahrefs|mj12|dotbot|petalbot|yandex|baidu|bytespider|gptbot|claudebot|ccbot|preview/i';

    public static function isBot(string $ua): bool
    {
        return $ua === '' || (bool) preg_match(self::BOTS, $ua);
    }

    public static function device(string $ua, ?int $screenWidth = null): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle/i', $ua) || (preg_match('/Android/i', $ua) && ! preg_match('/Mobile/i', $ua))) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $ua)) {
            return 'mobile';
        }
        // iPadOS reports itself as a Mac; a touch-size screen gives it away.
        if ($screenWidth && $screenWidth <= 1024 && str_contains($ua, 'Macintosh')) {
            return 'tablet';
        }

        return 'desktop';
    }

    public static function browser(string $ua): string
    {
        $map = [
            '/FBAN|FBAV|FB_IAB/' => 'Facebook app',
            '/Instagram/' => 'Instagram app',
            '/musical_ly|BytedanceWebview|TikTok/i' => 'TikTok app',
            '/Snapchat/' => 'Snapchat app',
            '/Edg(e|A|iOS)?\//' => 'Edge',
            '/OPR\/|Opera/' => 'Opera',
            '/SamsungBrowser/' => 'Samsung Internet',
            '/UCBrowser/' => 'UC Browser',
            '/YaBrowser/' => 'Yandex',
            '/Firefox|FxiOS/' => 'Firefox',
            '/Chrome|CriOS/' => 'Chrome',
            '/Safari/' => 'Safari',
        ];
        foreach ($map as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Other';
    }

    public static function os(string $ua): string
    {
        $map = [
            '/Windows/' => 'Windows',
            '/iPhone|iPad|iPod/' => 'iOS',
            '/Android/' => 'Android',
            '/CrOS/' => 'ChromeOS',
            '/Mac OS X|Macintosh/' => 'macOS',
            '/Linux/' => 'Linux',
        ];
        foreach ($map as $pattern => $name) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        return 'Other';
    }
}
