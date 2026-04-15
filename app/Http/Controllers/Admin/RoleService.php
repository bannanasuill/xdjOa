<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionModel;
use App\Models\RoleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RoleService extends Controller
{
    private const DATA_SCOPES = ['self', 'all', 'dept'];

    public function apiIndex(): JsonResponse
    {
        $rows = RoleModel::query()
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->get()
            ->map(fn (RoleModel $r) => $this->serializeRole($r))
            ->values();

        return response()->json(['data' => $rows]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRole(RoleModel $r): array
    {
        return [
            'id' => (int) $r->id,
            'name' => $r->name,
            'code' => $r->code,
            'data_scope' => $r->data_scope ?? 'self',
            'is_system' => (int) $r->is_system,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
            'permissions_text' => $this->rolePermissionsText($r),
        ];
    }

    /** 列表展示：按层级聚合权限名称（菜单 -> 子权限）。 */
    private function rolePermissionsText(RoleModel $r): string
    {
        if ($r->grantsAllPermissions()) {
            return '全部权限（系统内置）';
        }

        if (! RoleModel::isRolePermissionPivotPresent() || ! Schema::hasTable((new PermissionModel)->getTable())) {
            return '—';
        }

        $stored = $r->getStoredPermissionIds();
        if ($stored === []) {
            return '（未分配权限）';
        }

        $ids = PermissionModel::mergeAncestorMenuPermissionIds($stored);
        if ($ids === []) {
            return '（未分配权限）';
        }

        $rows = PermissionModel::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get(['id', 'parent_id', 'name'])
            ->all();

        if ($rows === []) {
            return '—';
        }

        $byId = [];
        $children = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $pid = $row->parent_id !== null ? (int) $row->parent_id : 0;
            $name = trim((string) ($row->name ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $byId[$id] = $name;
            if (! isset($children[$pid])) {
                $children[$pid] = [];
            }
            $children[$pid][] = $id;
        }

        if ($byId === []) {
            return '—';
        }

        $roots = [];
        foreach (array_keys($byId) as $id) {
            $parentId = 0;
            foreach ($children as $pid => $kids) {
                if (in_array($id, $kids, true)) {
                    $parentId = (int) $pid;
                    break;
                }
            }
            if ($parentId <= 0 || ! isset($byId[$parentId])) {
                $roots[] = $id;
            }
        }
        sort($roots);

        $rendered = [];
        foreach ($roots as $rootId) {
            $rendered[] = $this->renderPermissionNodeText($rootId, $byId, $children, []);
        }
        $rendered = array_values(array_filter(array_map('strval', $rendered), static fn (string $s) => trim($s) !== ''));

        return $rendered === [] ? '—' : implode('；', $rendered);
    }

    /**
     * @param  array<int, string>  $byId
     * @param  array<int, list<int>>  $children
     * @param  list<int>  $path
     */
    private function renderPermissionNodeText(int $id, array $byId, array $children, array $path): string
    {
        if (! isset($byId[$id]) || in_array($id, $path, true)) {
            return '';
        }

        $path[] = $id;
        $name = $byId[$id];
        $childIds = $children[$id] ?? [];
        sort($childIds);

        $childTexts = [];
        foreach ($childIds as $cid) {
            $text = $this->renderPermissionNodeText((int) $cid, $byId, $children, $path);
            if ($text !== '') {
                $childTexts[] = $text;
            }
        }

        if ($childTexts === []) {
            return $name;
        }

        return $name.'（'.implode('，', $childTexts).'）';
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::notIn(RoleModel::reservedBuiltinCodes()),
                Rule::unique('roles', 'code'),
            ],
            'data_scope' => ['nullable', 'string', 'max:20', Rule::in(self::DATA_SCOPES)],
        ], [
            'code.not_in' => '编码与系统预置角色（'.implode('、', RoleModel::reservedBuiltinCodes()).'）重复，请使用其他标识。',
        ]);

        $now = time();
        $role = new RoleModel;
        $role->name = $validated['name'];
        $role->code = $validated['code'];
        $role->data_scope = $validated['data_scope'] ?? 'self';
        $role->is_system = 0;
        $role->created_at = $now;
        $role->updated_at = $now;
        $role->save();

        return response()->json([
            'message' => '角色新增成功',
            'data' => $this->serializeRole($role->fresh()),
        ], 201);
    }

    public function apiUpdate(Request $request, RoleModel $role): JsonResponse
    {
        if ((int) $role->is_system === 1) {
            return response()->json(['message' => '系统角色为内置配置，不可编辑'], 403);
        }

        $codeUnique = Rule::unique('roles', 'code')->ignore($role->id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:50', $codeUnique],
            'data_scope' => ['nullable', 'string', 'max:20', Rule::in(self::DATA_SCOPES)],
        ]);

        $role->name = $validated['name'];
        $role->code = $validated['code'];
        $role->data_scope = $validated['data_scope'] ?? 'self';
        $role->updated_at = time();
        $role->save();

        return response()->json([
            'message' => '角色已更新',
            'data' => $this->serializeRole($role->fresh()),
        ]);
    }

    /**
     * 超级管理员（code=super_admin）且系统角色（is_system=1）：返回 permissions 表全部 id，
     * 不读、不写 role_permissions，避免每增权限都要维护 super_admin 等关联。
     */
    public function apiRolePermissionsIndex(RoleModel $role): JsonResponse
    {
        $isSuperAdmin = $role->code === RoleModel::CODE_SUPER_ADMIN;
        $isSystemRole = (int) $role->is_system === 1;

        if ($isSuperAdmin && $isSystemRole) {
            return response()->json([
                'permission_ids' => PermissionModel::orderedIds(),
                'system_full_access' => true,
            ]);
        }

        if (! RoleModel::isRolePermissionPivotPresent()) {
            return response()->json(['permission_ids' => []]);
        }

        $ids = PermissionModel::mergeAncestorMenuPermissionIds($role->getStoredPermissionIds());

        return response()->json(['permission_ids' => $ids]);
    }

    public function apiRolePermissionsSync(Request $request, RoleModel $role): JsonResponse
    {
        if ($role->code === RoleModel::CODE_SUPER_ADMIN && (int) $role->is_system === 1) {
            return response()->json(['message' => '超级管理员（系统内置）拥有全部权限，无需分配'], 403);
        }

        if (! RoleModel::isRolePermissionPivotPresent()) {
            return response()->json(['message' => '角色权限表不存在'], 503);
        }

        $validated = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $ids = PermissionModel::mergeAncestorMenuPermissionIds($validated['permission_ids']);

        $role->syncExplicitPermissionIds($ids);

        return response()->json(['message' => '角色权限已保存']);
    }
}
