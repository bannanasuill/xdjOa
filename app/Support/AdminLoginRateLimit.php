<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * 后台登录失败限流（与 {@see \App\Http\Controllers\Admin\AuthService} 一致）。
 */
final class AdminLoginRateLimit
{
    public static function ipAccountKey(string $account, string $ip): string
    {
        return 'admin-login:'.sha1(Str::lower(trim($account)).'|'.$ip);
    }

    public static function userKey(int $userId): string
    {
        return 'admin-login-acc:'.$userId;
    }

    public static function clearIpAccount(string $account, string $ip): void
    {
        RateLimiter::clear(self::ipAccountKey($account, $ip));
    }

    public static function clearUser(int $userId): void
    {
        RateLimiter::clear(self::userKey($userId));
    }

    /** 登录成功时调用：同时清账号+IP 与全 IP 累计两类计数 */
    public static function clearAfterSuccessfulLogin(string $account, string $ip, int $userId): void
    {
        self::clearIpAccount($account, $ip);
        self::clearUser($userId);
    }
}
