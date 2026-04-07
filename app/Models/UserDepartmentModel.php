<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDepartmentModel extends Model
{
    protected $table = 'user_departments';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'dept_id',
        'is_primary',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'user_id' => 'integer',
        'dept_id' => 'integer',
        'is_primary' => 'integer',
        'created_at' => 'integer',
    ];
}
