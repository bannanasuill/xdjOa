<?php

namespace App\Support;

use App\Models\UserModel;
use Illuminate\Support\Carbon;

/**
 * 未手填账号时：YYYYMMDD + 当日递增序号（1 起），与库里已有账号不重复。
 */
final class DefaultUserAccount
{
    public static function uniqueForToday(): string
    {
        $prefix = Carbon::now()->format('Ymd');

        for ($n = 1; $n < 100000; $n++) {
            $candidate = $prefix.$n;
            if (strtolower($candidate) === 'admin') {
                continue;
            }
            if (! UserModel::query()->where('account', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('无法生成唯一账号，请手动指定账号。');
    }
}
