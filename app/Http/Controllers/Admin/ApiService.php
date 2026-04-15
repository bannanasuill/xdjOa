<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuModel;
use App\Models\UserLogModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class ApiService extends Controller
{
    /**
     * 历史菜单 permission_code（menu.*）与权限表 perm.* 对齐。
     *
     * @var array<string, string>
     */
    private const MENU_PERMISSION_CODE_MAP = [
        'menu.admin' => 'perm.admin.users',
        'menu.admin_users_list' => 'perm.admin.users',
        'menu.admin_user_logs' => 'perm.admin.logs',
        'menu.admin_menus' => 'perm.admin.menus',
        'menu.admin_permissions' => 'perm.admin.permissions',
        'menu.admin_roles' => 'perm.admin.roles',
        'menu.admin_settings' => 'perm.admin.settings',
        'menu.admin_expense' => 'perm.admin.expense',
        'menu.admin_expense_templates' => 'perm.admin.expense.templates',
        'menu.admin_expense_apply' => 'perm.admin.expense.apply',
        'menu.admin_departments' => 'perm.admin.departments',
        'menu.admin_stores' => 'perm.admin.stores',
        'menu.admin_attendance_rules' => 'perm.admin.attendance_rules',
    ];

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['data' => null]);
        }

        $data = [
            'id' => $user->id,
            'account' => $user->account,
            'real_name' => $user->real_name,
            'status' => $user->status,
            'is_super_admin' => $user instanceof UserModel ? $user->isSuperAdminAccount() : false,
            'roles' => $user->getAdminRolesForDisplay(),
            'permissions' => $user->getAdminPermissionCodes(),
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * 首页：按用工状态汇总人员数量（须具备用户列表接口权限）。
     */
    public function userStatusSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => '未登录'], 401);
        }
        if (! $user->canAdminPermission('perm.admin.api.users.index')) {
            return response()->json(['message' => '无权查看'], 403);
        }
        if (! Schema::hasTable('users')) {
            return response()->json(['data' => ['total' => 0, 'by_status' => []]]);
        }

        $rows = DB::table('users')
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->get();

        $countByStatus = [];
        foreach ($rows as $row) {
            $countByStatus[(int) ($row->status ?? 0)] = (int) ($row->c ?? 0);
        }

        $options = UserModel::employmentStatusOptions();
        $byStatus = [];
        foreach ($options as $code => $label) {
            $byStatus[] = [
                'status' => (int) $code,
                'label' => $label,
                'count' => $countByStatus[(int) $code] ?? 0,
            ];
        }

        $unknown = 0;
        foreach ($countByStatus as $code => $cnt) {
            if (! array_key_exists($code, $options)) {
                $unknown += $cnt;
            }
        }
        if ($unknown > 0) {
            $byStatus[] = [
                'status' => -1,
                'label' => '其他',
                'count' => $unknown,
            ];
        }

        return response()->json([
            'data' => [
                'total' => (int) DB::table('users')->count(),
                'by_status' => $byStatus,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            UserLogModel::insertAuthAudit($request, $user, 'logout', 1, '退出成功。');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function menus(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['data' => []]);
        }

        // 含 visible=0（侧栏「未开启」）的菜单：仍按 permission_code 鉴权，无权限则不出现
        $all = MenuModel::query()
            ->orderByDesc('visible')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $roots = $all->filter(function (MenuModel $m) {
            return $m->parent_id === null || (int) $m->parent_id === 0;
        });

        $data = $roots
            ->map(fn (MenuModel $m) => $this->menuSidebarNode($m, $all, $user))
            ->filter()
            ->values();

        return response()->json(['data' => $data]);
    }

    private function resolveMenuGatePermissionCode(?string $menuPermissionCode): ?string
    {
        if ($menuPermissionCode === null || $menuPermissionCode === '') {
            return null;
        }

        return self::MENU_PERMISSION_CODE_MAP[$menuPermissionCode] ?? $menuPermissionCode;
    }

    /**
     * @param  Collection<int, MenuModel>  $all
     * @return array<string, mixed>|null
     */
    private function menuSidebarNode(MenuModel $item, Collection $all, UserModel $user): ?array
    {
        $children = $all
            ->filter(fn (MenuModel $m) => (int) ($m->parent_id ?? 0) === (int) $item->id)
            ->values()
            ->map(fn (MenuModel $m) => $this->menuSidebarNode($m, $all, $user))
            ->filter()
            ->values()
            ->all();

        $gate = $this->resolveMenuGatePermissionCode($item->permission_code);
        $allowed = $gate === null || $user->canAdminPermission($gate);

        if (! $allowed && $children === []) {
            return null;
        }

        return [
            'id' => (int) $item->id,
            'name' => $item->name,
            'path' => $item->path,
            'icon' => $item->icon,
            'visible' => (int) $item->visible,
            'children' => $children,
        ];
    }
}

