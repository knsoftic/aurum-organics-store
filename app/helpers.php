<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Read a store setting (cached). Settings are edited from Admin → Settings.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount, bool $withSymbol = true): string
    {
        $amount = (float) $amount;
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;
        $formatted = number_format($amount, $decimals);

        return $withSymbol ? trim(setting('currency_symbol', 'Rs.').' '.$formatted) : $formatted;
    }
}

if (! function_exists('media_url')) {
    /**
     * Public URL for a file stored on the "uploads" disk (public/uploads).
     */
    function media_url(?string $path, ?string $fallback = null): string
    {
        if (! $path) {
            return $fallback ?? asset('images/placeholder.svg');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('uploads/'.ltrim($path, '/'));
    }
}

if (! function_exists('safe_html')) {
    /**
     * Admin-authored rich text: keep simple formatting tags, drop scripts,
     * inline event handlers and javascript: links.
     */
    function safe_html(?string $html): string
    {
        if (! $html) {
            return '';
        }

        if (strip_tags($html) === $html) {
            return nl2br(e($html));
        }

        $clean = strip_tags($html, '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><h4><a><blockquote><span><hr>');
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean);
        $clean = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '$1="#"', $clean);

        return $clean;
    }
}

if (! function_exists('whatsapp_link')) {
    function whatsapp_link(?string $message = null): ?string
    {
        $number = preg_replace('/\D+/', '', (string) setting('whatsapp_number'));
        if (! $number) {
            return null;
        }
        if (str_starts_with($number, '0')) {
            $number = '92'.substr($number, 1);
        }

        return 'https://wa.me/'.$number.($message ? '?text='.rawurlencode($message) : '');
    }
}

if (! function_exists('country_flag')) {
    /** Emoji flag for a two-letter country code (empty when unknown). */
    function country_flag(?string $code): string
    {
        if (! $code || ! preg_match('/^[A-Z]{2}$/', $code = strtoupper($code))) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }
}

if (! function_exists('country_name')) {
    function country_name(?string $code): string
    {
        if (! $code) {
            return 'Unknown';
        }

        return config('countries.'.strtoupper($code), strtoupper($code));
    }
}

if (! function_exists('media_webp')) {
    /** Small WebP copy of an uploaded photo (thumbnails, logo icon). */
    function media_webp(?string $url, int $width): string
    {
        return \App\Services\ImageOptimizer::url($url, $width);
    }
}

if (! function_exists('inline_css')) {
    /** A public stylesheet, lightly minified, for inlining in <head> (cached until the file changes). */
    function inline_css(string $file): string
    {
        $path = public_path($file);
        if (! is_file($path)) {
            return '';
        }

        return \Illuminate\Support\Facades\Cache::rememberForever('inline_css.'.md5($file).'.'.filemtime($path), function () use ($path) {
            $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($path));
            $css = preg_replace('/\s+/', ' ', $css);
            $css = preg_replace('/\s*([{};])\s*/', '$1', $css);

            return str_replace('</style', '<\/style', trim($css));
        });
    }
}
