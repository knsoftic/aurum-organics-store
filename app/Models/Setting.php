<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected const CACHE_KEY = 'store.settings';

    /** JSON-encoded settings (repeatable rows edited in the admin). */
    public const JSON_KEYS = ['faqs'];

    public static function allCached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $value = static::allCached()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (in_array($key, self::JSON_KEYS, true)) {
            return json_decode($value, true) ?: $default;
        }

        return $value;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = json_encode(array_values($value));
            }
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        static::flushCache();
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
