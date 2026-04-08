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

    public const KEY_SITE_NAME = 'site_name';

    /** 网站图标固定使用 public 下路径，不在后台配置。 */
    public const BRAND_LOGO_PUBLIC_PATH = 'images/logo.png';

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

    /** 按 logo 修改时间生成缓存版本号，避免浏览器长期缓存旧 favicon。 */
    public static function brandLogoCacheVersion(): int
    {
        $path = public_path(self::BRAND_LOGO_PUBLIC_PATH);

        return is_file($path) ? (int) filemtime($path) : 1;
    }

    public static function resolvedFaviconHref(): string
    {
        $base = asset(self::BRAND_LOGO_PUBLIC_PATH);
        $v = self::brandLogoCacheVersion();

        return $v > 0 ? $base.'?v='.$v : $base;
    }

    /** 与 public/favicon.ico（内容与 logo 一致）配套，便于浏览器默认请求 /favicon.ico。 */
    public static function faviconIcoHref(): string
    {
        $base = asset('favicon.ico');
        $v = self::brandLogoCacheVersion();

        return $v > 0 ? $base.'?v='.$v : $base;
    }

    public static function resolvedSiteName(): string
    {
        $v = self::get(self::KEY_SITE_NAME);

        return ($v !== null && trim($v) !== '') ? trim($v) : '洗多家后台';
    }
}
