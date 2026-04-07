<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DepartmentService extends Controller
{
    public function apiLeaderOptions(): JsonResponse
    {
        if (! Schema::hasTable('users')) {
            return response()->json(['data' => []]);
        }

        $rows = UserModel::query()
            ->where('status', 1)
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'real_name', 'account'])
            ->map(function (UserModel $u) {
                $rn = trim((string) ($u->real_name ?? ''));
                $ac = trim((string) ($u->account ?? ''));

                return [
                    'id' => (int) $u->id,
                    'label' => $rn !== '' ? ($ac !== '' ? "{$rn}（{$ac}）" : $rn) : ($ac !== '' ? $ac : ('#'.$u->id)),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function apiIndex(): JsonResponse
    {
        if (! Schema::hasTable('departments')) {
            return response()->json(['data' => []]);
        }

        $depts = DepartmentModel::query()
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get();

        $leaderIds = $depts->pluck('leader_id')->filter()->unique()->all();
        $leaders = [];
        if ($leaderIds !== [] && Schema::hasTable('users')) {
            $leaders = UserModel::query()
                ->whereIn('id', $leaderIds)
                ->get(['id', 'real_name', 'account'])
                ->keyBy('id');
        }

        $rows = $depts->map(function (DepartmentModel $d) use ($leaders) {
            $row = $this->serializeOne($d);
            $lid = $d->leader_id;
            if ($lid !== null && isset($leaders[$lid])) {
                $u = $leaders[$lid];
                $rn = trim((string) ($u->real_name ?? ''));
                $ac = trim((string) ($u->account ?? ''));
                $row['leader_label'] = $rn !== '' ? ($ac !== '' ? "{$rn}（{$ac}）" : $rn) : ($ac !== '' ? $ac : null);
            } else {
                $row['leader_label'] = null;
            }

            return $row;
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
            'leader_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : 0;
        if ($parentId > 0) {
            if (! DepartmentModel::query()->whereKey($parentId)->exists()) {
                throw ValidationException::withMessages(['parent_id' => '上级部门不存在。']);
            }
        }

        $now = time();
        $leaderId = $validated['leader_id'] ?? null;
        if ($leaderId === null || (int) $leaderId < 1) {
            $leaderId = null;
        } else {
            $leaderId = (int) $leaderId;
        }

        $d = new DepartmentModel;
        $d->name = trim((string) $validated['name']);
        $d->parent_id = $parentId;
        $d->leader_id = $leaderId;
        $d->level = 1;
        $d->path = '';
        $d->sort = isset($validated['sort']) ? (int) $validated['sort'] : 0;
        $d->status = isset($validated['status']) ? (int) $validated['status'] : 1;
        $d->created_at = $now;
        $d->updated_at = $now;
        $d->save();

        $this->applyHierarchy($d);

        return response()->json([
            'message' => '部门新增成功',
            'data' => $this->serializeOne($d->fresh()),
        ], 201);
    }

    public function apiUpdate(Request $request, DepartmentModel $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
            'leader_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : 0;
        if ($parentId > 0) {
            if (! DepartmentModel::query()->whereKey($parentId)->exists()) {
                throw ValidationException::withMessages(['parent_id' => '上级部门不存在。']);
            }
        }

        if ($this->wouldCreateCycle($department, $parentId)) {
            throw ValidationException::withMessages(['parent_id' => '不能将上级设为自身或下级部门。']);
        }

        $leaderId = array_key_exists('leader_id', $validated) ? $validated['leader_id'] : $department->leader_id;
        if ($leaderId === null || (int) $leaderId < 1) {
            $leaderId = null;
        } else {
            $leaderId = (int) $leaderId;
        }

        $oldParentId = (int) $department->parent_id;

        $department->name = trim((string) $validated['name']);
        $department->parent_id = $parentId;
        $department->leader_id = $leaderId;
        if (array_key_exists('sort', $validated)) {
            $department->sort = (int) $validated['sort'];
        }
        if (array_key_exists('status', $validated)) {
            $department->status = (int) $validated['status'];
        }
        $department->updated_at = time();
        $department->save();

        $this->applyHierarchy($department->fresh());
        if ($parentId !== $oldParentId) {
            $this->refreshChildDepartments((int) $department->id);
        }

        return response()->json([
            'message' => '部门已更新',
            'data' => $this->serializeOne($department->fresh()),
        ]);
    }

    public function apiPatchStatus(Request $request, DepartmentModel $department): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $department->status = (int) $validated['status'];
        $department->updated_at = time();
        $department->save();

        return response()->json([
            'message' => '状态已更新',
            'data' => $this->serializeOne($department->fresh()),
        ]);
    }

    public function apiPatchSort(Request $request, DepartmentModel $department): JsonResponse
    {
        $validated = $request->validate([
            'sort' => ['required', 'integer', 'min:0'],
        ]);

        $department->sort = (int) $validated['sort'];
        $department->updated_at = time();
        $department->save();

        return response()->json([
            'message' => '排序已更新',
            'data' => $this->serializeOne($department->fresh()),
        ]);
    }

    public function apiDestroy(DepartmentModel $department): JsonResponse
    {
        $id = (int) $department->id;

        if (DepartmentModel::query()->where('parent_id', $id)->exists()) {
            return response()->json(['message' => '存在子部门，请先删除或调整子部门后再删除。'], 422);
        }

        if (Schema::hasTable('positions') && PositionModel::query()->where('dept_id', $id)->exists()) {
            return response()->json(['message' => '该部门下仍有职务，请先删除相关职务后再删除部门。'], 422);
        }

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('user_departments')) {
                DB::table('user_departments')->where('dept_id', $id)->delete();
            }
            DepartmentModel::query()->whereKey($id)->delete();
        });

        return response()->json(['message' => '部门已删除']);
    }

    private function applyHierarchy(DepartmentModel $dept): void
    {
        $pid = (int) $dept->parent_id;
        if ($pid <= 0) {
            $dept->level = 1;
            $dept->path = (string) $dept->id;
        } else {
            $parent = DepartmentModel::query()->find($pid);
            if ($parent === null) {
                $dept->parent_id = 0;
                $dept->level = 1;
                $dept->path = (string) $dept->id;
            } else {
                $dept->level = (int) $parent->level + 1;
                $base = trim((string) ($parent->path ?? ''));
                $dept->path = $base !== '' ? $base.'/'.$dept->id : (string) $dept->id;
            }
        }
        $dept->updated_at = time();
        $dept->save();
    }

    private function refreshChildDepartments(int $parentId): void
    {
        $children = DepartmentModel::query()->where('parent_id', $parentId)->get();
        foreach ($children as $child) {
            $this->applyHierarchy($child);
            $this->refreshChildDepartments((int) $child->id);
        }
    }

    private function wouldCreateCycle(DepartmentModel $dept, int $newParentId): bool
    {
        if ($newParentId <= 0) {
            return false;
        }

        if ($newParentId === (int) $dept->id) {
            return true;
        }

        $walk = $newParentId;
        $guard = 0;
        while ($walk > 0 && $guard < 2000) {
            if ($walk === (int) $dept->id) {
                return true;
            }
            $p = DepartmentModel::query()->find($walk);
            if ($p === null) {
                break;
            }
            $walk = (int) $p->parent_id;
            $guard++;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(DepartmentModel $d): array
    {
        return [
            'id' => (int) $d->id,
            'name' => $d->name,
            'parent_id' => (int) $d->parent_id,
            'leader_id' => $d->leader_id !== null ? (int) $d->leader_id : null,
            'type' => $d->type !== null && $d->type !== '' ? (string) $d->type : 'department',
            'level' => (int) $d->level,
            'path' => $d->path,
            'sort' => (int) $d->sort,
            'status' => (int) $d->status,
            'created_at' => $d->created_at !== null ? (int) $d->created_at : null,
            'updated_at' => $d->updated_at !== null ? (int) $d->updated_at : null,
        ];
    }
}
