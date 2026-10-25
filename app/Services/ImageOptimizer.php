<?php

namespace App\Services;

use Throwable;

/**
 * Resized WebP copies of uploaded photos (public/uploads/_opt/{width}/…webp) so phones
 * download a small image and desktops a sharp one. Copies are made on first use and
 * refreshed when the original changes; `php artisan images:optimize` makes them all at once.
 */
class ImageOptimizer
{
    public const WIDTHS = [400, 600, 800, 1200];

    protected const DIR = '_opt';

    protected const TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    protected static array $dims = [];

    /** Path inside public/uploads for an uploads URL or path, or null when it is not an optimisable upload. */
    public static function relative(?string $src): ?string
    {
        if (! $src) {
            return null;
        }
        $base = rtrim(asset('uploads'), '/').'/';
        if (str_starts_with($src, $base)) {
            $src = substr($src, strlen($base));
        } elseif (preg_match('#^(https?:)?//#', $src)) {
            return null;
        }
        $src = ltrim(strtok($src, '?#'), '/');
        if ($src === '' || str_starts_with($src, self::DIR.'/') || str_contains($src, '..')
            || ! in_array(strtolower(pathinfo($src, PATHINFO_EXTENSION)), self::TYPES, true)) {
            return null;
        }

        return is_file(self::path($src)) ? $src : null;
    }

    /** [width, height] of the original image. */
    public static function dimensions(string $relative): ?array
    {
        return self::$dims[$relative] ??= (function () use ($relative) {
            $size = @getimagesize(self::path($relative));

            return $size ? [$size[0], $size[1]] : null;
        })();
    }

    /** Public URL of a WebP copy at (at most) the given width; falls back to the original. */
    public static function url(?string $src, int $width): string
    {
        $relative = self::relative($src);
        if (! $relative) {
            return (string) $src;
        }
        $variant = self::variant($relative, $width);

        return $variant ? asset('uploads/'.$variant) : (string) $src;
    }

    /**
     * Attributes for a responsive <img>: src, srcset, width, height.
     *
     * @return array{src: string, srcset: ?string, width: ?int, height: ?int}
     */
    public static function responsive(?string $src, ?array $widths = null): array
    {
        $relative = self::relative($src);
        $dims = $relative ? self::dimensions($relative) : null;
        if (! $relative || ! $dims) {
            return ['src' => (string) $src, 'srcset' => null, 'width' => null, 'height' => null];
        }

        $set = [];
        foreach ($widths ?? self::WIDTHS as $w) {
            $actual = min($w, $dims[0]);
            if (isset($set[$actual]) || ! ($variant = self::variant($relative, $actual))) {
                continue;
            }
            $set[$actual] = asset('uploads/'.$variant).' '.$actual.'w';
            if ($actual === $dims[0]) {
                break; // no point making copies larger than the original
            }
        }
        if (! $set) {
            return ['src' => (string) $src, 'srcset' => null, 'width' => $dims[0], 'height' => $dims[1]];
        }
        ksort($set);
        $middle = array_values($set)[min(1, count($set) - 1)];

        return ['src' => strtok($middle, ' '), 'srcset' => implode(', ', $set), 'width' => $dims[0], 'height' => $dims[1]];
    }

    /** Creates (or reuses) the WebP copy and returns its path inside uploads. */
    public static function variant(string $relative, int $width): ?string
    {
        $source = self::path($relative);
        $target = self::DIR.'/'.$width.'/'.preg_replace('/\.(jpe?g|png|webp)$/i', '', $relative).'.webp';
        $targetPath = self::path($target);

        if (is_file($targetPath) && filemtime($targetPath) >= filemtime($source)) {
            return $target;
        }
        if (! function_exists('imagewebp')) {
            return null;
        }

        try {
            $image = match (strtolower(pathinfo($relative, PATHINFO_EXTENSION))) {
                'png' => @imagecreatefrompng($source),
                'webp' => @imagecreatefromwebp($source),
                default => @imagecreatefromjpeg($source),
            };
            if (! $image) {
                return null;
            }
            $w = imagesx($image);
            $h = imagesy($image);
            $newW = min($width, $w);
            $newH = (int) round($h * $newW / $w);

            $canvas = imagecreatetruecolor($newW, $newH);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);

            @mkdir(dirname($targetPath), 0775, true);
            $tmp = $targetPath.'.'.getmypid().'.tmp';
            imagewebp($canvas, $tmp, $newW <= 200 ? 72 : 78);
            imagedestroy($image);
            imagedestroy($canvas);
            rename($tmp, $targetPath);

            return $target;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function path(string $relative): string
    {
        return public_path('uploads/'.$relative);
    }
}
