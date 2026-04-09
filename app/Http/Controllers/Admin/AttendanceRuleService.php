<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRuleModel;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\StoreModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceRuleService extends Controller
{
    public function apiFormOptions(): JsonResponse
    {
        $stores = [];
        if (Schema::hasTable('stores')) {
            $stores = StoreModel::query()
                ->where('status', 1)
                ->orderBy('name')
                ->orderByDesc('id')
                ->get(['id', 'code', 'name'])
                ->map(static fn (StoreModel $s) => [
                    'id' => (int) $s->id,
                    'label' => trim((string) ($s->name ?? '')).(trim((string) ($s->code ?? '')) !== '' ? '（'.trim((string) $s->code).'）' : ''),
                ])
                ->values()
                ->all();
        }

        $positions = [];
        if (Schema::hasTable('positions')) {
            $pq = PositionModel::query()->where('status', 1)->orderBy('dept_id')->orderByDesc('level')->orderByDesc('id');
            $plist = $pq->get();
            $deptIds = $plist->pluck('dept_id')->unique()->filter()->all();
            $depts = [];
            if ($deptIds !== [] && Schema::hasTable('departments')) {
                $depts = DepartmentModel::query()
                    ->whereIn('id', $deptIds)
                    ->get(['id', 'name'])
                    ->keyBy('id');
            }
            $positions = $plist->map(static function (PositionModel $p) use ($depts) {
                $d = $depts[$p->dept_id] ?? null;
                $dn = $d !== null ? trim((string) ($d->name ?? '')) : '';

                return [
                    'id' => (int) $p->id,
                    'label' => trim((string) ($p->name ?? '')).($dn !== '' ? ' — '.$dn : ''),
                ];
            })->values()->all();
        }

        return response()->json([
            'data' => [
                'stores' => $stores,
                'positions' => $positions,
            ],
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendance_rules')) {
            return response()->json(['data' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $query = AttendanceRuleModel::query()->orderBy('priority')->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $storeIds = [];
            if (Schema::hasTable('stores')) {
                $storeIds = StoreModel::query()
                    ->where(function ($s) use ($like) {
                        $s->where('name', 'like', $like)->orWhere('code', 'like', $like);
                    })
                    ->pluck('id')
                    ->all();
            }
            $positionIds = Schema::hasTable('positions')
                ? PositionModel::query()->where('name', 'like', $like)->pluck('id')->all()
                : [];
            if ($storeIds === [] && $positionIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($sub) use ($storeIds, $positionIds) {
                    if ($storeIds !== []) {
                        $sub->whereIn('store_id', $storeIds);
                    }
                    if ($positionIds !== []) {
                        $sub->orWhereIn('position_id', $positionIds);
                    }
                });
            }
        }

        $rules = $query->get();
        $data = $rules->map(fn (AttendanceRuleModel $r) => $this->serializeOne($r))->values();

        return response()->json(['data' => $data]);
    }

    public function apiStore(Request $request): JsonResponse
    {
        if (! Schema::hasTable('attendance_rules')) {
            return response()->json(['message' => '考勤规则表未创建'], 503);
        }

        $validated = $this->validatedPayload($request);
        $this->assertStoreScope($validated['store_id'] ?? null);
        $this->assertPositionScope($validated['position_id'] ?? null);

        $now = time();
        $m = new AttendanceRuleModel;
        $this->fillRule($m, $validated);
        $m->created_at = $now;
        $m->updated_at = $now;
        $m->save();

        return response()->json([
            'message' => '规则已创建',
            'data' => $this->serializeOne($m->fresh()),
        ], 201);
    }

    public function apiUpdate(Request $request, AttendanceRuleModel $attendanceRule): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $this->assertStoreScope($validated['store_id'] ?? null);
        $this->assertPositionScope($validated['position_id'] ?? null);
        $this->fillRule($attendanceRule, $validated);
        $attendanceRule->updated_at = time();
        $attendanceRule->save();

        return response()->json([
            'message' => '规则已更新',
            'data' => $this->serializeOne($attendanceRule->fresh()),
        ]);
    }

    public function apiPatchStatus(Request $request, AttendanceRuleModel $attendanceRule): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);
        $attendanceRule->status = (int) $validated['status'];
        $attendanceRule->updated_at = time();
        $attendanceRule->save();

        return response()->json([
            'message' => '状态已更新',
            'data' => $this->serializeOne($attendanceRule->fresh()),
        ]);
    }

    public function apiDestroy(AttendanceRuleModel $attendanceRule): JsonResponse
    {
        $attendanceRule->delete();

        return response()->json(['message' => '规则已删除']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'store_id' => ['nullable', 'integer', 'min:1'],
            'position_id' => ['nullable', 'integer', 'min:1'],
            'work_start_time' => ['required', 'string', 'max:16'],
            'work_end_time' => ['required', 'string', 'max:16'],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'early_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'allow_remote' => ['nullable', 'integer', 'in:0,1'],
            'need_photo' => ['nullable', 'integer', 'in:0,1'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $ws = $this->normalizeTimeString((string) $validated['work_start_time']);
        $we = $this->normalizeTimeString((string) $validated['work_end_time']);
        if ($ws === null || $we === null) {
            throw ValidationException::withMessages([
                'work_start_time' => '上班时间格式须为 HH:mm 或 HH:mm:ss。',
            ]);
        }
        $validated['work_start_time'] = $ws;
        $validated['work_end_time'] = $we;

        if (isset($validated['store_id'])) {
            $validated['store_id'] = (int) $validated['store_id'];
        }
        if (isset($validated['position_id'])) {
            $validated['position_id'] = (int) $validated['position_id'];
        }

        return $validated;
    }

    private function normalizeTimeString(string $value): ?string
    {
        $v = trim($value);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m)) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $v, $m)) {
            return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillRule(AttendanceRuleModel $m, array $validated): void
    {
        $sid = array_key_exists('store_id', $validated) ? $validated['store_id'] : null;
        $m->store_id = $sid !== null && (int) $sid > 0 ? (int) $sid : null;
        $pid = array_key_exists('position_id', $validated) ? $validated['position_id'] : null;
        $m->position_id = $pid !== null && (int) $pid > 0 ? (int) $pid : null;
        $m->work_start_time = $validated['work_start_time'];
        $m->work_end_time = $validated['work_end_time'];
        $m->late_minutes = isset($validated['late_minutes']) ? (int) $validated['late_minutes'] : 30;
        $m->early_minutes = isset($validated['early_minutes']) ? (int) $validated['early_minutes'] : 30;
        $m->allow_remote = isset($validated['allow_remote']) ? (int) $validated['allow_remote'] : 0;
        $m->need_photo = isset($validated['need_photo']) ? (int) $validated['need_photo'] : 1;
        $m->priority = isset($validated['priority']) ? (int) $validated['priority'] : 0;
        if (array_key_exists('status', $validated)) {
            $m->status = (int) $validated['status'];
        } elseif (! $m->exists) {
            $m->status = 1;
        }
    }

    private function assertStoreScope(?int $storeId): void
    {
        if ($storeId === null || $storeId < 1) {
            return;
        }
        if (! Schema::hasTable('stores')) {
            throw ValidationException::withMessages(['store_id' => '门店表不可用。']);
        }
        $s = StoreModel::query()->find($storeId);
        if ($s === null || (int) $s->status !== 1) {
            throw ValidationException::withMessages(['store_id' => '请选择启用中的门店。']);
        }
    }

    private function assertPositionScope(?int $positionId): void
    {
        if ($positionId === null || $positionId < 1) {
            return;
        }
        if (! Schema::hasTable('positions')) {
            throw ValidationException::withMessages(['position_id' => '职务表不可用。']);
        }
        $p = PositionModel::query()->find($positionId);
        if ($p === null || (int) $p->status !== 1) {
            throw ValidationException::withMessages(['position_id' => '请选择启用中的职务。']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(AttendanceRuleModel $r): array
    {
        $storeName = null;
        $storeCode = null;
        if ($r->store_id !== null && Schema::hasTable('stores')) {
            $s = StoreModel::query()->find($r->store_id);
            if ($s !== null) {
                $storeName = trim((string) ($s->name ?? ''));
                $storeCode = trim((string) ($s->code ?? ''));
            }
        }

        $positionName = null;
        $deptName = null;
        if ($r->position_id !== null && Schema::hasTable('positions')) {
            $p = PositionModel::query()->find($r->position_id);
            if ($p !== null) {
                $positionName = trim((string) ($p->name ?? ''));
                $did = $p->dept_id;
                if ($did !== null && Schema::hasTable('departments')) {
                    $d = DepartmentModel::query()->find($did);
                    if ($d !== null) {
                        $deptName = trim((string) ($d->name ?? ''));
                    }
                }
            }
        }

        $wStart = $r->work_start_time;
        $wEnd = $r->work_end_time;
        if ($wStart !== null && ! is_string($wStart)) {
            $wStart = (string) $wStart;
        }
        if ($wEnd !== null && ! is_string($wEnd)) {
            $wEnd = (string) $wEnd;
        }

        return [
            'id' => (int) $r->id,
            'store_id' => $r->store_id !== null ? (int) $r->store_id : null,
            'store_name' => $storeName,
            'store_code' => $storeCode,
            'position_id' => $r->position_id !== null ? (int) $r->position_id : null,
            'position_name' => $positionName,
            'dept_name' => $deptName,
            'work_start_time' => $wStart !== null ? substr((string) $wStart, 0, 5) : null,
            'work_end_time' => $wEnd !== null ? substr((string) $wEnd, 0, 5) : null,
            'late_minutes' => $r->late_minutes !== null ? (int) $r->late_minutes : 30,
            'early_minutes' => $r->early_minutes !== null ? (int) $r->early_minutes : 30,
            'allow_remote' => (int) $r->allow_remote,
            'need_photo' => (int) $r->need_photo,
            'priority' => $r->priority !== null ? (int) $r->priority : 0,
            'status' => (int) $r->status,
            'created_at' => $r->created_at !== null ? (int) $r->created_at : null,
            'updated_at' => $r->updated_at !== null ? (int) $r->updated_at : null,
        ];
    }
}
