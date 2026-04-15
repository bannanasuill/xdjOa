<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\RoleModel;
use App\Models\StoreModel;
use App\Models\SystemSettingModel;
use App\Models\UserInviteCodeModel;
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
    private function canManageSuperAdmin(?UserModel $actor): bool
    {
        return $actor instanceof UserModel && $actor->isSuperAdminAccount();
    }

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
            && ! $actor->canAdminPermission(UserModel::API_PERMISSION_DESTROY)
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

        if (
            ! $actor->canAdminPermission('perm.admin.api.users.update')
            && ! $actor->canAdminPermission('perm.admin.api.users.store')
        ) {
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

        return response()->json([
            'data' => [
                'departments' => $departments,
                'positions' => self::activePositionOptionsForPicker(),
            ],
        ]);
    }

    /**
     * 用户列表「按职务筛选」、分配职务等下拉：启用职务 + 部门名称（与 org-options 中 positions 一致）。
     *
     * @return list<array{id:int, name:string, dept_id:int, dept_name:string}>
     */
    private static function activePositionOptionsForPicker(): array
    {
        if (! Schema::hasTable('positions')) {
            return [];
        }
        $plist = PositionModel::query()
            ->where('status', 1)
            ->orderBy('dept_id')
            ->orderByDesc('level')
            ->orderByDesc('id')
            ->get();
        $deptIds = $plist->pluck('dept_id')->unique()->filter()->all();
        $depts = [];
        if ($deptIds !== [] && Schema::hasTable('departments')) {
            $depts = DepartmentModel::query()
                ->whereIn('id', $deptIds)
                ->get(['id', 'name'])
                ->keyBy('id');
        }

        return $plist->map(static function (PositionModel $p) use ($depts) {
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

    /**
     * 用户列表筛选用职务下拉（仅需「用户列表」读权限）。
     */
    public function apiPositionFilterOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $actor->canAdminPermission('perm.admin.api.users.index')) {
            return response()->json(['message' => '无权查看职务选项'], 403);
        }

        return response()->json(['data' => self::activePositionOptionsForPicker()]);
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
            'filterPositionId' => $payload['filterPositionId'],
            'positionFilterOptions' => self::activePositionOptionsForPicker(),
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
            'options' => [
                'status_options' => UserModel::employmentStatusOptions(),
            ],
        ]);
    }

    /** SPA 邀请码列表 JSON。 */
    public function apiInviteIndex(Request $request): JsonResponse
    {
        if (! Schema::hasTable('user_invite_codes')) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            ]);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(UserModel::employmentStatusOptions()))],
            'used' => ['nullable', 'integer', 'in:0,1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));
        $status = isset($validated['status']) ? (int) $validated['status'] : null;
        $used = isset($validated['used']) ? (int) $validated['used'] : null;
        $perPage = (int) ($validated['per_page'] ?? 20);

        $builder = DB::table('user_invite_codes as i')
            ->leftJoin('users as cu', 'cu.id', '=', 'i.created_by')
            ->leftJoin('users as uu', 'uu.id', '=', 'i.used_user_id')
            ->leftJoin('departments as d', 'd.id', '=', 'i.dept_id')
            ->leftJoin('positions as p', 'p.id', '=', 'i.position_id')
            ->leftJoin('stores as s', 's.id', '=', 'i.store_id');

        if ($q !== '') {
            $builder->where(function ($w) use ($q) {
                $w->where('i.code', 'like', '%'.$q.'%')
                    ->orWhere('cu.account', 'like', '%'.$q.'%')
                    ->orWhere('cu.real_name', 'like', '%'.$q.'%')
                    ->orWhere('uu.account', 'like', '%'.$q.'%')
                    ->orWhere('uu.real_name', 'like', '%'.$q.'%');
            });
        }
        if ($status !== null) {
            $builder->where('i.register_status', $status);
        }
        if ($used !== null) {
            if ($used === 1) {
                $builder->whereNotNull('i.used_at');
            } else {
                $builder->whereNull('i.used_at');
            }
        }

        $paginator = $builder
            ->orderByDesc('i.id')
            ->paginate($perPage, [
                'i.id',
                'i.code',
                'i.dept_id',
                'i.position_id',
                'i.store_id',
                'i.register_status',
                'i.valid_hours',
                'i.expires_at',
                'i.used_at',
                'i.used_user_id',
                'i.created_by',
                'i.created_at',
                'i.updated_at',
                'cu.account as created_by_account',
                'cu.real_name as created_by_name',
                'uu.account as used_user_account',
                'uu.real_name as used_user_name',
                'd.name as dept_name',
                'p.name as position_name',
                's.name as store_name',
            ]);

        $rows = collect($paginator->items())->map(
            static fn ($r) => self::serializeInviteRow($r)
        )->values()->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'options' => [
                'status_options' => UserModel::employmentStatusOptions(),
                'used_options' => [
                    ['value' => 1, 'label' => '已使用'],
                    ['value' => 0, 'label' => '未使用'],
                ],
            ],
        ]);
    }

    /** 邀请码详情。 */
    public function apiInviteShow(Request $request, int $inviteId): JsonResponse
    {
        if (! Schema::hasTable('user_invite_codes')) {
            return response()->json(['message' => '邀请码表不存在'], 404);
        }

        $row = DB::table('user_invite_codes as i')
            ->leftJoin('users as cu', 'cu.id', '=', 'i.created_by')
            ->leftJoin('users as uu', 'uu.id', '=', 'i.used_user_id')
            ->leftJoin('departments as d', 'd.id', '=', 'i.dept_id')
            ->leftJoin('positions as p', 'p.id', '=', 'i.position_id')
            ->leftJoin('stores as s', 's.id', '=', 'i.store_id')
            ->where('i.id', $inviteId)
            ->first([
                'i.id',
                'i.code',
                'i.dept_id',
                'i.position_id',
                'i.store_id',
                'i.register_status',
                'i.valid_hours',
                'i.expires_at',
                'i.used_at',
                'i.used_user_id',
                'i.created_by',
                'i.created_at',
                'i.updated_at',
                'cu.account as created_by_account',
                'cu.real_name as created_by_name',
                'uu.account as used_user_account',
                'uu.real_name as used_user_name',
                'd.name as dept_name',
                'p.name as position_name',
                's.name as store_name',
            ]);

        if ($row === null) {
            return response()->json(['message' => '邀请码不存在'], 404);
        }

        return response()->json(['data' => self::serializeInviteRow($row)]);
    }

    /** 邀请码状态更新（仅未使用的邀请码可修改）。 */
    public function apiInviteUpdateStatus(Request $request, int $inviteId): JsonResponse
    {
        if (! Schema::hasTable('user_invite_codes')) {
            return response()->json(['message' => '邀请码表不存在'], 404);
        }
        $validated = $request->validate([
            'status' => ['required', 'integer', Rule::in(array_keys(UserModel::employmentStatusOptions()))],
        ], [
            'status.required' => '请选择状态',
            'status.in' => '状态不合法',
        ]);

        $row = DB::table('user_invite_codes')
            ->where('id', $inviteId)
            ->first(['id', 'register_status', 'used_at']);
        if ($row === null) {
            return response()->json(['message' => '邀请码不存在'], 404);
        }
        if ($row->used_at !== null) {
            return response()->json(['message' => '邀请码已被使用，不可修改状态'], 422);
        }

        $nextStatus = (int) $validated['status'];
        if ((int) ($row->register_status ?? UserModel::STATUS_ON_JOB) === $nextStatus) {
            return response()->json(['ok' => true, 'message' => '状态未变化']);
        }

        DB::table('user_invite_codes')
            ->where('id', $inviteId)
            ->update([
                'register_status' => $nextStatus,
                'updated_at' => time(),
            ]);

        UserLogModel::insertFromRequest(
            $request,
            'operation',
            'user',
            'update',
            'admin_user_invite',
            $inviteId,
            1,
            '已更新邀请码状态。',
            [
                'id' => $inviteId,
                'old_status' => (int) ($row->register_status ?? UserModel::STATUS_ON_JOB),
                'new_status' => $nextStatus,
            ]
        );

        return response()->json([
            'ok' => true,
            'message' => '状态已更新。',
            'data' => [
                'id' => $inviteId,
                'status' => $nextStatus,
                'status_label' => UserModel::employmentStatusLabel($nextStatus),
            ],
        ]);
    }

    /**
     * @param  object  $r
     * @return array<string, mixed>
     */
    private static function serializeInviteRow(object $r): array
    {
        $now = time();
        $status = (int) ($r->register_status ?? UserModel::STATUS_ON_JOB);
        $expiresAt = $r->expires_at !== null ? (int) $r->expires_at : null;
        $usedAt = $r->used_at !== null ? (int) $r->used_at : null;
        $isExpired = $usedAt === null && $expiresAt !== null && $expiresAt < $now;

        return [
            'id' => (int) ($r->id ?? 0),
            'code' => (string) ($r->code ?? ''),
            'dept_id' => $r->dept_id !== null ? (int) $r->dept_id : null,
            'dept_name' => $r->dept_name ?? null,
            'position_id' => $r->position_id !== null ? (int) $r->position_id : null,
            'position_name' => $r->position_name ?? null,
            'store_id' => $r->store_id !== null ? (int) $r->store_id : null,
            'store_name' => $r->store_name ?? null,
            'status' => $status,
            'status_label' => UserModel::employmentStatusLabel($status),
            'valid_hours' => (int) ($r->valid_hours ?? 0),
            'expires_at' => $expiresAt,
            'used_at' => $usedAt,
            'is_used' => $usedAt !== null,
            'is_expired' => $isExpired,
            'used_user_id' => $r->used_user_id !== null ? (int) $r->used_user_id : null,
            'used_user_account' => $r->used_user_account ?? null,
            'used_user_name' => $r->used_user_name ?? null,
            'created_by' => $r->created_by !== null ? (int) $r->created_by : null,
            'created_by_account' => $r->created_by_account ?? null,
            'created_by_name' => $r->created_by_name ?? null,
            'created_at' => $r->created_at !== null ? (int) $r->created_at : null,
            'updated_at' => $r->updated_at !== null ? (int) $r->updated_at : null,
        ];
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

    /** SPA POST 生成用户注册邀请码（可一次生成多条）。 */
    public function apiStore(Request $request): JsonResponse
    {
        $v = self::createInviteValidation();
        $validated = $request->validate($v['rules'], $v['messages']);
        $pack = $this->createInviteCodes($request, $validated);
        $n = (int) ($pack['count'] ?? 1);

        return response()->json([
            'ok' => true,
            'message' => $n > 1 ? '已生成 '.$n.' 条邀请码。' : '邀请码已生成。',
            'data' => $pack,
        ], 201);
    }

    /** Blade 编辑页；超级管理员不可进。 */
    public function edit(UserModel $adminUser): View
    {
        $actor = auth()->user();
        if ($adminUser->isSuperAdminAccount() && ! $this->canManageSuperAdmin($actor instanceof UserModel ? $actor : null)) {
            abort(403, '超级管理员不可编辑。');
        }

        return view('admin.users.edit', [
            'user' => $adminUser,
        ]);
    }

    /** Blade PUT 更新基础资料（不含角色）。 */
    public function update(Request $request, UserModel $adminUser): RedirectResponse
    {
        $actor = $request->user();
        if ($adminUser->isSuperAdminAccount() && ! $this->canManageSuperAdmin($actor instanceof UserModel ? $actor : null)) {
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
        $actor = $request->user();
        if ($adminUser->isSuperAdminAccount() && ! $this->canManageSuperAdmin($actor instanceof UserModel ? $actor : null)) {
            abort(403, '仅超级管理员可编辑超级管理员账号。');
        }

        $rules = self::updateUserValidationRules($adminUser)['rules'];
        $validated = $request->validate($rules);

        $this->updateProfile($request, $adminUser, $validated);

        if (array_key_exists('role_ids', $validated)) {
            $roleIds = array_values(array_unique(array_map('intval', $validated['role_ids'])));
            $this->syncRoles($request, $adminUser->fresh(), $roleIds);
        }

        return response()->json([
            'ok' => true,
            'message' => '用户信息已更新。',
            'data' => $adminUser->fresh(),
        ]);
    }

    /** SPA PATCH 分配角色。 */
    public function apiSyncRoles(Request $request, UserModel $adminUser): JsonResponse
    {
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
        if (
            ! $actor->canAdminPermission('perm.admin.api.users.update')
            && ! $actor->canAdminPermission('perm.admin.api.users.store')
        ) {
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

    /**
     * 邀请码创建表单选项：部门 / 职务 / 店铺（仅需新增用户权限）。
     */
    public function apiInviteOptions(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $actor->canAdminPermission('perm.admin.api.users.store')) {
            return response()->json(['message' => '无权查看邀请码选项'], 403);
        }

        $departments = [];
        if (Schema::hasTable('departments')) {
            $departments = DepartmentModel::query()
                ->where('status', 1)
                ->orderBy('sort')
                ->orderByDesc('id')
                ->get(['id', 'name'])
                ->map(static fn (DepartmentModel $d) => [
                    'id' => (int) $d->id,
                    'name' => trim((string) ($d->name ?? '')),
                ])->values()->all();
        }

        $positions = self::activePositionOptionsForPicker();
        $stores = [];
        if (Schema::hasTable('stores')) {
            $stores = StoreModel::query()
                ->where('status', 1)
                ->orderBy('name')
                ->orderByDesc('id')
                ->get(['id', 'code', 'name'])
                ->map(static fn (StoreModel $s) => [
                    'id' => (int) $s->id,
                    'code' => trim((string) ($s->code ?? '')),
                    'name' => trim((string) ($s->name ?? '')),
                ])->values()->all();
        }

        return response()->json([
            'data' => [
                'departments' => $departments,
                'positions' => $positions,
                'stores' => $stores,
                'status_options' => UserModel::employmentStatusOptions(),
            ],
        ]);
    }

    /** 某用户当前门店任职列表（编辑分配用）。 */
    public function apiUserStores(Request $request, UserModel $adminUser): JsonResponse
    {
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
        $actor = $request->user();
        if ($adminUser->isSuperAdminAccount() && ! $this->canManageSuperAdmin($actor instanceof UserModel ? $actor : null)) {
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
        $actor = $request->user();
        if ($adminUser->isSuperAdminAccount() && ! $this->canManageSuperAdmin($actor instanceof UserModel ? $actor : null)) {
            abort(403, '仅超级管理员可更新超级管理员状态。');
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

    /** SPA DELETE：物理删除用户及关联数据（须单独授权）。 */
    public function apiDestroy(Request $request, UserModel $adminUser): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => '未登录'], 401);
        }

        if (! $actor->canAdminPermission(UserModel::API_PERMISSION_DESTROY)) {
            return response()->json(['message' => '无权删除用户'], 403);
        }

        if ((int) $actor->id === (int) $adminUser->id) {
            return response()->json(['message' => '不能删除当前登录账号'], 422);
        }

        if ($adminUser->isProtectedSystemAdmin()) {
            return response()->json(['message' => '受保护账号不可删除'], 403);
        }

        $uid = (int) $adminUser->id;
        $snapshot = [
            'id' => $uid,
            'account' => $adminUser->account,
            'real_name' => $adminUser->real_name,
        ];

        try {
            if (! UserModel::physicalDeleteById($uid)) {
                return response()->json(['message' => '用户不存在或已删除'], 404);
            }

            UserLogModel::insertFromRequest(
                $request,
                'operation',
                'user',
                'delete',
                'admin_user',
                $uid,
                1,
                '已物理删除用户：'.(string) ($snapshot['account'] ?? '').' / '.(string) ($snapshot['real_name'] ?? ''),
                $snapshot
            );
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'delete',
                'admin_user',
                $uid,
                0,
                '删除用户失败：'.$e->getMessage(),
                $snapshot
            );

            throw $e;
        }

        return response()->json(['ok' => true, 'message' => '用户已删除。']);
    }

    // ——— 校验规则与用户业务 ———

    /** @return array<string, mixed> */
    protected static function listFilterRules(): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
            'role_id' => ['nullable', 'integer', 'min:1'],
            'position_id' => ['nullable', 'integer', 'min:1'],
            'presence_today' => ['nullable', 'string', Rule::in(array_keys(UserModel::adminListPresenceFilterOptions()))],
            'status' => ['nullable', 'integer', Rule::in(array_keys(UserModel::employmentStatusOptions()))],
            'employment_scope' => ['nullable', 'string', Rule::in(['left', 'not_left'])],
        ];
        if (RoleModel::isTablePresent()) {
            $rules['role_id'][] = Rule::exists('roles', 'id');
        }
        if (Schema::hasTable('positions')) {
            $rules['position_id'][] = Rule::exists('positions', 'id');
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
                'role_ids' => ['sometimes', 'array'],
                'role_ids.*' => ['integer', 'exists:roles,id'],
            ],
            'messages' => [
                'account.not_in' => '不能使用保留账号 admin。',
            ],
        ];
    }

    /**
     * @return array{
     *     rules: array<string, mixed>,
     *     messages: array<string, string>,
     * }
     */
    protected static function createInviteValidation(): array
    {
        return [
            'rules' => [
                'dept_id' => ['required', 'integer', 'min:1', Rule::exists('departments', 'id')->where('status', 1)],
                'position_id' => ['required', 'integer', 'min:1', Rule::exists('positions', 'id')->where('status', 1)],
                'store_id' => ['required', 'integer', 'min:1', Rule::exists('stores', 'id')->where('status', 1)],
                'valid_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'status' => ['required', 'integer', Rule::in(array_keys(UserModel::employmentStatusOptions()))],
                'count' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ],
            'messages' => [
                'count.min' => '生成数量至少为 1',
                'count.max' => '单次最多生成 100 条邀请码',
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
                'role_ids' => ['sometimes', 'array'],
                'role_ids.*' => ['integer', 'exists:roles,id'],
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
                'status' => ['required', 'integer', Rule::in(array_keys(UserModel::employmentStatusOptions()))],
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
     *     filterPositionId: int|null,
     *     perPage: int,
     * }
     */
    protected function bladeIndexPayload(array $validated): array
    {
        $keyword = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);
        $roleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;
        $positionId = isset($validated['position_id']) ? (int) $validated['position_id'] : null;
        $status = isset($validated['status']) ? (int) $validated['status'] : null;
        $employmentScope = isset($validated['employment_scope']) && is_string($validated['employment_scope'])
            ? trim($validated['employment_scope'])
            : null;
        $presenceToday = isset($validated['presence_today']) && is_string($validated['presence_today']) && $validated['presence_today'] !== ''
            ? $validated['presence_today']
            : null;

        $users = UserModel::adminListQuery($keyword, $roleId, $presenceToday, $positionId, $status, $employmentScope)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $presenceUserIds = $users->getCollection()->pluck('id')->map(static fn ($id) => (int) $id)->values()->all();
        $presenceMetaMap = UserModel::presenceTodayMetaMapForUserIds($presenceUserIds);

        return [
            'users' => $users,
            'searchQuery' => $keyword,
            'filterRoleId' => $roleId,
            'filterPositionId' => $positionId > 0 ? $positionId : null,
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
        $positionId = isset($validated['position_id']) ? (int) $validated['position_id'] : null;
        $status = isset($validated['status']) ? (int) $validated['status'] : null;
        $employmentScope = isset($validated['employment_scope']) && is_string($validated['employment_scope'])
            ? trim($validated['employment_scope'])
            : null;
        $presenceToday = isset($validated['presence_today']) && is_string($validated['presence_today']) && $validated['presence_today'] !== ''
            ? $validated['presence_today']
            : null;

        return UserModel::paginatedAdminApiList(
            $keyword,
            $roleId,
            $perPage,
            $presenceToday,
            $positionId > 0 ? $positionId : null,
            $status,
            $employmentScope,
        );
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
        if (array_key_exists('role_ids', $validated)) {
            $roleIds = array_values(array_unique(array_map('intval', $validated['role_ids'])));
        }
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
                'status' => UserModel::STATUS_ON_JOB,
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
     * @return array<string, mixed>
     */
    protected function createInviteCodes(Request $request, array $validated): array
    {
        $deptId = (int) $validated['dept_id'];
        $positionId = (int) $validated['position_id'];
        $storeId = (int) $validated['store_id'];
        $validHours = (int) $validated['valid_hours'];
        $status = (int) $validated['status'];
        $count = (int) ($validated['count'] ?? 1);
        $count = min(100, max(1, $count));

        $positionDeptId = (int) (PositionModel::query()->whereKey($positionId)->value('dept_id') ?? 0);
        if ($positionDeptId <= 0 || $positionDeptId !== $deptId) {
            throw ValidationException::withMessages([
                'position_id' => ['所选职务不属于当前部门，请重新选择。'],
            ]);
        }

        $now = time();
        $expiresAt = $now + $validHours * 3600;
        $createdBy = (int) ($request->user()?->id ?? 0) ?: null;
        $invites = [];
        $logRows = [];

        try {
            DB::transaction(function () use (
                $count,
                $deptId,
                $positionId,
                $storeId,
                $status,
                $validHours,
                $expiresAt,
                $now,
                $createdBy,
                &$invites,
                &$logRows
            ) {
                for ($i = 0; $i < $count; $i++) {
                    $code = UserInviteCodeModel::generateUniqueCode(8);
                    $row = [
                        'code' => $code,
                        'dept_id' => $deptId,
                        'position_id' => $positionId,
                        'store_id' => $storeId,
                        'register_status' => $status,
                        'valid_hours' => $validHours,
                        'expires_at' => $expiresAt,
                        'used_at' => null,
                        'used_user_id' => null,
                        'created_by' => $createdBy,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    DB::table('user_invite_codes')->insert($row);
                    $invites[] = [
                        'code' => $code,
                        'expires_at' => $expiresAt,
                    ];
                    $logRows[] = $row;
                }
            });

            UserLogModel::insertFromRequest(
                $request,
                'operation',
                'user',
                'create',
                'admin_user_invite',
                null,
                1,
                $count > 1 ? '已批量生成 '.$count.' 条用户注册邀请码。' : '已生成用户注册邀请码。',
                ['count' => $count, 'rows' => $logRows]
            );
        } catch (Throwable $e) {
            UserLogModel::insertFromRequest(
                $request,
                'error',
                'user',
                'create',
                'admin_user_invite',
                null,
                0,
                '生成用户邀请码失败：'.$e->getMessage(),
                ['count' => $count, 'dept_id' => $deptId, 'position_id' => $positionId, 'store_id' => $storeId]
            );
            throw $e;
        }

        $first = $invites[0] ?? null;

        return [
            'count' => $count,
            'invites' => $invites,
            /** 兼容单条：首条 code / expires_at */
            'code' => $first['code'] ?? '',
            'dept_id' => $deptId,
            'position_id' => $positionId,
            'store_id' => $storeId,
            'valid_hours' => $validHours,
            'status' => $status,
            'status_label' => UserModel::employmentStatusLabel($status),
            'expires_at' => $expiresAt,
        ];
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
