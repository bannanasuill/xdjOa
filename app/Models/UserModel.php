<?php

namespace App\Models;

use App\Models\Concerns\LogsModelChangesToUserLogTrait;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\HasApiTokens;

/**
 * 业务用户实体（表名 users，物理表由数据库连接 prefix / DB_TABLE_PREFIX 拼接）：后台权限由角色-权限表拼装。
 */
class UserModel extends Authenticatable
{
    use HasApiTokens, HasFactory, LogsModelChangesToUserLogTrait, Notifiable;

    /** 写入业务日志表时使用的 target_type（模型审计）。 */
    protected string $userLogTargetType = 'admin_user';

    /** 写入业务日志表时使用的 module。 */
    protected string $userLogModule = 'user';

    /** 具备此权限才允许登录并访问后台（超级管理员不受限） */
    public const ADMIN_PANEL_LOGIN_PERMISSION = 'perm.admin.login';

    /** 用户列表「当下状态」筛选值（与 presence_today 展示文案对应）。 */
    public const PRESENCE_FILTER_NOT_ARRIVED = 'not_arrived';

    public const PRESENCE_FILTER_PRESENT = 'present';

    public const PRESENCE_FILTER_OUTING = 'outing';

    public const PRESENCE_FILTER_OFF_WORK = 'off_work';

    public const PRESENCE_FILTER_UNKNOWN = 'unknown';

    /**
     * @return array<string, string> code => 中文标签
     */
    public static function adminListPresenceFilterOptions(): array
    {
        return [
            self::PRESENCE_FILTER_NOT_ARRIVED => '未到岗',
            self::PRESENCE_FILTER_PRESENT => '到岗',
            self::PRESENCE_FILTER_OUTING => '外出',
            self::PRESENCE_FILTER_OFF_WORK => '下班',
            self::PRESENCE_FILTER_UNKNOWN => '其他',
        ];
    }

