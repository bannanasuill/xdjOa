<?php

namespace App\Services;

use App\Models\AttendanceRuleModel;
use App\Models\StoreModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 员工到岗打卡前：按考勤规则（时间窗口、门店范围、拍照）判断是否允许。
 */
class AttendanceArrivalChecker
{
    /** @return array{ok: true}|array{ok: false, code: int, message: string} */
    public function verify(
        UserModel $user,
        int $now,
        ?float $longitude,
        ?float $latitude,
        ?int $requestStoreId,
        ?string $photo,
    ): array {
        if (! Schema::hasTable('attendance_rules')) {
            return ['ok' => true];
        }

        /** @var Collection<int, AttendanceRuleModel> $rules */
        $rules = AttendanceRuleModel::query()
            ->where('status', 1)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return ['ok' => true];
        }

        $workDate = date('Y-m-d', $now);
        $ctx = $this->resolveContext($user, $workDate, $now, $longitude, $latitude, $requestStoreId);
        if (isset($ctx['error_code'])) {
            return ['ok' => false, 'code' => (int) $ctx['error_code'], 'message' => (string) $ctx['error_message']];
        }

        /** @var array{store_id: ?int, position_id: ?int} $ctx */
        $rule = $this->matchRule($rules, $ctx['store_id'], $ctx['position_id']);
        if ($rule === null) {
            return ['ok' => true];
        }

        $timeResult = $this->checkTimeWindow($rule, $now);
        if ($timeResult !== null) {
            return $timeResult;
        }

        if ((int) $rule->need_photo === 1) {
            $p = $photo !== null ? trim($photo) : '';
            if ($p === '') {
                return ['ok' => false, 'code' => 1024, 'message' => '根据考勤规则需上传到岗照片'];
            }
        }

        if ((int) $rule->allow_remote !== 1) {
            $geo = $this->checkOnSiteStore($ctx['store_id'], $longitude, $latitude);
            if ($geo !== null) {
                return $geo;
            }
        }

