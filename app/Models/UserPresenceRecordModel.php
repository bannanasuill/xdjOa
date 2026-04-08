<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPresenceRecordModel extends Model
{
    public const TYPE_ARRIVAL = 1;
    public const TYPE_OUTING = 2;

    public const STATUS_VALID = 1;
    public const STATUS_VOID = 0;

    public $timestamps = false;

    protected $table = 'user_presence_records';

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

    protected $casts = [
        'user_id' => 'integer',
        'record_type' => 'integer',
        'start_at' => 'integer',
        'end_at' => 'integer',
        'source' => 'integer',
        'status' => 'integer',
        'longitude' => 'decimal:6',
        'latitude' => 'decimal:6',
    ];
}

