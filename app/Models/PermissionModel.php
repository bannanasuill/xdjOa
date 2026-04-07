<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PermissionModel extends Model
{
    protected $table = 'permissions';

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'parent_id',
        'path',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'parent_id' => 'integer',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->orderBy('id');
    }

    /**
     * 全部权限主键（升序），用于超级管理员等「全量权限」展示。
     *
     * @return list<int>
     */
    public static function orderedIds(): array
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return [];
        }

        return static::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
