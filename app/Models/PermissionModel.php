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

    /**
     * 为已选权限 id 补齐链路上的「菜单」祖先（parent_id 向上直到根）。
     * 仅勾接口时路由 meta / 侧栏依赖的 perm.admin.* 菜单码会一并写入，避免无法进页。
     *
     * @param  array<int, int|string>  $permissionIds
     * @return list<int>
     */
    public static function mergeAncestorMenuPermissionIds(array $permissionIds): array
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $permissionIds), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $rows = static::query()->get(['id', 'parent_id', 'type']);
        $byId = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            $parent = $r->parent_id;
            $byId[$id] = [
                'parent_id' => $parent !== null && (int) $parent > 0 ? (int) $parent : null,
                'type' => strtolower(trim((string) ($r->type ?? ''))),
            ];
        }

        $out = [];
        foreach ($ids as $startId) {
            $out[$startId] = true;
            $current = $startId;
            $guard = 0;
            while ($current > 0 && isset($byId[$current]) && $guard < 256) {
                $guard++;
                $parentId = $byId[$current]['parent_id'];
                if ($parentId === null || $parentId <= 0 || ! isset($byId[$parentId])) {
                    break;
                }
                if ($byId[$parentId]['type'] === 'menu') {
                    $out[$parentId] = true;
                }
                $current = $parentId;
            }
        }

        $merged = array_map('intval', array_keys($out));
        sort($merged);

        return $merged;
    }
}
