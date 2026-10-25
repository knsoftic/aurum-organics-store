<?php

namespace App\Services\Analytics;

/**
 * Works out where a visit came from: ad click IDs, UTM tags, the referring site,
 * or the in-app browser of a social app (those often send no referrer).
 */
class Source
{
    public const MEDIUMS = ['organic' => 'Organic search', 'paid' => 'Paid ads', 'social' => 'Social', 'referral' => 'Referral', 'email' => 'Email', 'direct' => 'Direct'];

    protected const SEARCH = ['google' => 'Google', 'bing' => 'Bing', 'yahoo' => 'Yahoo', 'duckduckgo' => 'DuckDuckGo', 'yandex' => 'Yandex', 'baidu' => 'Baidu', 'ecosia' => 'Ecosia'];

    protected const SOCIAL = [
        'facebook' => 'Facebook', 'fb.me' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube',
        'youtu.be' => 'YouTube', 't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)', 'x.com' => 'X (Twitter)', 'whatsapp' => 'WhatsApp',
        'wa.me' => 'WhatsApp', 'pinterest' => 'Pinterest', 'linkedin' => 'LinkedIn', 'lnkd.in' => 'LinkedIn', 'reddit' => 'Reddit',
        'snapchat' => 'Snapchat', 'quora' => 'Quora', 'threads.net' => 'Threads', 'telegram' => 'Telegram', 't.me' => 'Telegram',
    ];

    protected const UTM_NAMES = [
        'fb' => 'Facebook', 'facebook' => 'Facebook', 'meta' => 'Facebook', 'ig' => 'Instagram', 'instagram' => 'Instagram',
        'tiktok' => 'TikTok', 'tt' => 'TikTok', 'google' => 'Google', 'youtube' => 'YouTube', 'yt' => 'YouTube',
        'whatsapp' => 'WhatsApp', 'wa' => 'WhatsApp', 'twitter' => 'X (Twitter)', 'x' => 'X (Twitter)', 'snapchat' => 'Snapchat',
        'pinterest' => 'Pinterest', 'bing' => 'Bing', 'email' => 'Email', 'newsletter' => 'Email', 'sms' => 'SMS',
    ];

    protected const PAID_MEDIUMS = ['cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'paid-social', 'ads', 'ad', 'cpm', 'display', 'paidsearch', 'paid_search'];

    /**
     * @param  array  $query  URL query parameters of the landing page
     * @return array{source: string, medium: string, referrer_host: ?string, click_id: ?string}
     */
    public static function classify(array $query, ?string $referrer, string $ua, string $ownHost): array
    {
        $host = $referrer ? strtolower((string) parse_url($referrer, PHP_URL_HOST)) : null;
        $host = $host ? preg_replace('/^(www|m|l|lm|mobile)\./', '', $host) : null;
        if ($host && ($host === $ownHost || str_ends_with($host, '.'.$ownHost))) {
            $host = null; // moving around our own site
        }

        $result = ['source' => 'Direct', 'medium' => 'direct', 'referrer_host' => $host, 'click_id' => null];
        $utmSource = strtolower(trim((string) ($query['utm_source'] ?? '')));
        $utmMedium = strtolower(trim((string) ($query['utm_medium'] ?? '')));

        foreach (['gclid' => 'Google', 'gbraid' => 'Google', 'wbraid' => 'Google', 'ttclid' => 'TikTok', 'msclkid' => 'Bing', 'ScCid' => 'Snapchat'] as $param => $name) {
            if (! empty($query[$param])) {
                return ['source' => $name, 'medium' => 'paid', 'click_id' => substr($param, 0, 10)] + $result;
            }
        }

        if ($utmSource !== '') {
            $result['source'] = self::UTM_NAMES[$utmSource] ?? ucfirst(substr($utmSource, 0, 40));
            $result['medium'] = match (true) {
                in_array($utmMedium, self::PAID_MEDIUMS, true) => 'paid',
                in_array($utmMedium, ['email', 'newsletter'], true) || $result['source'] === 'Email' => 'email',
                $utmMedium === 'organic' => 'organic',
                $utmMedium === 'referral' => 'referral',
                default => 'social',
            };

            return $result;
        }

        if ($host) {
            foreach (self::SEARCH as $needle => $name) {
                if (str_contains($host, $needle)) {
                    return ['source' => $name, 'medium' => 'organic'] + $result;
                }
            }
            foreach (self::SOCIAL as $needle => $name) {
                if ($host === $needle || str_contains($host, $needle)) {
                    return ['source' => $name, 'medium' => 'social', 'click_id' => ! empty($query['fbclid']) ? 'fbclid' : null] + $result;
                }
            }

            return ['source' => substr($host, 0, 40), 'medium' => 'referral'] + $result;
        }

        // Social apps open links in their own browser and usually hide the referrer.
        $app = match (true) {
            (bool) preg_match('/Instagram/', $ua) => 'Instagram',
            (bool) preg_match('/FBAN|FBAV|FB_IAB/', $ua) || ! empty($query['fbclid']) => 'Facebook',
            (bool) preg_match('/musical_ly|BytedanceWebview|TikTok/i', $ua) => 'TikTok',
            (bool) preg_match('/Snapchat/', $ua) => 'Snapchat',
            (bool) preg_match('/Pinterest/', $ua) => 'Pinterest',
            default => null,
        };

        return $app ? ['source' => $app, 'medium' => 'social', 'click_id' => ! empty($query['fbclid']) ? 'fbclid' : null] + $result : $result;
    }
}
