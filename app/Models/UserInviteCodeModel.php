<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserInviteCodeModel extends Model
{
    protected $table = 'user_invite_codes';

    public $timestamps = false;

    /** 邀请码尚未通过后台审核，不可用于注册 */
    public const AUDIT_PENDING = 0;

    /** 邀请码已通过审核，可用于注册 */
    public const AUDIT_APPROVED = 1;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'dept_id',
        'position_id',
        'store_id',
        'register_status',
        'invite_audit_status',
        'valid_hours',
        'expires_at',
        'used_at',
        'used_user_id',
        'created_by',
        'created_at',
        'updated_at',
    ];

    /**
     * 生成唯一邀请码（大写字母 + 数字，不含易混字符）。
     */
    public static function generateUniqueCode(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = max(6, min(16, $length));

        for ($i = 0; $i < 20; $i++) {
            $buf = '';
            for ($j = 0; $j < $len; $j++) {
                $buf .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $exists = DB::table((new static)->getTable())->where('code', $buf)->exists();
            if (! $exists) {
                return $buf;
            }
        }

        return strtoupper(substr(bin2hex(random_bytes(8)), 0, $len));
    }

    public static function inviteAuditLabel(int $audit): string
    {
        return match ($audit) {
            self::AUDIT_PENDING => '待生效',
            self::AUDIT_APPROVED => '已通过',
            default => '未知',
        };
    }
}

