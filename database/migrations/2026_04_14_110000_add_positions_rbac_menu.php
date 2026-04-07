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
            ['code' => 'perm.admin.positions'],
            [
                'name' => '职务管理',
                'type' => 'menu',
                'parent_id' => $adminRoot,
                'path' => '/admin/positions',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $menuPid = (int) DB::table('permissions')->where('code', 'perm.admin.positions')->value('id');

        $apis = [
            ['perm.admin.api.positions.index', '接口：职务列表', 'GET /admin/api/positions'],
            ['perm.admin.api.positions.dept_options', '接口：职务所属部门候选', 'GET /admin/api/positions/dept-options'],
            ['perm.admin.api.positions.store', '接口：职务新增', 'POST /admin/api/positions'],
            ['perm.admin.api.positions.update', '接口：职务更新', 'PUT /admin/api/positions/{position}'],
            ['perm.admin.api.positions.status', '接口：职务状态', 'PATCH /admin/api/positions/{position}/status'],
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
                'perm.admin.positions',
                'perm.admin.api.positions.index',
                'perm.admin.api.positions.dept_options',
                'perm.admin.api.positions.store',
                'perm.admin.api.positions.update',
                'perm.admin.api.positions.status',
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
                ['id' => 12],
                [
                    'id' => 12,
                    'name' => '职务列表',
                    'permission_code' => 'menu.admin_positions',
                    'path' => '/admin/positions',
                    'component' => '',
                    'parent_id' => 1,
                    'icon' => '',
                    'sort' => 3,
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
            'perm.admin.api.positions.status',
            'perm.admin.api.positions.update',
            'perm.admin.api.positions.store',
            'perm.admin.api.positions.dept_options',
            'perm.admin.api.positions.index',
            'perm.admin.positions',
        ];

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('code', $codes)->delete();
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 12)->delete();
        }
    }
};
