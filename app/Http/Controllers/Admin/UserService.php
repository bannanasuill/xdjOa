<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\RoleModel;
use App\Models\SystemSettingModel;
use App\Models\UserLogModel;
use App\Models\UserModel;
use App\Support\DefaultUserAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
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

    protected static function mergeEmptyPhoneEmail(Request $request): void
    {
        $request->merge([
            'phone' => $request->input('phone') === '' ? null : $request->input('phone'),
            'email' => $request->input('email') === '' ? null : $request->input('email'),
        ]);
    }

    /** 创建用户：空账号转 null（便于 nullable 校验）；手机号/邮箱同上。 */
    protected static function mergeCreateUserRequest(Request $request): void
    {
        self::mergeEmptyPhoneEmail($request);
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
                'email' => [
                    'nullable',
                    'email',
                    'max:100',
                    Rule::unique('users', 'email'),
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
                'email' => [
                    'nullable',
                    'email',
                    'max:100',
                    Rule::unique('users', 'email')->ignore($user->id),
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

        $users = UserModel::adminListQuery($keyword, $roleId)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'users' => $users,
            'searchQuery' => $keyword,
            'filterRoleId' => $roleId,
            'perPage' => $perPage,
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

        return UserModel::paginatedAdminApiList($keyword, $roleId, $perPage);
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
            'email' => $validated['email'],
            'role_ids' => $roleIds,
        ];

        try {
            $createdUser = UserModel::query()->create([
                'account' => $validated['account'],
                'password' => $validated['password'],
                'real_name' => $validated['real_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
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
            'email' => ($validated['email'] ?? '') === '' ? null : $validated['email'],
            'updated_at' => time(),
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $requestData = [
            'account' => $validated['account'],
            'real_name' => $validated['real_name'] ?: null,
            'phone' => ($validated['phone'] ?? '') === '' ? null : $validated['phone'],
            'email' => ($validated['email'] ?? '') === '' ? null : $validated['email'],
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