        return ['ok' => true];
    }

    /**
     * @return array{store_id: ?int, position_id: ?int}|array{error_code: int, error_message: string}
     */
    private function resolveContext(
        UserModel $user,
        string $workDate,
        int $now,
        ?float $longitude,
        ?float $latitude,
        ?int $requestStoreId,
    ): array {
        $uid = (int) $user->id;
        $assignRows = $this->activeUserStoreRows($uid, $workDate);

        if ($requestStoreId !== null && $requestStoreId > 0) {
            foreach ($assignRows as $row) {
                if ((int) $row->store_id === $requestStoreId) {
                    return [
                        'store_id' => (int) $row->store_id,
                        'position_id' => (int) $row->position_id,
                    ];
                }
            }

            return ['error_code' => 1026, 'error_message' => '未分配该门店或门店不在有效期内'];
        }

        if ($assignRows !== []) {
            if ($longitude !== null && $latitude !== null) {
                $inRange = $this->assignmentsInRadius($assignRows, $latitude, $longitude);
                if ($inRange === []) {
                    return ['error_code' => 1023, 'error_message' => '未在已分配门店的打卡范围内，请靠近门店后重试'];
                }
                if (count($inRange) === 1) {
                    $pick = $inRange[0];

                    return [
                        'store_id' => (int) $pick->store_id,
                        'position_id' => (int) $pick->position_id,
                    ];
                }
                usort($inRange, function ($a, $b) use ($latitude, $longitude) {
                    $da = $this->storeDistanceMeters((int) $a->store_id, $latitude, $longitude);
                    $db = $this->storeDistanceMeters((int) $b->store_id, $latitude, $longitude);

                    return $da <=> $db;
                });
                $pick = $inRange[0];

                return [
                    'store_id' => (int) $pick->store_id,
                    'position_id' => (int) $pick->position_id,
                ];
            }

            if (count($assignRows) === 1) {
                $one = $assignRows[0];

                return [
                    'store_id' => (int) $one->store_id,
                    'position_id' => (int) $one->position_id,
                ];
            }

            return ['error_code' => 1025, 'error_message' => '您在多个门店任职，请开启定位或在请求中传 store_id 指定打卡门店'];
        }

        $positionIds = $this->activeUserPositionIds($uid);
        $positionId = $positionIds[0] ?? null;

        return [
            'store_id' => null,
            'position_id' => $positionId,
        ];
    }

    /**
     * @param  list<object>  $assignRows
     * @return list<object>
     */
    private function assignmentsInRadius(array $assignRows, float $lat, float $lon): array
    {
        $out = [];
        foreach ($assignRows as $row) {
            $sid = (int) $row->store_id;
            if ($sid < 1) {
                continue;
            }
            $store = StoreModel::query()->find($sid);
            if ($store === null || (int) $store->status !== 1) {
                continue;
            }
            if ($store->latitude === null || $store->longitude === null) {
                continue;
            }
            $slat = (float) $store->latitude;
            $slon = (float) $store->longitude;
            $radius = (int) ($store->radius ?? 100);
            if ($radius < 1) {
                $radius = 100;
            }
            $meters = $this->haversineMeters($lat, $lon, $slat, $slon);
            if ($meters <= (float) $radius) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function storeDistanceMeters(int $storeId, float $lat, float $lon): float
    {
        $store = StoreModel::query()->find($storeId);
        if ($store === null || $store->latitude === null || $store->longitude === null) {
            return PHP_FLOAT_MAX;
        }

        return $this->haversineMeters($lat, $lon, (float) $store->latitude, (float) $store->longitude);
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lon2 - $lon1);
        $a = sin($dp / 2) * sin($dp / 2) + cos($p1) * cos($p2) * sin($dl / 2) * sin($dl / 2);

        return 2 * $r * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  Collection<int, AttendanceRuleModel>  $rules
     */
    private function matchRule(Collection $rules, ?int $storeId, ?int $positionId): ?AttendanceRuleModel
    {
        foreach ($rules as $r) {
            $rs = $r->store_id;
            $rp = $r->position_id;
            if ($rs !== null && (int) $rs !== (int) ($storeId ?? 0)) {
                continue;
            }
            if ($rp !== null && (int) $rp !== (int) ($positionId ?? 0)) {
                continue;
            }

            return $r;
        }

        return null;
    }

    /**
     * @return array{ok: false, code: int, message: string}|null
     */
    private function checkTimeWindow(AttendanceRuleModel $rule, int $now): ?array
    {
        $tz = (string) config('app.timezone', 'Asia/Shanghai');
        $tNow = Carbon::createFromTimestamp($now)->timezone($tz);
        $dateStr = $tNow->toDateString();
        $wsRaw = $rule->work_start_time;
        $weRaw = $rule->work_end_time;
        if ($wsRaw === null || $weRaw === null) {
            return null;
        }
        $ws = Carbon::parse($dateStr.' '.(string) $wsRaw, $tz);
        $we = Carbon::parse($dateStr.' '.(string) $weRaw, $tz);
        if ($we->lessThanOrEqualTo($ws)) {
            $we->addDay();
        }

        if ($tNow->lt($ws)) {
            return ['ok' => false, 'code' => 1020, 'message' => '尚未到上班时间，暂时无法到岗打卡'];
        }
        if ($tNow->gt($we)) {
            return ['ok' => false, 'code' => 1022, 'message' => '已超过规定的下班时间，无法到岗打卡'];
        }

        return null;
    }

    /**
     * @return array{ok: false, code: int, message: string}|null
     */
    private function checkOnSiteStore(?int $storeId, ?float $longitude, ?float $latitude): ?array
    {
        if ($storeId === null || $storeId < 1) {
            return ['ok' => false, 'code' => 1023, 'message' => '需在门店范围内打卡，请先分配门店后再试'];
        }
        if ($longitude === null || $latitude === null) {
            return ['ok' => false, 'code' => 1023, 'message' => '根据考勤规则需上传定位以校验是否在门店范围内'];
        }
        $store = StoreModel::query()->find($storeId);
        if ($store === null || (int) $store->status !== 1) {
            return ['ok' => false, 'code' => 1023, 'message' => '门店无效，无法校验打卡位置'];
        }
        if ($store->latitude === null || $store->longitude === null) {
            return ['ok' => false, 'code' => 1023, 'message' => '门店未配置坐标，无法校验打卡位置'];
        }
        $radius = (int) ($store->radius ?? 100);
        if ($radius < 1) {
            $radius = 100;
        }
        $meters = $this->haversineMeters($latitude, $longitude, (float) $store->latitude, (float) $store->longitude);
        if ($meters > (float) $radius) {
            return ['ok' => false, 'code' => 1023, 'message' => '当前位置不在该门店允许的打卡范围内'];
        }

        return null;
    }

    /** @return list<object> */
    private function activeUserStoreRows(int $userId, string $workDate): array
    {
        $t = 'user_stores';
        if (! Schema::hasTable($t)) {
            return [];
        }

        return DB::table($t)
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $workDate)
            ->whereDate('end_date', '>=', $workDate)
            ->orderByDesc('is_main')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return list<int> */
    private function activeUserPositionIds(int $userId): array
    {
        $tUp = 'user_positions';
        $tP = 'positions';
        if (! Schema::hasTable($tUp) || ! Schema::hasTable($tP)) {
            return [];
        }

        return DB::table($tUp.' as up')
            ->join($tP.' as p', 'p.id', '=', 'up.position_id')
            ->where('up.user_id', $userId)
            ->where('p.status', 1)
            ->orderBy('up.id')
            ->pluck('up.position_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
