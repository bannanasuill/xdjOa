<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentModel;
use App\Models\StoreModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreService extends Controller
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
            ->get(['id', 'name'])
            ->map(fn (DepartmentModel $d) => [
                'id' => (int) $d->id,
                'label' => ($d->name !== null && $d->name !== '') ? (string) $d->name : ('#'.$d->id),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        if (! Schema::hasTable('stores')) {
            return response()->json(['data' => []]);
        }

        $q = trim((string) $request->query('q', ''));

        $query = StoreModel::query()->orderByDesc('id');
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%'.$q.'%')
                    ->orWhere('code', 'like', '%'.$q.'%');
            });
        }

        $stores = $query->get();
        $deptIds = $stores->pluck('dept_id')->filter()->unique()->all();
        $depts = [];
        if ($deptIds !== [] && Schema::hasTable('departments')) {
            $depts = DepartmentModel::query()
                ->whereIn('id', $deptIds)
                ->get(['id', 'name'])
                ->keyBy('id');
        }

        $data = $stores->map(function (StoreModel $s) use ($depts) {
            $row = $this->serializeOne($s);
            $did = $s->dept_id;
            if ($did !== null && isset($depts[$did])) {
                $row['dept_name'] = $depts[$did]->name;
            } else {
                $row['dept_name'] = null;
            }

            return $row;
        })->values();

        return response()->json(['data' => $data]);
    }

    /**
     * 根据地址调用百度地图地理编码，返回 GCJ-02（国测局）经纬度，与打卡字段说明一致。
     */
    public function apiGeocode(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $user->canAdminPermission('perm.admin.api.stores.store')
            && ! $user->canAdminPermission('perm.admin.api.stores.update')) {
            return response()->json(['message' => '无权执行此操作'], 403);
        }

        $validated = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'province_name' => ['nullable', 'string', 'max:64'],
            'city_name' => ['nullable', 'string', 'max:64'],
            'district_name' => ['nullable', 'string', 'max:64'],
        ]);

        $query = $this->composeGeocodeAddress($validated);
        if ($query === '' || mb_strlen($query) < 4) {
            throw ValidationException::withMessages([
                'address' => '请填写更完整的地址（含省市区或地标，至少 4 个字符）。',
            ]);
        }

        $ak = (string) config('services.baidu_map.ak', '');
        if ($ak === '') {
            return response()->json(['message' => '服务端未配置 BAIDU_MAP_AK，无法调用百度地图。'], 503);
        }

        try {
            $httpResponse = Http::timeout(12)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'xdjOa/1.0 (server-side geocoding)',
                ])
                ->get('https://api.map.baidu.com/geocoding/v3/', [
                    'address' => $query,
                    'output' => 'json',
                    'ak' => $ak,
                    'ret_coordtype' => 'gcj02ll',
                ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => '请求百度地图失败，请稍后重试。'], 502);
        }

        if (! $httpResponse->successful()) {
            return response()->json(['message' => '百度地图接口 HTTP 异常'], 502);
        }

        $json = $httpResponse->json();
        if (! is_array($json)) {
            return response()->json(['message' => '百度地图返回数据无效'], 502);
        }

        $status = (int) ($json['status'] ?? -1);
        if ($status !== 0) {
            $msg = (string) ($json['message'] ?? '地理编码失败');

            return response()->json(['message' => $this->baiduGeocodeFailureMessage($msg)], 422);
        }

        $loc = $json['result']['location'] ?? null;
        if (! is_array($loc)) {
            return response()->json(['message' => '未解析到坐标'], 422);
        }

        $lng = $loc['lng'] ?? null;
        $lat = $loc['lat'] ?? null;
        if ($lng === null || $lat === null) {
            return response()->json(['message' => '百度地图未返回经纬度'], 422);
        }

        return response()->json([
            'message' => '已根据地址解析经纬度（GCJ-02）',
            'data' => [
                'longitude' => round($lng, 6),
                'latitude' => round($lat, 6),
                'query' => $query,
                'level' => $json['result']['level'] ?? null,
            ],
        ]);
    }

    public function apiStore(Request $request): JsonResponse
    {
        if (! Schema::hasTable('stores')) {
            return response()->json(['message' => '门店表未创建'], 503);
        }

        $validated = $this->validatedPayload($request, null);
        $this->assertDeptActive(isset($validated['dept_id']) ? (int) $validated['dept_id'] : null);

        $now = time();
        $s = new StoreModel;
        $this->fillStore($s, $validated);
        $s->created_at = $now;
        $s->updated_at = $now;
        $s->save();

        return response()->json([
            'message' => '店铺已创建',
            'data' => $this->serializeOne($s->fresh()),
        ], 201);
    }

    public function apiUpdate(Request $request, StoreModel $store): JsonResponse
    {
        $validated = $this->validatedPayload($request, $store);
        $this->assertDeptActive(isset($validated['dept_id']) ? (int) $validated['dept_id'] : null);
        $this->fillStore($store, $validated);
        $store->updated_at = time();
        $store->save();

        return response()->json([
            'message' => '店铺已更新',
            'data' => $this->serializeOne($store->fresh()),
        ]);
    }

    public function apiPatchStatus(Request $request, StoreModel $store): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer', 'in:0,1'],
        ]);

        $store->status = (int) $validated['status'];
        $store->updated_at = time();
        $store->save();

        return response()->json([
            'message' => '状态已更新',
            'data' => $this->serializeOne($store->fresh()),
        ]);
    }

    public function apiDestroy(StoreModel $store): JsonResponse
    {
        $store->delete();

        return response()->json(['message' => '店铺已删除']);
    }

    private function assertDeptActive(?int $deptId): void
    {
        if ($deptId === null || $deptId < 1) {
            return;
        }
        if (! Schema::hasTable('departments')) {
            return;
        }
        $dept = DepartmentModel::query()->find($deptId);
        if ($dept === null || (int) $dept->status !== 1) {
            throw ValidationException::withMessages(['dept_id' => '请选择已启用的部门。']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?StoreModel $existing): array
    {
        $codeRule = Rule::unique('stores', 'code');
        if ($existing !== null) {
            $codeRule = $codeRule->ignore($existing->id);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:32', $codeRule],
            'name' => ['required', 'string', 'max:64'],
            'dept_id' => ['nullable', 'integer', 'min:1', 'exists:departments,id'],
            'store_type' => ['required', 'integer', 'in:1,2,3'],
            'address' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'radius' => ['nullable', 'integer', 'min:1', 'max:500000'],
            'wifi_mac' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ]);
    }

    private function fillStore(StoreModel $s, array $validated): void
    {
        $s->code = trim((string) $validated['code']);
        $s->name = trim((string) $validated['name']);
        $deptId = $validated['dept_id'] ?? null;
        $s->dept_id = $deptId !== null && (int) $deptId > 0 ? (int) $deptId : null;
        $s->store_type = (int) $validated['store_type'];
        $s->address = isset($validated['address']) ? trim((string) $validated['address']) : null;
        if (array_key_exists('longitude', $validated) && $validated['longitude'] !== null && $validated['longitude'] !== '') {
            $s->longitude = $validated['longitude'];
        } else {
            $s->longitude = null;
        }
        if (array_key_exists('latitude', $validated) && $validated['latitude'] !== null && $validated['latitude'] !== '') {
            $s->latitude = $validated['latitude'];
        } else {
            $s->latitude = null;
        }
        $s->radius = isset($validated['radius']) ? (int) $validated['radius'] : 100;
        $s->wifi_mac = isset($validated['wifi_mac']) ? trim((string) $validated['wifi_mac']) : null;
        if (array_key_exists('status', $validated)) {
            $s->status = (int) $validated['status'];
        } elseif ($s->exists === false) {
            $s->status = 1;
        }
    }

    private function baiduGeocodeFailureMessage(string $baiduMessage): string
    {
        $base = '百度地图：'.$baiduMessage;
        if (str_contains($baiduMessage, 'Referer') || str_contains($baiduMessage, 'referer')) {
            return $base.'。当前 AK 若为「浏览器端」且启用了 Referer 校验，服务端无法通过校验。请在百度开放平台新建或改用「服务端」应用密钥，安全设置使用服务器 IP 白名单，并开通地理编码。';
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function composeGeocodeAddress(array $validated): string
    {
        $parts = [];
        foreach (['province_name', 'city_name', 'district_name'] as $key) {
            if (! isset($validated[$key])) {
                continue;
            }
            $t = trim((string) $validated[$key]);
            if ($t !== '') {
                $parts[] = $t;
            }
        }
        if (isset($validated['address'])) {
            $addr = trim((string) $validated['address']);
            if ($addr !== '') {
                $parts[] = $addr;
            }
        }

        return implode('', $parts);
    }

    private function serializeOne(StoreModel $s): array
    {
        return [
            'id' => (int) $s->id,
            'dept_id' => $s->dept_id !== null ? (int) $s->dept_id : null,
            'dept_name' => null,
            'code' => $s->code,
            'name' => $s->name,
            'store_type' => (int) $s->store_type,
            'address' => $s->address,
            'longitude' => $s->longitude,
            'latitude' => $s->latitude,
            'radius' => $s->radius !== null ? (int) $s->radius : 100,
            'wifi_mac' => $s->wifi_mac,
            'status' => (int) $s->status,
            'created_at' => $s->created_at !== null ? (int) $s->created_at : null,
            'updated_at' => $s->updated_at !== null ? (int) $s->updated_at : null,
        ];
    }
}
