<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuModel extends Model
{
    protected $table = 'menus';

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'permission_code',
        'path',
        'component',
        'parent_id',
        'icon',
        'sort',
        'visible',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'parent_id' => 'integer',
        'sort' => 'integer',
        'visible' => 'integer',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->orderBy('sort')
            ->orderBy('id');
    }
}