    public $timestamps = false;

    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'account',
        'password',
        'real_name',
        'phone',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'status' => 'integer',
    ];

    protected static function newFactory()
    {
        return UserFactory::new();
    }

    /** @see \Illuminate\Auth\Authenticatable 空列名即禁用持久化登录令牌。 */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * 用于会话认证的用户名字段（账号）。
     */
    public function username(): string
    {
        return 'account';
    }

    /**
     * 是否允许进入后台：系统管理员全放行；否则须具备 {@see ADMIN_PANEL_LOGIN_PERMISSION}。
     */
    public function canAccessAdminPanel(): bool
    {
        if ($this->isProtectedSystemAdmin()) {
            return true;
        }

        return $this->canAdminPermission(self::ADMIN_PANEL_LOGIN_PERMISSION);
    }

    /**
     * 是否视为「全量后台权限」载体：内置 admin 账号，或同时满足系统角色 + super_admin 的角色绑定。
     * 仅 is_system=1 但非 super_admin 的角色走 role_permissions，不自动全权限。
     */
    public function isProtectedSystemAdmin(): bool
    {
        if ($this->account === 'admin') {
            return true;
        }

        $tUr = 'user_roles';
        $tR = 'roles';
        if (! Schema::hasTable($tUr) || ! Schema::hasTable($tR)) {
            return false;
        }

        return DB::table($tUr)
            ->join($tR, $tR.'.id', '=', $tUr.'.role_id')
            ->where($tUr.'.user_id', $this->id)
            ->where($tR.'.is_system', 1)
            ->where($tR.'.code', RoleModel::CODE_SUPER_ADMIN)
            ->exists();
    }

    /**
     * 超级管理员：内置 admin 账号，或绑定了 super_admin 角色（列表里仅此类账号不可切换启用/禁用状态）。
     */
    public function isSuperAdminAccount(): bool
    {
        if ($this->account === 'admin') {
            return true;
        }

        $tUr = 'user_roles';
        $tR = 'roles';
        if (! Schema::hasTable($tUr) || ! Schema::hasTable($tR)) {
            return false;
        }

        return DB::table($tUr)
            ->join($tR, $tR.'.id', '=', $tUr.'.role_id')
            ->where($tUr.'.user_id', $this->id)
            ->where($tR.'.code', RoleModel::CODE_SUPER_ADMIN)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function getAdminPermissionCodes(): array
    {
        $key = 'admin_user_perm_codes_'.$this->getAuthIdentifier();
        $req = request();
        if ($req !== null && $req->attributes->has($key)) {
            /** @var list<string> */
            return $req->attributes->get($key);
        }

        $codes = $this->computeAdminPermissionCodes();
        if ($req !== null) {
            $req->attributes->set($key, $codes);
        }

        return $codes;
    }

    /** 是否具备指定权限码（空码视为通过）；超级管理员系统角色 / 内置 admin 恒 true。 */
    public function canAdminPermission(?string $code): bool
    {
        if ($code === null || $code === '') {
            return true;
        }

        if ($this->isProtectedSystemAdmin()) {
            return true;
        }

        return in_array($code, $this->getAdminPermissionCodes(), true);
    }

    /**
     * 非全量权限用户：按 user_roles → role_permissions 聚合成 permission code 列表。
     *
     * @return list<string>
     */
    protected function computeAdminPermissionCodes(): array
    {
        $tP = 'permissions';
        if (! Schema::hasTable($tP)) {
            return [];
        }

        if ($this->isProtectedSystemAdmin()) {
            return DB::table($tP)
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->orderBy('id')
                ->pluck('code')
                ->unique()
                ->values()
                ->all();
        }

        $tUr = 'user_roles';
        $tRp = 'role_permissions';
        if (! Schema::hasTable($tUr) || ! Schema::hasTable($tRp)) {
            return [];
        }

        $roleIds = DB::table($tUr)->where('user_id', $this->id)->pluck('role_id');
        if ($roleIds->isEmpty()) {
            return [];
        }

        $permIds = DB::table($tRp)->whereIn('role_id', $roleIds)->distinct()->pluck('permission_id');
        if ($permIds->isEmpty()) {
            return [];
        }

        return DB::table($tP)
            ->whereIn('id', $permIds)
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('id')
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 后台「当前用户」展示用角色列表（与用户列表中 roles 字段结构一致）。
     *
     * @return list<array{id:int, name:string, is_system:bool, code:string}>
     */
    public function getAdminRolesForDisplay(): array
    {
        $tUr = 'user_roles';
        $tR = 'roles';
        if (! Schema::hasTable($tUr) || ! Schema::hasTable($tR)) {
            if ($this->isProtectedSystemAdmin()) {
                return [
                    ['id' => 0, 'name' => '超级管理员', 'is_system' => true, 'code' => RoleModel::CODE_SUPER_ADMIN],
                ];
            }

            return [];
        }

        $rows = DB::table($tUr)
            ->join($tR, $tR.'.id', '=', $tUr.'.role_id')
            ->where($tUr.'.user_id', $this->id)
            ->select([
                $tR.'.id as role_id',
                $tR.'.name as role_name',
                $tR.'.code as role_code',
                $tR.'.is_system as is_system',
            ])
            ->orderByDesc($tR.'.is_system')
            ->orderBy($tR.'.id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $rid = (int) ($r->role_id ?? 0);
            $name = trim((string) ($r->role_name ?? ''));
            if ($rid <= 0 || $name === '') {
                continue;
            }
            $out[] = [
                'id' => $rid,
                'name' => $name,
                'is_system' => (int) ($r->is_system ?? 0) === 1,
                'code' => (string) ($r->role_code ?? ''),
            ];
        }

        if ($out === [] && $this->isProtectedSystemAdmin()) {
            $out[] = ['id' => 0, 'name' => '超级管理员', 'is_system' => true, 'code' => RoleModel::CODE_SUPER_ADMIN];
        }

        return $out;
    }

    /**
     * 用户管理列表查询构造器：全表 + 可选账号/姓名模糊 + 可选角色 + 可选今日当下状态 + 可选职务。
     */
    public static function adminListQuery(
        string $keyword,
        ?int $roleId = null,
        ?string $presenceFilter = null,
        ?int $positionId = null,
    ): Builder {
        $query = static::query();
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $like = '%'.addcslashes($keyword, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('account', 'like', $like)
                    ->orWhere('real_name', 'like', $like);
            });
        }

        if ($roleId !== null && $roleId > 0) {
            $tUr = 'user_roles';
            $tU = 'users';
            if (Schema::hasTable($tUr)) {
                $query->whereExists(function ($q) use ($roleId, $tUr, $tU) {
                    $q->selectRaw('1')
                        ->from($tUr)
                        ->whereColumn($tUr.'.user_id', $tU.'.id')
                        ->where($tUr.'.role_id', $roleId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($positionId !== null && $positionId > 0) {
            $tUp = 'user_positions';
            $tU = 'users';
            if (Schema::hasTable($tUp)) {
                $query->whereExists(function ($q) use ($positionId, $tUp, $tU) {
                    $q->selectRaw('1')
                        ->from($tUp)
                        ->whereColumn($tUp.'.user_id', $tU.'.id')
                        ->where($tUp.'.position_id', $positionId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        static::applyAdminListPresenceFilter($query, $presenceFilter);

        return $query;
    }

    /**
     * 按今日考勤记录筛选「当下状态」（与 {@see presenceTodayMetaFromRecords} 一致，用于列表分页总数正确）。
     */
    public static function applyAdminListPresenceFilter(Builder $query, ?string $code, ?string $workDate = null): void
    {
        $code = $code !== null ? trim($code) : '';
        if ($code === '') {
            return;
        }

        $allowed = array_keys(static::adminListPresenceFilterOptions());
        if (! in_array($code, $allowed, true)) {
            return;
        }

        $t = 'user_presence_records';
        if (! Schema::hasTable($t)) {
            if ($code === self::PRESENCE_FILTER_NOT_ARRIVED) {
                return;
            }
            $query->whereRaw('1 = 0');

            return;
        }

        $workDate = $workDate !== null && $workDate !== '' ? substr($workDate, 0, 10) : date('Y-m-d');
        $u = (new static)->getTable();

        if ($code === self::PRESENCE_FILTER_NOT_ARRIVED) {
            $query->whereNotExists(function ($sub) use ($t, $workDate, $u) {
                $sub->selectRaw('1')
                    ->from($t.' as upr')
                    ->whereColumn('upr.user_id', $u.'.id')
                    ->where('upr.work_date', $workDate)
                    ->where('upr.status', 1);
            });

            return;
        }

        if ($code === self::PRESENCE_FILTER_OUTING) {
            $query->whereExists(function ($sub) use ($t, $workDate, $u) {
                $sub->selectRaw('1')
                    ->from($t.' as o')
                    ->whereColumn('o.user_id', $u.'.id')
                    ->where('o.work_date', $workDate)
                    ->where('o.status', 1)
                    ->where('o.record_type', 2)
                    ->whereNull('o.end_at');
            });

            return;
        }

        static::applyAdminListPresenceFilterWithNoOpenOuting($query, $t, $workDate, $u);

        if ($code === self::PRESENCE_FILTER_OFF_WORK) {
            static::applyAdminListLastRecordTypes($query, [3], $t, $workDate, $u);

            return;
        }

        if ($code === self::PRESENCE_FILTER_PRESENT) {
            static::applyAdminListLastRecordTypes($query, [1, 2], $t, $workDate, $u);

            return;
        }

        if ($code === self::PRESENCE_FILTER_UNKNOWN) {
            static::applyAdminListLastRecordTypes($query, [1, 2, 3], $t, $workDate, $u, true);
        }
    }

    /**
     * @param  list<int>  $types
     * @param  bool  $negate  true：最后一条记录的 record_type 不在 $types 中
     */
    protected static function applyAdminListLastRecordTypes(
        Builder $query,
        array $types,
        string $presenceTable,
        string $workDate,
        string $usersTable,
        bool $negate = false,
    ): void {
        $query->whereExists(function ($sub) use ($types, $presenceTable, $workDate, $usersTable, $negate) {
            $sub->selectRaw('1')
                ->from($presenceTable.' as upr')
                ->whereColumn('upr.user_id', $usersTable.'.id')
                ->where('upr.work_date', $workDate)
                ->where('upr.status', 1);
            if ($negate) {
                $sub->whereNotIn('upr.record_type', $types);
            } else {
                $sub->whereIn('upr.record_type', $types);
            }
            $sub->whereNotExists(function ($sub2) use ($presenceTable, $workDate) {
                $sub2->selectRaw('1')
                    ->from($presenceTable.' as upr2')
                    ->whereColumn('upr2.user_id', 'upr.user_id')
                    ->whereColumn('upr2.work_date', 'upr.work_date')
                    ->where('upr2.status', 1)
                    ->where(function ($w) {
                        $w->whereColumn('upr2.start_at', '>', 'upr.start_at')
                            ->orWhere(function ($w2) {
                                $w2->whereColumn('upr2.start_at', 'upr.start_at')
                                    ->whereColumn('upr2.id', '>', 'upr.id');
                            });
                    });
            });
        });
    }

    protected static function applyAdminListPresenceFilterWithNoOpenOuting(Builder $query, string $t, string $workDate, string $u): void
    {
        $query->whereNotExists(function ($sub) use ($t, $workDate, $u) {
            $sub->selectRaw('1')
                ->from($t.' as o')
                ->whereColumn('o.user_id', $u.'.id')
                ->where('o.work_date', $workDate)
                ->where('o.status', 1)
                ->where('o.record_type', 2)
                ->whereNull('o.end_at');
        });
    }

    /**
     * 批量用户 id → 已绑定角色简述（列表 roles 列）。无绑定则无对应 user_id 键。
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array<int, array{id:int, name:string, is_system:bool, code:string}>>
     */
    public static function rolesMapForUserIds(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }
        $tUr = 'user_roles';
        $tR = 'roles';
        if (! Schema::hasTable($tUr) || ! Schema::hasTable($tR)) {
            return [];
        }

        $rows = DB::table($tUr)
            ->join($tR, $tR.'.id', '=', $tUr.'.role_id')
            ->whereIn($tUr.'.user_id', $userIds)
            ->select([
                $tUr.'.user_id as user_id',
                $tR.'.id as role_id',
                $tR.'.name as role_name',
                $tR.'.code as role_code',
                $tR.'.is_system as is_system',
            ])
            ->orderBy($tR.'.is_system', 'desc')
            ->orderBy($tR.'.id')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $uid = (int) ($r->user_id ?? 0);
            $rid = (int) ($r->role_id ?? 0);
            $name = (string) ($r->role_name ?? '');
            $isSystem = (int) ($r->is_system ?? 0) === 1;
            $code = (string) ($r->role_code ?? '');
            if ($uid <= 0 || $rid <= 0 || $name === '') {
                continue;
            }
            if (! isset($map[$uid])) {
                $map[$uid] = [];
            }
            $map[$uid][] = [
                'id' => $rid,
                'name' => $name,
                'is_system' => $isSystem,
                'code' => $code,
            ];
        }

        return $map;
    }

    /**
     * 新建用户默认角色 id 列表（默认不分配，需在后台另行分配）。
     *
     * @return list<int>
     */
    public static function defaultNewUserRoleIds(): array
    {
        return [];
    }

    /**
     * 全量覆盖 user_roles 中本用户的关联；校验规则与后台分配接口一致。
     *
     * @param  array<int, int|string>  $roleIds
     */
    public function syncRolesFromIds(array $roleIds): void
    {
        $tUr = 'user_roles';
        $tR = 'roles';
        if (! Schema::hasTable($tUr) || ! Schema::hasTable($tR)) {
            return;
        }

        if ($this->isSuperAdminAccount()) {
            return;
        }

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

        if ($roleIds !== []) {
            if (RoleModel::countWhereIdIn($roleIds) !== count($roleIds)) {
                throw ValidationException::withMessages([
                    'role_ids' => ['存在无效的角色'],
                ]);
            }
            if (RoleModel::existsWithCodeInIds(RoleModel::CODE_SUPER_ADMIN, $roleIds)) {
                throw ValidationException::withMessages([
                    'role_ids' => ['超级管理员角色不可分配。'],
                ]);
            }
        }

        DB::transaction(function () use ($roleIds, $tUr) {
            DB::table($tUr)->where('user_id', $this->id)->delete();
            foreach ($roleIds as $rid) {
                DB::table($tUr)->insert([
                    'user_id' => $this->id,
                    'role_id' => $rid,
                ]);
            }
        });
    }

    /** 删除用户前清理角色中间表（表不存在时跳过）。 */
    public static function detachAllRoleAssignments(int $userId): void
    {
        $tUr = 'user_roles';
        if (! Schema::hasTable($tUr)) {
            return;
        }

        DB::table($tUr)->where('user_id', $userId)->delete();
    }

    /** 按登录账号取一条（认证入口用）。 */
    public static function findByAccount(string $account): ?self
    {
        return static::query()->where('account', $account)->first();
    }

    /**
     * 报销审批链解析：当前用户关联的部门 ID、主部门、启用中的职务 code（positions.code）。
     *
     * @return array{dept_ids: list<int>, primary_dept_id: ?int, position_codes: list<string>}
     */
    public static function applicantOrgContext(self $user): array
    {
        $uid = (int) $user->id;
        if ($uid <= 0) {
            return ['dept_ids' => [], 'primary_dept_id' => null, 'position_codes' => []];
        }

        $tUd = 'user_departments';
        $tUp = 'user_positions';
        $tD = 'departments';
        $tP = 'positions';

        $deptIds = [];
        $primaryDeptId = null;

        if (Schema::hasTable($tUd)) {
            $rows = DB::table($tUd)->where('user_id', $uid)->get(['dept_id', 'is_primary']);
            foreach ($rows as $r) {
                $did = (int) ($r->dept_id ?? 0);
                if ($did > 0) {
                    $deptIds[] = $did;
                }
                if ((int) ($r->is_primary ?? 0) === 1 && $did > 0) {
                    $primaryDeptId = $did;
                }
            }
            $deptIds = array_values(array_unique($deptIds));
            if ($primaryDeptId === null && $deptIds !== []) {
                if (Schema::hasTable($tD)) {
                    $ordered = DB::table($tD)
                        ->whereIn('id', $deptIds)
                        ->orderBy('sort')
                        ->orderByDesc('id')
                        ->pluck('id')
                        ->map(static fn ($id) => (int) $id)
                        ->all();
                    $primaryDeptId = $ordered[0] ?? $deptIds[0];
                } else {
                    $primaryDeptId = $deptIds[0];
                }
            }
        }

        $positionCodes = [];
        if (Schema::hasTable($tUp) && Schema::hasTable($tP)) {
            $positionCodes = DB::table($tUp)
                ->join($tP, $tP.'.id', '=', $tUp.'.position_id')
                ->where($tUp.'.user_id', $uid)
                ->where($tP.'.status', 1)
                ->pluck($tP.'.code')
                ->map(static fn ($c) => trim((string) $c))
                ->filter(static fn ($c) => $c !== '')
                ->unique()
                ->values()
                ->all();
        }

        return [
            'dept_ids' => $deptIds,
            'primary_dept_id' => $primaryDeptId,
            'position_codes' => $positionCodes,
        ];
    }

    /**
     * 批量用户 id → 已关联部门、职务（列表展示用）。
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{departments: list<array{id:int, name:string}>, positions: list<array{id:int, name:string, dept_id:int, dept_name:string}>}>
     */
    public static function orgMapForUserIds(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $tUd = 'user_departments';
        $tUp = 'user_positions';
        $tD = 'departments';
        $tP = 'positions';

        $out = [];
        foreach ($userIds as $uid) {
            $out[$uid] = ['departments' => [], 'positions' => []];
        }

        if (Schema::hasTable($tUd) && Schema::hasTable($tD)) {
            $rows = DB::table($tUd)
                ->join($tD, $tD.'.id', '=', $tUd.'.dept_id')
                ->whereIn($tUd.'.user_id', $userIds)
                ->orderBy($tD.'.sort')
                ->orderByDesc($tD.'.id')
                ->select([$tUd.'.user_id as user_id', $tD.'.id as dept_id', $tD.'.name as dept_name'])
                ->get();
            foreach ($rows as $r) {
                $uid = (int) ($r->user_id ?? 0);
                if ($uid <= 0 || ! isset($out[$uid])) {
                    continue;
                }
                $out[$uid]['departments'][] = [
                    'id' => (int) ($r->dept_id ?? 0),
                    'name' => trim((string) ($r->dept_name ?? '')),
                ];
            }
        }

        if (Schema::hasTable($tUp) && Schema::hasTable($tP)) {
            $deptJoin = Schema::hasTable($tD);
            $q = DB::table($tUp)
                ->join($tP, $tP.'.id', '=', $tUp.'.position_id')
                ->whereIn($tUp.'.user_id', $userIds)
                ->orderBy($tP.'.dept_id')
                ->orderByDesc($tP.'.level')
                ->orderByDesc($tP.'.id')
                ->select([
                    $tUp.'.user_id as user_id',
                    $tP.'.id as position_id',
                    $tP.'.name as position_name',
                    $tP.'.dept_id as dept_id',
                ]);
            if ($deptJoin) {
                $q->addSelect($tD.'.name as dept_name')
                    ->leftJoin($tD, $tD.'.id', '=', $tP.'.dept_id');
            }
            foreach ($q->get() as $r) {
                $uid = (int) ($r->user_id ?? 0);
                if ($uid <= 0 || ! isset($out[$uid])) {
                    continue;
                }
                $out[$uid]['positions'][] = [
                    'id' => (int) ($r->position_id ?? 0),
                    'name' => trim((string) ($r->position_name ?? '')),
                    'dept_id' => (int) ($r->dept_id ?? 0),
                    'dept_name' => $deptJoin ? trim((string) ($r->dept_name ?? '')) : '',
                ];
            }
        }

        return $out;
    }

    /**
     * 批量用户 id → 门店任职（列表展示用）。
     *
     * @param  array<int, int>  $userIds
     * @return array<int, list<array{id:int, store_id:int, store_name:string, store_code:string, position_id:int, position_name:string, dept_name:string, is_main:int, start_date:string, end_date:string}>>
     */
    public static function userStoresMapForUserIds(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        $base = [];
        foreach ($userIds as $uid) {
            $base[$uid] = [];
        }
        if ($userIds === []) {
            return [];
        }

        $tUs = 'user_stores';
        $tS = 'stores';
        $tP = 'positions';
        $tD = 'departments';
        if (! Schema::hasTable($tUs) || ! Schema::hasTable($tS) || ! Schema::hasTable($tP)) {
            return $base;
        }

        $deptJoin = Schema::hasTable($tD);
        $q = DB::table($tUs.' as us')
            ->join($tS.' as s', 's.id', '=', 'us.store_id')
            ->join($tP.' as p', 'p.id', '=', 'us.position_id')
            ->whereIn('us.user_id', $userIds)
            ->orderByDesc('us.is_main')
            ->orderBy('us.id')
            ->select([
                'us.user_id as user_id',
                'us.id as assignment_id',
                'us.store_id as store_id',
                'us.position_id as position_id',
                'us.is_main as is_main',
                'us.start_date as start_date',
                'us.end_date as end_date',
                's.name as store_name',
                's.code as store_code',
                'p.name as position_name',
            ]);
        if ($deptJoin) {
            $q->addSelect('d.name as dept_name')
                ->leftJoin($tD.' as d', 'd.id', '=', 'p.dept_id');
        }
        foreach ($q->get() as $r) {
            $uid = (int) ($r->user_id ?? 0);
            if ($uid <= 0 || ! isset($base[$uid])) {
                continue;
            }
            $sd = $r->start_date ?? '';
            $ed = $r->end_date ?? '';
            $base[$uid][] = [
                'id' => (int) ($r->assignment_id ?? 0),
                'store_id' => (int) ($r->store_id ?? 0),
                'store_name' => trim((string) ($r->store_name ?? '')),
                'store_code' => trim((string) ($r->store_code ?? '')),
                'position_id' => (int) ($r->position_id ?? 0),
                'position_name' => trim((string) ($r->position_name ?? '')),
                'dept_name' => $deptJoin ? trim((string) ($r->dept_name ?? '')) : '',
                'is_main' => (int) ($r->is_main ?? 0),
                'start_date' => is_string($sd) ? substr($sd, 0, 10) : (string) $sd,
                'end_date' => is_string($ed) ? substr($ed, 0, 10) : (string) $ed,
            ];
        }

        return $base;
    }

    /**
     * 小程序可打卡地点：当日有效的门店任职 + 门店坐标/半径（与 POST /api/presence/arrival 中 store_id 对应）。
     *
     * @return list<array{
     *     assignment_id:int,
     *     store_id:int,
     *     store_name:string,
     *     store_code:string,
     *     store_type:int,
     *     address:string,
     *     longitude: ?string,
     *     latitude: ?string,
     *     radius:int,
     *     has_location:bool,
     *     position_id:int,
     *     position_name:string,
     *     dept_name:string,
     *     is_main:int
     * }>
     */
    public static function apiClockInPlacesForUserId(int $userId, ?string $workDate = null): array
    {
        if ($userId < 1) {
            return [];
        }

        $workDate = $workDate !== null && $workDate !== '' ? substr($workDate, 0, 10) : date('Y-m-d');

        $tUs = 'user_stores';
        $tS = 'stores';
        $tP = 'positions';
        $tD = 'departments';
        if (! Schema::hasTable($tUs) || ! Schema::hasTable($tS) || ! Schema::hasTable($tP)) {
            return [];
        }

        $deptJoin = Schema::hasTable($tD);
        $q = DB::table($tUs.' as us')
            ->join($tS.' as s', 's.id', '=', 'us.store_id')
            ->join($tP.' as p', 'p.id', '=', 'us.position_id')
            ->where('us.user_id', $userId)
            ->whereDate('us.start_date', '<=', $workDate)
            ->whereDate('us.end_date', '>=', $workDate)
            ->where('s.status', 1)
            ->where('p.status', 1)
            ->orderByDesc('us.is_main')
            ->orderBy('us.id')
            ->select([
                'us.id as assignment_id',
                'us.store_id as store_id',
                'us.position_id as position_id',
                'us.is_main as is_main',
                's.name as store_name',
                's.code as store_code',
                's.store_type as store_type',
                's.address as store_address',
                's.longitude as store_longitude',
                's.latitude as store_latitude',
                's.radius as store_radius',
                'p.name as position_name',
            ]);
        if ($deptJoin) {
            $q->addSelect('d.name as dept_name')
                ->leftJoin($tD.' as d', 'd.id', '=', 'p.dept_id');
        }

        $out = [];
        foreach ($q->get() as $r) {
            $lon = $r->store_longitude ?? null;
            $lat = $r->store_latitude ?? null;
            $lonS = $lon !== null && $lon !== '' ? trim((string) $lon) : '';
            $latS = $lat !== null && $lat !== '' ? trim((string) $lat) : '';
            $hasLoc = $lonS !== '' && $latS !== '';
            $out[] = [
                'assignment_id' => (int) ($r->assignment_id ?? 0),
                'store_id' => (int) ($r->store_id ?? 0),
                'store_name' => trim((string) ($r->store_name ?? '')),
                'store_code' => trim((string) ($r->store_code ?? '')),
                'store_type' => (int) ($r->store_type ?? 1),
                'address' => trim((string) ($r->store_address ?? '')),
                'longitude' => $hasLoc ? $lonS : null,
                'latitude' => $hasLoc ? $latS : null,
                'radius' => max(1, (int) ($r->store_radius ?? 100)),
                'has_location' => $hasLoc,
                'position_id' => (int) ($r->position_id ?? 0),
                'position_name' => trim((string) ($r->position_name ?? '')),
                'dept_name' => ($deptJoin && isset($r->dept_name)) ? trim((string) $r->dept_name) : '',
                'is_main' => (int) ($r->is_main ?? 0),
            ];
        }

        return $out;
    }

    /**
     * 全量覆盖用户的部门、职务关联（与后台分配接口配合校验后调用）。
     *
     * @param  array<int, int|string>  $deptIds
     * @param  array<int, int|string>  $positionIds
     */
    public function syncOrgFromIds(array $deptIds, array $positionIds): void
    {
        if ($this->isSuperAdminAccount()) {
            return;
        }

        $deptIds = array_values(array_unique(array_filter(array_map('intval', $deptIds), static fn (int $id) => $id > 0)));
        $positionIds = array_values(array_unique(array_filter(array_map('intval', $positionIds), static fn (int $id) => $id > 0)));

        $tUd = 'user_departments';
        $tUp = 'user_positions';
        $now = time();

        DB::transaction(function () use ($deptIds, $positionIds, $tUd, $tUp, $now) {
            if (Schema::hasTable($tUd)) {
                DB::table($tUd)->where('user_id', $this->id)->delete();
                foreach ($deptIds as $did) {
                    DB::table($tUd)->insert([
                        'user_id' => $this->id,
                        'dept_id' => $did,
                        'is_primary' => 0,
                        'created_at' => $now,
                    ]);
                }
            }

            if (Schema::hasTable($tUp)) {
                DB::table($tUp)->where('user_id', $this->id)->delete();
                foreach ($positionIds as $pid) {
                    DB::table($tUp)->insert([
                        'user_id' => $this->id,
                        'position_id' => $pid,
                        'is_primary' => 0,
                        'created_at' => $now,
                    ]);
                }
            }
        });
    }

    /**
     * SPA 用户列表：分页数据 + 每行 roles、is_super_admin。
     *
     * @return array{data: list<array<string, mixed>>, paginator: \Illuminate\Pagination\LengthAwarePaginator}
     */
    public static function paginatedAdminApiList(
        string $keyword,
        ?int $roleId,
        int $perPage,
        ?string $presenceFilter = null,
        ?int $positionId = null,
    ): array {
        $paginator = static::adminListQuery($keyword, $roleId, $presenceFilter, $positionId)
            ->orderBy('id')
            ->paginate($perPage);

        $items = $paginator->items();
        $userIds = array_map(static fn ($u) => (int) ($u->id ?? 0), $items);
        $rolesMap = static::rolesMapForUserIds($userIds);
        $orgMap = static::orgMapForUserIds($userIds);
        $storesMap = Schema::hasTable('user_stores') ? static::userStoresMapForUserIds($userIds) : [];
        $presenceMeta = static::presenceTodayMetaMapForUserIds($userIds);

        $data = array_map(function ($u) use ($rolesMap, $orgMap, $storesMap, $presenceMeta) {
            $uid = (int) ($u->id ?? 0);
            $row = $u->toArray();
            $row['roles'] = $rolesMap[$uid] ?? [];
            $row['departments'] = $orgMap[$uid]['departments'] ?? [];
            $row['positions'] = $orgMap[$uid]['positions'] ?? [];
            $row['stores'] = $storesMap[$uid] ?? [];
            $row['is_super_admin'] = $u->isSuperAdminAccount();
            $meta = $presenceMeta[$uid] ?? ['label' => '—', 'out_reason' => null];
            $presenceLabel = $meta['label'];
            $row['presence_today'] = $presenceLabel;
            $row['presence_today_class'] = static::presenceTodayPillClass($presenceLabel);
            $row['presence_today_title'] = static::presenceTodayHoverTitle($presenceLabel, $meta['out_reason'] ?? null);

            return $row;
        }, $items);

        return ['data' => $data, 'paginator' => $paginator];
    }

    /**
     * 今日当下状态文案（基于 user_presence_records，逻辑表名，带连接前缀）。
     *
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public static function presenceTodayStatusMapForUserIds(array $userIds): array
    {
        $meta = static::presenceTodayMetaMapForUserIds($userIds);
        $out = [];
        foreach ($meta as $uid => $row) {
            $out[$uid] = $row['label'];
        }

        return $out;
    }

    /**
     * 今日当下状态：展示文案 + 当前外出原因（未外出时为 null）。
     *
     * @param  array<int, int>  $userIds
     * @return array<int, array{label: string, out_reason: string|null}>
     */
    public static function presenceTodayMetaMapForUserIds(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $empty = ['label' => '—', 'out_reason' => null];
        $t = 'user_presence_records';
        if (! Schema::hasTable($t)) {
            return array_fill_keys($userIds, $empty);
        }

        $today = date('Y-m-d');
        $rows = DB::table($t)
            ->whereIn('user_id', $userIds)
            ->where('work_date', $today)
            ->where('status', 1)
            ->orderBy('user_id')
            ->orderBy('start_at')
            ->orderBy('id')
            ->get(['user_id', 'record_type', 'start_at', 'end_at', 'reason']);

        $byUser = [];
        foreach ($rows as $r) {
            $uid = (int) ($r->user_id ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = [];
            }
            $byUser[$uid][] = $r;
        }

        $out = [];
        foreach ($userIds as $uid) {
            $out[$uid] = static::presenceTodayMetaFromRecords($byUser[$uid] ?? []);
        }

        return $out;
    }

    /**
     * @param  list<object>  $recs
     * @return array{label: string, out_reason: string|null}
     */
    protected static function presenceTodayMetaFromRecords(array $recs): array
    {
        if ($recs === []) {
            return ['label' => '未到岗', 'out_reason' => null];
        }

        foreach ($recs as $r) {
            $type = (int) ($r->record_type ?? 0);
            $endAt = $r->end_at ?? null;
            if ($type === 2 && ($endAt === null || $endAt === '')) {
                $reason = isset($r->reason) ? trim((string) $r->reason) : '';

                return ['label' => '外出', 'out_reason' => $reason !== '' ? $reason : null];
            }
        }

        $last = $recs[count($recs) - 1];
        $t = (int) ($last->record_type ?? 0);
        if ($t === 3) {
            return ['label' => '下班', 'out_reason' => null];
        }
        if ($t === 2) {
            return ['label' => '到岗', 'out_reason' => null];
        }
        if ($t === 1) {
            return ['label' => '到岗', 'out_reason' => null];
        }

        return ['label' => '—', 'out_reason' => null];
    }

    public static function presenceTodayHoverTitle(string $label, ?string $outReason): string
    {
        if ($label !== '外出') {
            return '';
        }
        $r = $outReason !== null ? trim($outReason) : '';

        return $r !== '' ? $r : '暂无外出说明';
    }

    /**
     * 与 resources/css/admin-spa.css、public/css/admin-layout.css 中 .admin-presence-pill 修饰符对应。
     */
    public static function presenceTodayPillClass(string $label): string
    {
        return match ($label) {
            '未到岗' => 'admin-presence-pill admin-presence-pill--absent',
            '到岗' => 'admin-presence-pill admin-presence-pill--present',
            '外出' => 'admin-presence-pill admin-presence-pill--out',
            '下班' => 'admin-presence-pill admin-presence-pill--off',
            default => 'admin-presence-pill admin-presence-pill--muted',
        };
    }
}
