<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PositionService extends Controller
{
    public function apiDeptOptions(): JsonResponse
    {
        if (! Schema::hasTable('departments')) {
            return response()->json(['data' => []]);
        }

        $rows = DepartmentModel::query()
            ->where('status', 1)
            ->orderBy('sort')
            ->orderByDesc('id')
            ->get(['id', 'name', 'path'])
            ->map(fn (DepartmentModel $d) => [
                'id' => (int) $d->id,
                'name' => $d->name,
                'label' => ($d->name !== null && $d->name !== '') ? $d->name : ('#'.$d->id),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function apiIndex(): JsonResponse
    {
        if (! Schema::hasTable('positions')) {
            return response()->json(['data' => []]);
        }

        $positions = PositionModel::query()
            ->orderBy('dept_id')
            ->orderByDesc('level')
            ->orderByDesc('id')
            ->get();

        $deptIds = $positions->pluck('dept_id')->unique()->filter()->all();
        $depts = [];
        if ($deptIds !== [] && Schema::hasTable('departments')) {
            $depts = DepartmentModel::query()
                ->whereIn('id', $deptIds)
                ->get(['id', 'name'])
                ->keyBy('id');
        }

        $rows = $positions->map(function (PositionModel $p) use ($depts) {
            $row = $this->serializeOne($p);
            $d = $depts[$p->dept_id] ?? null;
            $row['dept_name'] = $d !== null ? $d->name : null;

            return $row;
        })->values();

        return response()->json(['data' => $rows]);
    }

    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('positions', 'code')],
            'dept_id' => ['required', 'integer', 'min:1', 'exists:departments,id'],
            'level' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ], [
            'code.regex' => '职位标识仅允许字母、数字、下划线与中划线。',
        ]);

        if (Schema::hasTable('departments')) {
            $dept = DepartmentModel::query()->find($validated['dept_id']);
            if ($dept === null || (int) $dept->status !== 1) {
                return response()->json(['message' => '请选择已启用的部门。'], 422);
            }
        }

        $now = time();
        $p = new PositionModel;
        $p->name = trim((string) $validated['name']);
        $p->code = trim((string) $validated['code']);
        $p->dept_id = (int) $validated['dept_id'];
        $p->level = isset($validated['level']) ? (int) $validated['level'] : 1;
        $p->status = isset($validated['status']) ? (int) $validated['status'] : 1;
        $p->created_at = $now;
        $p->updated_at = $now;
        $p->save();

        return response()->json([
            'message' => '职务新增成功',
            'data' => $this->serializeOne($p->fresh()),
        ], 201);
    }

    public function apiUpdate(Request $request, PositionModel $position): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'dept_id' => ['required', 'integer', 'min:1', 'exists:departments,id'],
            'level' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        if (Schema::hasTable('departments')) {
            $dept = DepartmentModel::query()->find($validated['dept_id']);
            if ($dept === null || (int) $dept->status !== 1) {
                return response()->json(['message' => '请选择已启用的部门。'], 422);
            }
        }

        $position->name = trim((string) $validated['name']);
        $position->dept_id = (int) $validated['dept_id'];
        if (array_key_exists('level', $validated)) {
            $position->level = (int) $validated['level'];
        }
        if (array_key_exists('status', $validated)) {
            $position->status = (int) $validated['status'];
        }
        $position->updated_at = time();
        $position->save();

        return response()->json([
            'message' => '职务已更新',
            'data' => $this->serializeOne($position->fresh()),
        ]);
    }

    public function apiPatchStatus(Request $request, PositionModel $position): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $position->status = (int) $validated['status'];
        $position->updated_at = time();
        $position->save();

        return response()->json([
            'message' => '状态已更新',
            'data' => $this->serializeOne($position->fresh()),
        ]);
    }

    public function apiDestroy(PositionModel $position): JsonResponse
    {
        $id = (int) $position->id;

        DB::transaction(function () use ($id) {
            if (Schema::hasTable('user_positions')) {
                DB::table('user_positions')->where('position_id', $id)->delete();
            }
            PositionModel::query()->whereKey($id)->delete();
        });

        return response()->json(['message' => '职务已删除']);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(PositionModel $p): array
    {
        return [
            'id' => (int) $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'dept_id' => (int) $p->dept_id,
            'dept_name' => null,
            'level' => (int) $p->level,
            'status' => (int) $p->status,
            'created_at' => $p->created_at !== null ? (int) $p->created_at : null,
            'updated_at' => $p->updated_at !== null ? (int) $p->updated_at : null,
        ];
    }
}
