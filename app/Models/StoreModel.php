<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 门店/总部打卡点（逻辑表名 stores，物理表可带前缀）。
 */
class StoreModel extends Model
{
    public const TYPE_STORE = 1;

    public const TYPE_HEADQUARTERS = 2;

    public const TYPE_WAREHOUSE = 3;

    /** @var string */
    protected $table = 'stores';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'dept_id',
        'code',
        'name',
        'store_type',
        'address',
        'longitude',
        'latitude',
        'radius',
        'wifi_mac',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'dept_id' => 'integer',
        'store_type' => 'integer',
        'radius' => 'integer',
        'status' => 'integer',
        'longitude' => 'decimal:6',
        'latitude' => 'decimal:6',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];
}
