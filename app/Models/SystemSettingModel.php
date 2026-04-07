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
        'name',
        'type',
        'sort',
        'remark',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array{group_name: string, name: string, type: string, sort: int, remark: ?string}
     */
    protected static function metaForKey(string $key): array
    {
        return match ($key) {
            self::KEY_DEFAULT_USER_PASSWORD => [
                'group_name' => 'system',
                'name' => '新增用户默认密码',
                'type' => 'string',
                'sort' => 10,
                'remark' => '后台新增用户未填写密码时使用；不少于 6 位。',
            ],
            self::KEY_SITE_FAVICON => [
                'group_name' => 'system',
                'name' => '网站图标',
                'type' => 'string',
                'sort' => 20,
                'remark' => '后台与登录页 favicon，支持 URL 或站内路径',
            ],
            self::KEY_SITE_NAME => [
                'group_name' => 'system',
                'name' => '站点名称',
                'type' => 'string',
                'sort' => 30,
                'remark' => '浏览器标题与登录页展示名称',
            ],
            default => [
                'group_name' => 'system',
                'name' => $key,
                'type' => 'string',
                'sort' => 0,
                'remark' => null,
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
