<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * 用户到岗/外出时间轴记录（逻辑表名 user_presence_records，物理表可带数据库前缀）。
 */
class UserPresenceRecordModel extends Authenticatable
{
    /** 记录类型：到岗（点位）。 */
    public const TYPE_ARRIVAL = 1;

    /** 记录类型：外出（区间）。 */
    public const TYPE_OUTING = 2;

    /** 记录类型：下班。（点位）*/
    public const TYPE_LEAVE = 3;

    /** 状态：有效。 */
    public const STATUS_VALID = 1;

    public $timestamps = false;

    /** @var string */
    protected $table = 'user_presence_records';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'work_date',
        'record_type',
        'start_at',
        'end_at',
        'source',
        'status',
        'reason',
        'address',
        'longitude',
        'latitude',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'work_date' => 'string',
        'record_type' => 'integer',
        'start_at' => 'integer',
        'end_at' => 'integer',
        'source' => 'integer',
        'status' => 'integer',
        'longitude' => 'decimal:6',
        'latitude' => 'decimal:6',
    ];
}

