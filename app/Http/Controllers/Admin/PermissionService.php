<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PermissionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PermissionService extends Controller
{
    public function apiIndex(): JsonResponse
    {
        $all = PermissionModel::query()->orderBy('id')->get();
        $roots = $all->filter(function (PermissionModel $p) {
            return $p->parent_id === null || (int) $p->parent_id === 0;
        });

        $data = $roots->map(fn (PermissionModel $p) => $this->permissionNode($p, $all))->values();

        return response()->json(['data' => $data]);
    }

    /**
     * @param  Collection<int, PermissionModel>  $all
     * @return array<string, mixed>
     */
    private function permissionNode(PermissionModel $item, Collection $all): array
    {
        $children = $all
            ->filter(fn (PermissionModel $p) => (int) ($p->parent_id ?? 0) === (int) $item->id)
            ->values()
            ->map(fn (PermissionModel $p) => $this->permissionNode($p, $all))
            ->all();

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'code' => $item->code,
            'type' => $item->type,
            'path' => $item->path,
            'parent_id' => $item->parent_id !== null ? (int) $item->parent_id : null,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'children' => $children,
        ];
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $this->validatedPermissionPayload($request, null);
        $now = time();
        $permission = new PermissionModel;
        $permission->fill($validated);
        $permission->created_at = $now;
        $permission->updated_at = $now;
        $permission->save();

        return response()->json([
            'message' => '权限新增成功',
            'data' => ['id' => (int) $permission->id],
        ], 201);
    }

    public function apiUpdate(Request $request, PermissionModel $permission): JsonResponse
    {
        $validated = $this->validatedPermissionPayload($request, $permission);

        $newParent = $validated['parent_id'] ?? null;
        if ($this->wouldCreateCycle($permission, $newParent)) {
            return response()->json(['message' => '不能将父级设为自身或子权限'], 422);
        }

        $validated['updated_at'] = time();
        $permission->fill($validated);
        $permission->save();

        return response()->json(['message' => '权限已更新']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPermissionPayload(Request $request, ?PermissionModel $existing): array
    {
        $codeUnique = Rule::unique('permissions', 'code');
        if ($existing) {
            $codeUnique->ignore($existing->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:100', $codeUnique],
            'type' => ['required', 'string', 'max:20', Rule::in(['menu', 'button', 'api'])],
            'path' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:permissions,id'],
        ]);

        if (array_key_exists('parent_id', $validated) && ($validated['parent_id'] === 0 || $validated['parent_id'] === '0')) {
            $validated['parent_id'] = null;
        }

        return $validated;
    }

    private function wouldCreateCycle(PermissionModel $permission, ?int $newParentId): bool
    {
        if ($newParentId === null || $newParentId === 0) {
            return false;
        }

        if ((int) $newParentId === (int) $permission->id) {
            return true;
        }

        $current = PermissionModel::query()->find($newParentId);
        $guard = 0;

        while ($current && $guard++ < 100) {
            if ((int) $current->id === (int) $permission->id) {
                return true;
            }
            $pid = $current->parent_id;
            if ($pid === null || (int) $pid === 0) {
                break;
            }
            $current = PermissionModel::query()->find((int) $pid);
        }

        return false;
    }
}
