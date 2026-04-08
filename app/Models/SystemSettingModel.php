<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * 系统级配置项，对应表 configs（带前缀后为 xdj_configs）。
 */
class SystemSettingModel extends Model
{
    public const KEY_DEFAULT_USER_PASSWORD = 'default_user_password';

    public const KEY_SITE_FAVICON = 'site_favicon';

    public const KEY_SITE_NAME = 'site_name';

    protected $table = 'configs';

    public $timestamps = false;

    protected $fillable = [
        'config_key',
        'config_value',
        'group_name',
        'created_at',
        'updated_at',
    ];

    /**
     * 写入数据库时仅设置分组；展示文案由后台界面与注释维护，不再落表。
     *
     * @return array{group_name: string}
     */
    protected static function metaForKey(string $key): array
    {
        return match ($key) {
            self::KEY_DEFAULT_USER_PASSWORD,
            self::KEY_SITE_FAVICON,
            self::KEY_SITE_NAME => [
                'group_name' => 'system',
            ],
            default => [
                'group_name' => 'system',
            ],
        };
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (! Schema::hasTable('configs')) {
            return $default;
        }

        $row = static::query()->where('config_key', $key)->value('config_value');
        if ($row === null) {
            return $default;
        }
        $s = (string) $row;

        return $s !== '' ? $s : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        if (! Schema::hasTable('configs')) {
            return;
        }

        $now = time();
        $meta = static::metaForKey($key);
        $existing = static::query()->where('config_key', $key)->first();

        if ($existing) {
            $existing->fill(array_merge($meta, [
                'config_value' => $value,
                'updated_at' => $now,
            ]));
            $existing->save();

            return;
        }

        static::query()->create(array_merge($meta, [
            'config_key' => $key,
            'config_value' => $value,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    public static function deleteKey(string $key): void
    {
        if (! Schema::hasTable('configs')) {
            return;
        }
        static::query()->where('config_key', $key)->delete();
    }

    public static function resolvedFaviconHref(): string
    {
        $v = self::get(self::KEY_SITE_FAVICON);
        if ($v === null || trim($v) === '') {
            return asset('favicon.ico');
        }
        $v = trim($v);
        if (str_starts_with($v, 'http://') || str_starts_with($v, 'https://') || str_starts_with($v, '//')) {
            return $v;
        }

        return asset(ltrim($v, '/'));
    }

    public static function resolvedSiteName(): string
    {
        $v = self::get(self::KEY_SITE_NAME);

        return ($v !== null && trim($v) !== '') ? trim($v) : '洗多家后台';
    }
}
