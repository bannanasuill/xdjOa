<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\RoleModel;
use App\Models\StoreModel;
use App\Models\SystemSettingModel;
use App\Models\UserLogModel;
use App\Models\UserModel;
use App\Support\DefaultUserAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\View\View;
use Throwable;

/**
 * 后台用户管理：路由入口、校验、列表/创建/更新/角色/状态等业务均在本类。
 */
class UserService extends Controller
{
    /**
     * 分配角色下拉：组装权鉴后交给模型取数。
     */
    public function apiRoleOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }

        if (
            ! $actor->canAdminPermission('perm.admin.api.users.index')
            && ! $actor->canAdminPermission('perm.admin.api.users.store')
            && ! $actor->canAdminPermission('perm.admin.api.users.update')
        ) {
            return response()->json(['message' => '无权查看角色列表'], 403);
        }

        return response()->json(['data' => RoleModel::assignableForUserPicker()]);
    }

    /**
     * 为员工分配部门/职务时的下拉数据（仅需「更新用户」权限，不要求单独开通部门/职务菜单接口）。
     */
    public function apiOrgOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }

        if (! $actor->canAdminPermission('perm.admin.api.users.update')) {
            return response()->json(['message' => '无权查看组织选项'], 403);
        }

        $departments = [];
        if (Schema::hasTable('departments')) {
            $departments = DepartmentModel::query()
                ->where('status', 1)
                ->orderBy('sort')
                ->orderByDesc('id')
                ->get(['id', 'name'])
                ->map(static function (DepartmentModel $d) {
                    return [
                        'id' => (int) $d->id,
                        'name' => trim((string) ($d->name ?? '')),
                    ];
                })
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
                    'name' => trim((string) ($p->name ?? '')),
                    'dept_id' => (int) $p->dept_id,
                    'dept_name' => $dn,
                ];
            })->values()->all();
        }

        return response()->json([
            'data' => [
                'departments' => $departments,
                'positions' => $positions,
            ],
        ]);
    }

    /** Blade 分页列表（无 SPA 时访问 /admin/users）。 */
    public function index(Request $request): View
    {
        $validated = $request->validate(self::listFilterRules());
        $payload = $this->bladeIndexPayload($validated);

        return view('admin.users.index', [
            'users' => $payload['users'],
            'searchQuery' => $payload['searchQuery'],
            'filterRoleId' => $payload['filterRoleId'],
            'roleFilterOptions' => RoleModel::assignableForUserPicker(),
            'perPage' => $payload['perPage'],
            'perPageOptions' => [10, 20, 50, 100],
            'presenceMetaMap' => $payload['presenceMetaMap'] ?? [],
            'presenceFilterOptions' => UserModel::adminListPresenceFilterOptions(),
            'filterPresenceToday' => $payload['filterPresenceToday'] ?? null,
        ]);
    }

    /** SPA 用户列表 JSON：附带 roles、is_super_admin。 */
    public function apiIndex(Request $request): JsonResponse
    {
        $validated = $request->validate(self::apiListFilterRules());
        $pack = $this->apiIndexPayload($validated);
        $paginator = $pack['paginator'];

        return response()->json([
            'data' => $pack['data'],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * 用户出勤记录（按条分页）：可选业务日期区间，默认按日期、时间倒序。
     */
    public function apiUserPresenceRecords(Request $request, UserModel $adminUser): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
        if (
            ! empty($validated['date_from'])
            && ! empty($validated['date_to'])
            && $validated['date_to'] < $validated['date_from']
        ) {
            return response()->json(['message' => '结束日期不能早于开始日期'], 422);
        }

        $t = 'user_presence_records';
        if (! Schema::hasTable($t)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $perPage = min(100, max(1, $perPage));

        $q = DB::table($t)
            ->where('user_id', (int) $adminUser->id)
            ->where('status', 1);

        if (! empty($validated['date_from'])) {
            $q->whereDate('work_date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $q->whereDate('work_date', '<=', $validated['date_to']);
        }

        $paginator = $q->orderByDesc('work_date')
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(static function ($r) {
            $start = (int) ($r->start_at ?? 0);
            $endRaw = $r->end_at ?? null;
            $end = $endRaw !== null && $endRaw !== '' ? (int) $endRaw : null;
            $duration = null;
            if ($end !== null && $end >= $start) {
                $duration = (int) floor(($end - $start) / 60);
            }

            return [
                'id' => (int) ($r->id ?? 0),
                'work_date' => (string) ($r->work_date ?? ''),
                'record_type' => (int) ($r->record_type ?? 0),
                'record_type_label' => self::presenceRecordTypeLabel((int) ($r->record_type ?? 0)),
                'start_at' => $start,
                'end_at' => $end,
                'duration_minutes' => $duration,
                'reason' => $r->reason,
                'address' => $r->address,
                'longitude' => $r->longitude,
                'latitude' => $r->latitude,
                'source' => (int) ($r->source ?? 1),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private static function presenceRecordTypeLabel(int $type): string
    {
        return match ($type) {
            1 => '到岗',
            2 => '外出',
            3 => '下班',
            default => '其他',
        };
    }

    /** Blade 新增页。 */
    public function create(): View
    {
        return view('admin.users.create');
    }

    /** Blade POST 创建：写用户表；默认不分配角色。 */
    public function store(Request $request): RedirectResponse
    {
        self::mergeCreateUserRequest($request);
        $v = self::createUserValidation();
        $validated = $request->validate($v['rules'], $v['messages']);

        $this->createUser($request, $validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', '新增用户成功。');
    }

    /** SPA POST 创建用户，逻辑与 store 一致（默认无角色 + 失败回滚删除用户）。 */
    public function apiStore(Request $request): JsonResponse
    {
        self::mergeCreateUserRequest($request);
        $v = self::createUserValidation();
        $validated = $request->validate($v['rules'], $v['messages']);

        $createdUser = $this->createUser($request, $validated);

        return response()->json([
            'ok' => true,
            'message' => '新增用户成功。',
            'data' => $createdUser,
        ], 201);
    }

    /** Blade 编辑页；超级管理员不可进。 */
    public function edit(UserModel $adminUser): View
    {
        if ($adminUser->isSuperAdminAccount()) {
            abort(403, '超级管理员不可编辑。');
        }

        return view('admin.users.edit', [
            'user' => $adminUser,
        ]);
    }

    /** Blade PUT 更新基础资料（不含角色）。 */
    public function update(Request $request, UserModel $adminUser): RedirectResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            abort(403, '超级管理员不可编辑。');
        }

        $rules = self::updateUserValidationRules($adminUser)['rules'];
        $validated = $request->validate($rules);

        $this->updateProfile($request, $adminUser, $validated);

        return redirect()
            ->route('admin.users.index')
            ->with('success', '用户信息已更新。');
    }

    /** SPA PUT 更新基础资料。 */
    public function apiUpdate(Request $request, UserModel $adminUser): JsonResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            abort(403, '超级管理员不可编辑。');
        }

        $rules = self::updateUserValidationRules($adminUser)['rules'];
        $validated = $request->validate($rules);

        $this->updateProfile($request, $adminUser, $validated);

        return response()->json([
            'ok' => true,
            'message' => '用户信息已更新。',
            'data' => $adminUser->fresh(),
        ]);
    }

    /** SPA PATCH 分配角色。 */
    public function apiSyncRoles(Request $request, UserModel $adminUser): JsonResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            return response()->json(['message' => '超级管理员不可修改角色'], 403);
        }

        $validated = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $roleIds = array_values(array_unique(array_map('intval', $validated['role_ids'])));

        $this->syncRoles($request, $adminUser, $roleIds);

        return response()->json([
            'ok' => true,
            'message' => '角色已更新。',
        ]);
    }

    /** SPA PATCH：全量同步员工所属部门、职务（均支持多选）。 */
    public function apiSyncOrg(Request $request, UserModel $adminUser): JsonResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            return response()->json(['message' => '超级管理员不可修改组织归属'], 403);
        }

        $deptRules = ['present', 'array'];
        $deptItemRules = [];
        $posRules = ['present', 'array'];
        $posItemRules = [];

        if (Schema::hasTable('departments')) {
            $deptItemRules = ['integer', 'min:1', Rule::exists('departments', 'id')->where('status', 1)];
        }
        if (Schema::hasTable('positions')) {
            $posItemRules = ['integer', 'min:1', Rule::exists('positions', 'id')->where('status', 1)];
        }

        $validated = $request->validate([
            'dept_ids' => $deptRules,
            'dept_ids.*' => $deptItemRules ?: ['prohibited'],
            'position_ids' => $posRules,
            'position_ids.*' => $posItemRules ?: ['prohibited'],
        ]);

        $deptIds = array_values(array_unique(array_map('intval', $validated['dept_ids'])));
        $positionIds = array_values(array_unique(array_map('intval', $validated['position_ids'])));

        if (! Schema::hasTable('departments')) {
            $deptIds = [];
        }
        if (! Schema::hasTable('positions')) {
            $positionIds = [];
        }

        // 职务挂在部门下：保存时合并所选职务的 dept_id，一并写入用户部门关系
        if ($positionIds !== [] && Schema::hasTable('positions')) {
            $fromPositions = PositionModel::query()
                ->whereIn('id', $positionIds)
                ->where('status', 1)
                ->pluck('dept_id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $deptIds = array_values(array_unique(array_merge($deptIds, $fromPositions)));
        }

        if ($deptIds !== [] && Schema::hasTable('departments')) {
            $deptIds = DepartmentModel::query()
                ->whereIn('id', $deptIds)
                ->where('status', 1)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->values()
                ->all();
        } else {
            $deptIds = [];
        }

        $this->syncOrg($request, $adminUser, $deptIds, $positionIds);

        return response()->json([
            'ok' => true,
            'message' => '职务与关联部门已更新。',
        ]);
    }

    /**
     * 分配门店弹窗：启用中的门店列表 + 职务列表（与组织选项一致）。
     */
    public function apiStoreAssignmentOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $actor->canAdminPermission('perm.admin.api.users.update')) {
            return response()->json(['message' => '无权查看门店分配选项'], 403);
        }

        $positions = [];
        $orgResp = $this->apiOrgOptions($request);
        $orgPayload = json_decode($orgResp->getContent(), true);
        if (is_array($orgPayload)) {
            $positions = $orgPayload['data']['positions'] ?? [];
        }

        $stores = [];
        if (Schema::hasTable('stores')) {
            $stores = StoreModel::query()
                ->where('status', 1)
                ->orderBy('name')
                ->orderByDesc('id')
                ->get(['id', 'code', 'name', 'store_type'])
                ->map(static function (StoreModel $s) {
                    return [
                        'id' => (int) $s->id,
                        'code' => trim((string) ($s->code ?? '')),
                        'name' => trim((string) ($s->name ?? '')),
                        'store_type' => (int) ($s->store_type ?? 1),
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'data' => [
                'stores' => $stores,
                'positions' => $positions,
            ],
        ]);
    }

    /** 某用户当前门店任职列表（编辑分配用）。 */
    public function apiUserStores(Request $request, UserModel $adminUser): JsonResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            return response()->json(['message' => '超级管理员无需分配门店'], 403);
        }

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $actor->canAdminPermission('perm.admin.api.users.update')) {
            return response()->json(['message' => '无权查看'], 403);
        }

        if (! Schema::hasTable('user_stores')) {
            return response()->json(['data' => []]);
        }

        $map = UserModel::userStoresMapForUserIds([(int) $adminUser->id]);

        return response()->json(['data' => $map[(int) $adminUser->id] ?? []]);
    }

    /**
     * 全量覆盖用户门店任职：须恰好一个主门店（有任一行时）。
     *
     * @throws ValidationException
     */
    public function apiUserStoresSync(Request $request, UserModel $adminUser): JsonResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            return response()->json(['message' => '超级管理员不可分配门店'], 403);
        }

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $actor->canAdminPermission('perm.admin.api.users.update')) {
            return response()->json(['message' => '无权保存'], 403);
        }

        if (! Schema::hasTable('user_stores')) {
            return response()->json(['message' => '用户门店关联表未创建'], 503);
        }
        if (! Schema::hasTable('stores') || ! Schema::hasTable('positions')) {
            throw ValidationException::withMessages([
                'assignments' => '门店或职务表不可用。',
            ]);
        }

        $validated = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*.store_id' => ['required', 'integer', 'min:1'],
            'assignments.*.position_id' => ['required', 'integer', 'min:1'],
            'assignments.*.is_main' => ['required', 'integer', 'in:0,1'],
            'assignments.*.start_date' => ['required', 'date'],
            'assignments.*.end_date' => ['nullable', 'date'],
        ]);

        /** @var list<array{store_id:int, position_id:int, is_main:int, start_date:string, end_date?:string|null}> $assignments */
        $assignments = [];
        foreach ($validated['assignments'] as $row) {
            $sid = (int) $row['store_id'];
            $pid = (int) $row['position_id'];
            $endRaw = $row['end_date'] ?? null;
            $end = ($endRaw !== null && $endRaw !== '') ? Carbon::parse((string) $endRaw)->format('Y-m-d') : '9999-12-31';
            $start = Carbon::parse((string) $row['start_date'])->format('Y-m-d');
            if (Carbon::parse($start)->gt(Carbon::parse($end))) {
                throw ValidationException::withMessages([
                    'assignments' => '生效日期不能晚于失效日期。',
                ]);
            }
            $assignments[] = [
                'store_id' => $sid,
                'position_id' => $pid,
                'is_main' => (int) $row['is_main'],
                'start_date' => $start,
                'end_date' => $end,
            ];
        }

        if ($assignments === []) {
            DB::table('user_stores')->where('user_id', $adminUser->id)->delete();

            return response()->json(['ok' => true, 'message' => '已清空门店分配。']);
        }

        $dup = [];
        foreach ($assignments as $a) {
            $k = $a['store_id'].'-'.$a['position_id'];
            if (isset($dup[$k])) {
                throw ValidationException::withMessages([
                    'assignments' => '同一门店与职务的组合不能重复。',
                ]);
            }
            $dup[$k] = true;
        }

        $mainCount = count(array_filter($assignments, static fn (array $a) => $a['is_main'] === 1));
        if ($mainCount !== 1) {
            throw ValidationException::withMessages([
                'assignments' => '须指定且仅能指定一个「主门店」（is_main = 1）。',
            ]);
        }

        $storeIds = array_values(array_unique(array_map(static fn (array $a) => $a['store_id'], $assignments)));
        $positionIds = array_values(array_unique(array_map(static fn (array $a) => $a['position_id'], $assignments)));

        $validStores = StoreModel::query()
            ->where('status', 1)
            ->whereIn('id', $storeIds)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        if (count($validStores) !== count($storeIds)) {
            throw ValidationException::withMessages(['assignments' => '存在无效或未启用的门店。']);
        }

        $validPos = PositionModel::query()
            ->where('status', 1)
            ->whereIn('id', $positionIds)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        if (count($validPos) !== count($positionIds)) {
            throw ValidationException::withMessages(['assignments' => '存在无效或未启用的职务。']);
        }

        $now = time();
        DB::transaction(function () use ($adminUser, $assignments, $now) {
            DB::table('user_stores')->where('user_id', $adminUser->id)->delete();
            foreach ($assignments as $a) {
                DB::table('user_stores')->insert([
                    'user_id' => $adminUser->id,
                    'store_id' => $a['store_id'],
                    'position_id' => $a['position_id'],
                    'is_main' => $a['is_main'],
                    'start_date' => $a['start_date'],
                    'end_date' => $a['end_date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return response()->json(['ok' => true, 'message' => '门店分配已更新。']);
    }

    /** Blade 切换启用/禁用，须备注；超级管理员不可改。 */
    public function updateStatus(Request $request, UserModel $adminUser): RedirectResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            abort(403, '超级管理员不可更新状态。');
        }

        $v = self::statusValidation();
        $validated = $request->validate($v['rules'], $v['messages']);

        $status = (int) $validated['status'];
        $remark = trim((string) ($validated['status_remark'] ?? ''));

        $this->updateStatusInternal($request, $adminUser, $status, $remark);

        return redirect()
            ->route('admin.users.index')
            ->with('success', '用户状态已更新。');
    }

    /** SPA PATCH 状态 + 备注。 */
    public function apiUpdateStatus(Request $request, UserModel $adminUser): JsonResponse
    {
        if ($adminUser->isSuperAdminAccount()) {
            abort(403, '超级管理员不可更新状态。');
        }

        $v = self::statusValidation();
        $validated = $request->validate($v['rules'], $v['messages']);

        $status = (int) $validated['status'];
        $remark = trim((string) ($validated['status_remark'] ?? ''));

        $this->updateStatusInternal($request, $adminUser, $status, $remark);

        return response()->json([
            'ok' => true,
            'message' => '用户状态已更新。',
            'data' => $adminUser->fresh(),
        ]);
    }

    // ——— 校验规则与用户业务 ———

    /** @return array<string, mixed> */
    protected static function listFilterRules(): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'role_id' => ['nullable', 'integer', 'min:1'],
            'presence_today' => ['nullable', 'string', Rule::in(array_keys(UserModel::adminListPresenceFilterOptions()))],
        ];
        if (RoleModel::isTablePresent()) {
            $rules['role_id'][] = Rule::exists('roles', 'id');
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    protected static function apiListFilterRules(): array
    {
        return array_merge(self::listFilterRules(), [
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    protected static function mergeEmptyPhone(Request $request): void
    {
        $request->merge([
            'phone' => $request->input('phone') === '' ? null : $request->input('phone'),
        ]);
    }

    /** 创建用户：空账号转 null（便于 nullable 校验）；手机号空串转 null。 */
    protected static function mergeCreateUserRequest(Request $request): void
    {
        self::mergeEmptyPhone($request);
        $acc = $request->input('account');
        if ($acc === null || (is_string($acc) && trim($acc) === '')) {
            $request->merge(['account' => null]);
        } elseif (is_string($acc)) {
            $request->merge(['account' => trim($acc)]);
        }
        $pw = $request->input('password');
        if ($pw === null || (is_string($pw) && trim($pw) === '')) {
            $request->merge(['password' => null]);
        } elseif (is_string($pw)) {
            $request->merge(['password' => trim($pw)]);
        }
    }

    /**
     * @return array{
     *     rules: array<string, mixed>,
     *     messages: array<string, string>,
     * }
     */
    protected static function createUserValidation(): array
    {
        return [
            'rules' => [
                'account' => [
                    'nullable',
                    'string',
                    'max:50',
                    Rule::unique('users', 'account'),
                    Rule::notIn(['admin']),
                ],
                'real_name' => ['required', 'string', 'max:50'],
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('users', 'phone'),
                ],
                'password' => ['nullable', 'string', 'min:6'],
            ],
            'messages' => [
                'account.not_in' => '不能使用保留账号 admin。',
            ],
        ];
    }

    /**
     * @return array{rules: array<string, mixed>}
     */
    protected static function updateUserValidationRules(UserModel $user): array
    {
        return [
            'rules' => [
                'account' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('users', 'account')->ignore($user->id),
                ],
                'real_name' => ['required', 'string', 'max:50'],
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('users', 'phone')->ignore($user->id),
                ],
                'password' => ['nullable', 'string', 'min:6'],
            ],
        ];
    }

    /**
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    protected static function statusValidation(): array
    {
        return [
            'rules' => [
                'status' => ['required', 'integer', 'in:0,1'],
                'status_remark' => ['required', 'string', 'max:500'],
            ],
            'messages' => [
                'status_remark.required' => '切换状态时请填写备注。',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     users: \Illuminate\Contracts\Pagination\LengthAwarePaginator,
     *     searchQuery: string,
     *     filterRoleId: int|null,
     *     perPage: int,
     * }
     */
    protected function bladeIndexPayload(array $validated): array
    {
        $keyword = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);
        $roleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;
        $presenceToday = isset($validated['presence_today']) && is_string($validated['presence_today']) && $validated['presence_today'] !== ''
            ? $validated['presence_today']
            : null;

        $users = UserModel::adminListQuery($keyword, $roleId, $presenceToday)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $presenceUserIds = $users->getCollection()->pluck('id')->map(static fn ($id) => (int) $id)->values()->all();
        $presenceMetaMap = UserModel::presenceTodayMetaMapForUserIds($presenceUserIds);

        return [
            'users' => $users,
            'searchQuery' => $keyword,
            'filterRoleId' => $roleId,
            'filterPresenceToday' => $presenceToday,
            'perPage' => $perPage,
            'presenceMetaMap' => $presenceMetaMap,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{data: mixed, paginator: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    protected function apiIndexPayload(array $validated): array
    {
        $keyword = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);
        $roleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;
        $presenceToday = isset($validated['presence_today']) && is_string($validated['presence_today']) && $validated['presence_today'] !== ''
            ? $validated['presence_today']
            : null;

        return UserModel::paginatedAdminApiList($keyword, $roleId, $perPage, $presenceToday);
    }

    /**
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    protected function createUser(Request $request, array $validated): UserModel
    {
        $account = isset($validated['account']) ? trim((string) $validated['account']) : '';
        if ($account === '') {
            $account = DefaultUserAccount::uniqueForToday();
        }
        $validated['account'] = $account;

        $password = isset($validated['password']) ? trim((string) $validated['password']) : '';
        if ($password === '') {
            $password = (string) (SystemSettingModel::get(SystemSettingModel::KEY_DEFAULT_USER_PASSWORD) ?? '');
            if ($password === '') {
                $password = (string) config('admin.default_user_password', '');
            }
        }
        if (strlen($password) < 6) {
            throw ValidationException::withMessages([
                'password' => ['请设置密码（至少 6 位），或在「系统配置」中设置默认密码，或在 .env 中配置不少于 6 位的 DEFAULT_USER_PASSWORD。'],
            ]);
        }
        $validated['password'] = $password;

        $roleIds = UserModel::defaultNewUserRoleIds();
        $now = time();
        $requestData = [
            'account' => $validated['account'],
            'real_name' => $validated['real_name'],
            'phone' => $validated['phone'],
            'role_ids' => $roleIds,
        ];

        try {
            $createdUser = UserModel::query()->create([
                'account' => $validated['account'],
                'password' => $validated['password'],
                'real_name' => $validated['real_name'],
                'phone' => $validated['phone'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            try {
                $createdUser->syncRolesFromIds($roleIds);
            } catch (ValidationException $e) {
                $createdUser->delete();
                throw $e;
            }

            return $createdUser;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'create',
                'admin_user',
                null,
                0,
                '创建用户失败：'.$e->getMessage(),
                $requestData
            );
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function updateProfile(Request $request, UserModel $user, array $validated): void
    {
        $data = [
            'account' => $validated['account'],
            'real_name' => $validated['real_name'],
            'phone' => ($validated['phone'] ?? '') === '' ? null : $validated['phone'],
            'updated_at' => time(),
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $requestData = [
            'account' => $validated['account'],
            'real_name' => $validated['real_name'] ?: null,
            'phone' => ($validated['phone'] ?? '') === '' ? null : $validated['phone'],
        ];

        try {
            $user->update($data);
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'update',
                'admin_user',
                (int) $user->id,
                0,
                '更新用户失败：'.$e->getMessage(),
                $requestData
            );
            throw $e;
        }
    }

    /**
     * @param  list<int>  $roleIds
     */
    protected function syncRoles(Request $request, UserModel $user, array $roleIds): void
    {
        $requestData = ['user_id' => (int) $user->id, 'role_ids' => $roleIds];

        try {
            $user->syncRolesFromIds($roleIds);

            UserLogModel::insertFromRequest(
                $request,
                'operation',
                'user',
                'update',
                'admin_user_roles',
                (int) $user->id,
                1,
                '已更新用户角色。',
                $requestData
            );
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'update',
                'admin_user_roles',
                (int) $user->id,
                0,
                '更新用户角色失败：'.$e->getMessage(),
                $requestData
            );
            throw $e;
        }
    }

    /**
     * @param  list<int>  $deptIds
     * @param  list<int>  $positionIds
     */
    protected function syncOrg(Request $request, UserModel $user, array $deptIds, array $positionIds): void
    {
        $requestData = [
            'user_id' => (int) $user->id,
            'dept_ids' => $deptIds,
            'position_ids' => $positionIds,
        ];

        try {
            $user->syncOrgFromIds($deptIds, $positionIds);

            UserLogModel::insertFromRequest(
                $request,
                'operation',
                'user',
                'update',
                'admin_user_org',
                (int) $user->id,
                1,
                '已更新用户职务及关联部门。',
                $requestData
            );
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'update',
                'admin_user_org',
                (int) $user->id,
                0,
                '更新用户职务及关联部门失败：'.$e->getMessage(),
                $requestData
            );
            throw $e;
        }
    }

    protected function updateStatusInternal(Request $request, UserModel $user, int $status, string $remark): void
    {
        $requestData = [
            'status' => $status,
            'status_remark' => $remark,
        ];

        try {
            $user->update([
                'status' => $status,
                'updated_at' => time(),
            ]);
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'update',
                'admin_user',
                (int) $user->id,
                0,
                '更新用户状态失败：'.$e->getMessage(),
                $requestData
            );
            throw $e;
        }
    }
}
