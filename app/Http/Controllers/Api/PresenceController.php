<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PresenceController extends Controller
{
    private const TABLE = 'user_presence_records';

    public function arrival(Request $request): JsonResponse
    {
        $validated = $this->validateLocation($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $now = time();
        if ($this->currentOpenOuting((int) $user->id) !== null) {
            return $this->fail(1008, '存在进行中的外出记录，请先返回');
        }
        $latest = $this->latestValidRecord((int) $user->id);
        if ($latest !== null && $latest->end_at !== null && $now <= (int) $latest->end_at) {
            return $this->fail(1007, '时间冲突，请稍后再试');
        }

        $data = [
            'user_id' => (int) $user->id,
            'work_date' => date('Y-m-d', $now),
            'record_type' => 1,
            'start_at' => $now,
            'end_at' => $now,
            'source' => 1,
            'status' => 1,
            'reason' => null,
            'address' => $validated['address'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $createdId = (int) DB::table(self::TABLE)->insertGetId($data);

        return $this->ok('到岗成功', ['id' => $createdId]);
    }

    public function outingStart(Request $request): JsonResponse
    {
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
        if ($latest !== null && $latest->end_at !== null && $now <= (int) $latest->end_at) {
            return $this->fail(1007, '时间冲突，请稍后再试');
        }
        if ($this->currentOpenOuting($uid) !== null) {
            return $this->fail(1008, '存在进行中的外出记录，请先返回');
        }

        $createdId = (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $uid,
            'work_date' => date('Y-m-d', $now),
            'record_type' => 2,
            'start_at' => $now,
            'end_at' => null,
            'source' => 1,
            'status' => 1,
            'reason' => trim((string) $request->input('reason')),
            'address' => $request->input('address'),
            'longitude' => $request->input('longitude'),
            'latitude' => $request->input('latitude'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->ok('外出开始成功', ['id' => $createdId]);
    }

    public function outingEnd(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'outing_id' => ['required', 'integer', 'min:1'],
            'address' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }
        /** @var array<string,mixed> $validated */
        $validated = $validator->validated();

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $open = DB::table(self::TABLE)
            ->where('id', (int) $validated['outing_id'])
            ->where('user_id', (int) $user->id)
            ->where('status', 1)
            ->where('record_type', 2)
            ->whereNull('end_at')
            ->first();
        if ($open === null) {
            return $this->fail(1009, '外出记录不存在或已结束');
        }

        $now = time();
        $endAt = $now > (int) $open->start_at ? $now : ((int) $open->start_at + 1);
        $update = [
            'end_at' => $endAt,
            'updated_at' => $now,
        ];
        if (! empty($validated['address'])) {
            $update['address'] = $validated['address'];
        }
        if (array_key_exists('longitude', $validated)) {
            $update['longitude'] = $validated['longitude'];
        }
        if (array_key_exists('latitude', $validated)) {
            $update['latitude'] = $validated['latitude'];
        }
        DB::table(self::TABLE)->where('id', (int) $open->id)->update($update);

        return $this->ok('外出结束成功', [
            'id' => (int) $open->id,
            'duration_minutes' => (int) floor(($endAt - (int) $open->start_at) / 60),
        ]);
    }

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $today = date('Y-m-d');
        $rows = DB::table(self::TABLE)
            ->where('user_id', (int) $user->id)
            ->where('status', 1)
            ->where('work_date', $today)
            ->orderBy('start_at')
            ->orderBy('id')
            ->get();

        return $this->ok('获取成功', [
            'date' => $today,
            'records' => $rows->map(fn ($r) => [
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

    public function offwork(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }
        /** @var array<string,mixed> $validated */
        $validated = $validator->validated();

        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $now = time();
        $uid = (int) $user->id;
        $reason = trim((string) ($validated['reason'] ?? ''));
        $openOuting = $this->currentOpenOuting($uid);

        if ($openOuting !== null) {
            if ($reason === '') {
                return $this->fail(1010, '外出中下班请填写原因');
            }

            $outingEndAt = $now > (int) $openOuting->start_at ? $now : ((int) $openOuting->start_at + 1);
            $outingUpdate = [
                'end_at' => $outingEndAt,
                'updated_at' => $now,
            ];
            if (! empty($validated['address'])) {
                $outingUpdate['address'] = $validated['address'];
            }
            if (array_key_exists('longitude', $validated)) {
                $outingUpdate['longitude'] = $validated['longitude'];
            }
            if (array_key_exists('latitude', $validated)) {
                $outingUpdate['latitude'] = $validated['latitude'];
            }
            DB::table(self::TABLE)->where('id', (int) $openOuting->id)->update($outingUpdate);
        }

        $latest = $this->latestValidRecord($uid);
        if ($latest !== null && $latest->end_at !== null && $now <= (int) $latest->end_at) {
            $now = (int) $latest->end_at + 1;
        }

        $createdId = (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $uid,
            'work_date' => date('Y-m-d', $now),
            'record_type' => 3,
            'start_at' => $now,
            'end_at' => $now,
            'source' => 1,
            'status' => 1,
            'reason' => $reason !== '' ? $reason : null,
            'address' => $validated['address'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->ok('下班成功', ['id' => $createdId]);
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

    private function latestValidRecord(int $userId): ?object
    {
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->orderByDesc('end_at')
            ->orderByDesc('id')
            ->first();
    }

    private function currentOpenOuting(int $userId): ?object
    {
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->where('record_type', 2)
            ->whereNull('end_at')
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

