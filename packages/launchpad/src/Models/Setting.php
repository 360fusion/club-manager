<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Launchpad\Admin\AdminLog;
use Launchpad\Support\SettingsCache;

class Setting extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        // a change through the model, however it is made, makes the next read start over
        static::saved(fn () => app(SettingsCache::class)->forget());
        static::deleted(fn () => app(SettingsCache::class)->forget());

        // what staff change is in the admin log (it keeps no value of a setting whose name says it is a secret)
        static::created(fn (Setting $setting) => AdminLog::settingChanged((string) $setting->key, null, $setting->value));
        static::updated(fn (Setting $setting) => AdminLog::settingChanged((string) $setting->key, $setting->getOriginal('value'), $setting->value));
        static::deleted(fn (Setting $setting) => AdminLog::settingChanged((string) $setting->key, $setting->getOriginal('value'), null));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $rows = app(SettingsCache::class)->all(function () {
            try {
                return self::query()->pluck('value', 'key')->all();
            } catch (QueryException) {
                // no table yet (before the first migrate, or while the app is being built): the defaults apply, and the page still answers
                return [];
            }
        });

        if (! array_key_exists($key, $rows)) {
            return $default;
        }

        $decoded = json_decode((string) $rows[$key], true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $rows[$key];
    }

    /** Take a setting away: it goes back to its default */
    public static function unset(string $key): void
    {
        // each row through the model, so the deletion is in the log
        self::where('key', $key)->get()->each->delete();
        app(SettingsCache::class)->forget();
    }

    public static function set(string $key, mixed $value): self
    {
        $stored = is_array($value) || is_object($value) ? json_encode($value) : $value;

        return self::updateOrCreate(['key' => $key], ['value' => $stored]);
    }
}
