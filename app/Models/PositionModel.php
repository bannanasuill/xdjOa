<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionModel extends Model
{
    protected $table = 'positions';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'dept_id',
        'level',
        'status',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'dept_id' => 'integer',
        'level' => 'integer',
        'status' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'dept_id');
    }
}
