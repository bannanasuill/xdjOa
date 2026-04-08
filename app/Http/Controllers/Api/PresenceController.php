<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPresenceRecordModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PresenceController extends Controller
{
    public function arrival(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_presence_records')) {
            return $this->fail(1999, '到岗外出表未就绪');
        }

        $validated = $this->validateLocation($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $now = time();
        $latest = $this->latestValidRecord((int) $user->id);
        if ($latest !== null && $now <= (int) $latest->end_at) {
            return $this->fail(1007, '时间冲突，请稍后再试');
        }

        $data = [
            'user_id' => (int) $user->id,
            'work_date' => date('Y-m-d', $now),
            'record_type' => UserPresenceRecordModel::TYPE_ARRIVAL,
            'start_at' => $now,
            'end_at' => $now,
            'source' => 1,
            'status' => UserPresenceRecordModel::STATUS_VALID,
            'reason' => null,
            'address' => $validated['address'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $created = UserPresenceRecordModel::query()->create($data);

        return $this->ok('到岗成功', ['id' => (int) $created->id]);
    }

    public function outingStart(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_presence_records')) {
            return $this->fail(1999, '到岗外出表未就绪');
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $now = time();
        $uid = (int) $user->id;
        $latest = $this->latestValidRecord($uid);
        if ($latest !== null && $now <= (int) $latest->end_at) {
            return $this->fail(1007, '时间冲突，请稍后再试');
        }
        if ($this->currentOpenOuting($uid) !== null) {
            return $this->fail(1008, '存在进行中的外出记录，请先返回');
        }

        $created = UserPresenceRecordModel::query()->create([
            'user_id' => $uid,
            'work_date' => date('Y-m-d', $now),
            'record_type' => UserPresenceRecordModel::TYPE_OUTING,
            'start_at' => $now,
            'end_at' => $now,
            'source' => 1,
            'status' => UserPresenceRecordModel::STATUS_VALID,
            'reason' => trim((string) $request->input('reason')),
            'address' => $request->input('address'),
            'longitude' => $request->input('longitude'),
            'latitude' => $request->input('latitude'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->ok('外出开始成功', ['id' => (int) $created->id]);
    }

    public function outingEnd(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_presence_records')) {
            return $this->fail(1999, '到岗外出表未就绪');
        }

        $validated = $this->validateLocation($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $open = $this->currentOpenOuting((int) $user->id);
        if ($open === null) {
            return $this->fail(1009, '不存在进行中的外出记录');
        }

        $now = time();
        $endAt = $now > (int) $open->start_at ? $now : ((int) $open->start_at + 1);
        $open->end_at = $endAt;
        $open->updated_at = $now;
        if (! empty($validated['address'])) {
            $open->address = $validated['address'];
        }
        if (array_key_exists('longitude', $validated)) {
            $open->longitude = $validated['longitude'];
        }
        if (array_key_exists('latitude', $validated)) {
            $open->latitude = $validated['latitude'];
        }
        $open->save();

        return $this->ok('外出结束成功', [
            'id' => (int) $open->id,
            'duration_minutes' => (int) floor(((int) $open->end_at - (int) $open->start_at) / 60),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_presence_records')) {
            return $this->fail(1999, '到岗外出表未就绪');
        }

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $today = date('Y-m-d');
        $rows = UserPresenceRecordModel::query()
            ->where('user_id', (int) $user->id)
            ->where('status', UserPresenceRecordModel::STATUS_VALID)
            ->where('work_date', $today)
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        return $this->ok('获取成功', [
            'date' => $today,
            'records' => $rows->map(fn (UserPresenceRecordModel $r) => [
                'id' => (int) $r->id,
                'record_type' => (int) $r->record_type,
                'start_at' => (int) $r->start_at,
                'end_at' => (int) $r->end_at,
                'reason' => $r->reason,
                'address' => $r->address,
                'longitude' => $r->longitude,
                'latitude' => $r->latitude,
            ])->values()->all(),
        ]);
    }

    private function validateLocation(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        /** @var array<string,mixed> */
        return $validator->validated();
    }

    private function latestValidRecord(int $userId): ?UserPresenceRecordModel
    {
        return UserPresenceRecordModel::query()
            ->where('user_id', $userId)
            ->where('status', UserPresenceRecordModel::STATUS_VALID)
            ->orderByDesc('end_at')
            ->orderByDesc('id')
            ->first();
    }

    private function currentOpenOuting(int $userId): ?UserPresenceRecordModel
    {
        return UserPresenceRecordModel::query()
            ->where('user_id', $userId)
            ->where('status', UserPresenceRecordModel::STATUS_VALID)
            ->where('record_type', UserPresenceRecordModel::TYPE_OUTING)
            ->whereColumn('end_at', 'start_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function ok(string $message = 'success', ?array $data = []): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function fail(int $code, string $message, ?array $data = null): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
    }
}

