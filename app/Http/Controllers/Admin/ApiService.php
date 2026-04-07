<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuModel;
use App\Models\UserLogModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'roles' => $user->getAdminRolesForDisplay(),
            'permissions' => $user->getAdminPermissionCodes(),
        ];

        return response()->json(['data' => $data]);
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

        $all = MenuModel::query()
            ->where('visible', 1)
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
            'children' => $children,
        ];
    }
}

