<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStoreModel extends Model
{
    protected $table = 'user_stores';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'store_id',
        'position_id',
        'is_main',
        'start_date',
        'end_date',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'user_id' => 'integer',
        'store_id' => 'integer',
        'position_id' => 'integer',
        'is_main' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(StoreModel::class, 'store_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionModel::class, 'position_id');
    }
}
