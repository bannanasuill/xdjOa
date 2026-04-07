<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $adminRoot = DB::table('permissions')->where('code', 'perm.admin')->value('id');
        if (! $adminRoot) {
            return;
        }

        $now = time();
        $adminRoot = (int) $adminRoot;

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.departments'],
            [
                'name' => '部门管理',
                'type' => 'menu',
                'parent_id' => $adminRoot,
                'path' => '/admin/departments',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $menuPid = (int) DB::table('permissions')->where('code', 'perm.admin.departments')->value('id');

        $apis = [
            ['perm.admin.api.departments.index', '接口：部门列表', 'GET /admin/api/departments'],
            ['perm.admin.api.departments.leader_options', '接口：部门负责人候选', 'GET /admin/api/departments/leader-options'],
            ['perm.admin.api.departments.store', '接口：部门新增', 'POST /admin/api/departments'],
            ['perm.admin.api.departments.update', '接口：部门更新', 'PUT /admin/api/departments/{department}'],
            ['perm.admin.api.departments.status', '接口：部门状态', 'PATCH /admin/api/departments/{department}/status'],
        ];

        foreach ($apis as [$code, $name, $path]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'type' => 'api',
                    'parent_id' => $menuPid,
                    'path' => $path,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $permIds = DB::table('permissions')
            ->whereIn('code', [
                'perm.admin.departments',
                'perm.admin.api.departments.index',
                'perm.admin.api.departments.leader_options',
                'perm.admin.api.departments.store',
                'perm.admin.api.departments.update',
                'perm.admin.api.departments.status',
            ])
            ->pluck('id');

        if (Schema::hasTable('role_permissions') && $permIds->isNotEmpty()) {
            foreach ($permIds as $pid) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => 2, 'permission_id' => (int) $pid],
                    ['role_id' => 2, 'permission_id' => (int) $pid]
                );
            }
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->updateOrInsert(
                ['id' => 11],
                [
                    'id' => 11,
                    'name' => '部门与职务',
                    'permission_code' => 'menu.admin_departments',
                    'path' => '/admin/departments',
                    'component' => '',
                    'parent_id' => 1,
                    'icon' => '',
                    'sort' => 2,
                    'visible' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $codes = [
            'perm.admin.api.departments.status',
            'perm.admin.api.departments.update',
            'perm.admin.api.departments.store',
            'perm.admin.api.departments.leader_options',
            'perm.admin.api.departments.index',
            'perm.admin.departments',
        ];

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('code', $codes)->delete();
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 11)->delete();
        }
    }
};
