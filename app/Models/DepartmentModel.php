<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentModel extends Model
{
    protected $table = 'departments';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'parent_id',
        'leader_id',
        'level',
        'path',
        'type',
        'status',
        'sort',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'parent_id' => 'integer',
        'leader_id' => 'integer',
        'level' => 'integer',
        'status' => 'integer',
        'sort' => 'integer',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];
}
