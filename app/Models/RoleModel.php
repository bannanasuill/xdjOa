<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleModel extends Model
{
    /** 初始化三类内置角色 code（新建自定义角色时不可占用这些编码） */
    public const CODE_SUPER_ADMIN = 'super_admin';

    public const CODE_ADMIN = 'admin';

    public const CODE_EMPLOYEE = 'employee';

    /** @deprecated 使用 CODE_SUPER_ADMIN */
    public const SYSTEM_ADMIN_CODE = self::CODE_SUPER_ADMIN;

    protected $table = 'roles';

    /**
     * @return list<string>
     */
    public static function reservedBuiltinCodes(): array
    {
        return [self::CODE_SUPER_ADMIN, self::CODE_ADMIN, self::CODE_EMPLOYEE];
    }

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'data_scope',
        'is_system',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_system' => 'integer',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PermissionModel::class,
            'role_permissions',
            'role_id',
            'permission_id'
        );
    }

    /** 仅超级管理员且系统内置角色视为拥有全部权限节点（不依赖 role_permissions 行）。 */
    public function grantsAllPermissions(): bool
    {
        return (int) $this->is_system === 1 && ($this->code ?? '') === self::CODE_SUPER_ADMIN;
    }

    /** 按 code 查主键；表不存在或其它原因无记录时返回 null。 */
    public static function findIdByCode(string $code): ?int
    {
        if (! static::isTablePresent()) {
            return null;
        }

        $id = static::query()->where('code', $code)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<int, int>  $ids
     */
    public static function countWhereIdIn(array $ids): int
    {
        if ($ids === [] || ! static::isTablePresent()) {
            return 0;
        }

        return static::query()->whereIn('id', $ids)->count();
    }

    /**
     * @param  array<int, int>  $ids
     */
    public static function existsWithCodeInIds(string $code, array $ids): bool
    {
        if ($ids === [] || ! static::isTablePresent()) {
            return false;
        }

        return static::query()->where('code', $code)->whereIn('id', $ids)->exists();
    }

    /** 角色表是否已迁移（校验、查询前可调用）。 */
    public static function isTablePresent(): bool
    {
        return Schema::hasTable((new static)->getTable());
    }

    /** role_permissions 中间表是否存在。 */
    public static function isRolePermissionPivotPresent(): bool
    {
        return Schema::hasTable('role_permissions');
    }

    /**
     * 非「系统全量」角色在关联表里已分配的 permission_id（升序）。
     *
     * @return list<int>
     */
    public function getStoredPermissionIds(): array
    {
        if (! static::isRolePermissionPivotPresent()) {
            return [];
        }

        return DB::table('role_permissions')
            ->where('role_id', $this->id)
            ->orderBy('permission_id')
            ->pluck('permission_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * 覆盖写入当前角色在 role_permissions 中的行（非系统内置全量角色使用）。
     *
     * @param  array<int, int>  $permissionIds
     */
    public function syncExplicitPermissionIds(array $permissionIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $permissionIds)));

        $pivot = 'role_permissions';
        DB::transaction(function () use ($ids, $pivot) {
            DB::table($pivot)->where('role_id', $this->id)->delete();
            if ($ids !== []) {
                $rows = array_map(
                    fn (int $pid) => ['role_id' => (int) $this->id, 'permission_id' => $pid],
                    $ids
                );
                DB::table($pivot)->insert($rows);
            }
        });

        $this->updated_at = time();
        $this->save();
    }

    /**
     * 用户分配角色下拉数据：排除 super_admin（由接口外维护）。
     *
     * @return list<array{id:int, name:string, code:?string, is_system:int}>
     */
    public static function assignableForUserPicker(): array
    {
        if (! static::isTablePresent()) {
            return [];
        }

        return static::query()
            ->where(function ($q) {
                $q->whereNull('code')
                    ->orWhere('code', '!=', self::CODE_SUPER_ADMIN);
            })
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->get(['id', 'name', 'code', 'is_system'])
            ->map(static function ($r) {
                return [
                    'id' => (int) $r->id,
                    'name' => $r->name,
                    'code' => $r->code,
                    'is_system' => (int) $r->is_system,
                ];
            })
            ->values()
            ->all();
    }
}
