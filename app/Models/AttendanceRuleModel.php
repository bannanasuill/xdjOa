<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRuleModel extends Model
{
    protected $table = 'attendance_rules';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'position_id',
        'work_start_time',
        'work_end_time',
        'late_minutes',
        'early_minutes',
        'allow_remote',
        'need_photo',
        'priority',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'store_id' => 'integer',
        'position_id' => 'integer',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
        'allow_remote' => 'integer',
        'need_photo' => 'integer',
        'priority' => 'integer',
        'status' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(StoreModel::class, 'store_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionModel::class, 'position_id');
    }
}
