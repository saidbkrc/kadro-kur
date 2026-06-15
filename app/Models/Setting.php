<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** İstek başına tek sorgu: tüm ayarlar bir kez yüklenir. */
    protected static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (static::$cache === null) {
            static::$cache = static::query()->pluck('value', 'key')->all();
        }

        return static::$cache[$key] ?? $default;
    }

    public static function int(string $key, int $default): int
    {
        $value = static::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache[$key] = $value;
    }

    /** İstek içi cache'i sıfırlar (testlerde süreç paylaşıldığı için gerekli). */
    public static function flushCache(): void
    {
        static::$cache = null;
    }
}
