<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPositionModel extends Model
{
    protected $table = 'user_positions';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'position_id',
        'is_primary',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'user_id' => 'integer',
        'position_id' => 'integer',
        'is_primary' => 'integer',
        'created_at' => 'integer',
    ];
}
