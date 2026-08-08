<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        return static::all()->firstWhere('key', $key)?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    /** All settings as a key=>value map (cached). */
    public static function map(): array
    {
        return Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());
    }

    /**
     * A secret setting, encrypted at rest.
     *
     * Same treatment nodes.daemon_secret gets: a database leak must not hand
     * over a live credential for somebody else's DNS account. Kept out of the
     * config overlay deliberately, so the plaintext exists only for the moment
     * a call is being made.
     */
    public static function putSecret(string $key, ?string $value): void
    {
        static::put($key, filled($value) ? Crypt::encryptString($value) : null);
    }

    public static function secret(string $key): ?string
    {
        $stored = static::map()[$key] ?? null;

        if (! filled($stored)) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable $e) {
            // Wrong APP_KEY, or a value written before it was a secret. Either
            // way it is not a usable credential, and throwing here would take
            // out every page that reads the setting.
            return null;
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }
}
