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
            ['code' => 'perm.admin.stores'],
            [
                'name' => '店铺管理',
                'type' => 'menu',
                'parent_id' => $adminRoot,
                'path' => '/admin/stores',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $menuPid = (int) DB::table('permissions')->where('code', 'perm.admin.stores')->value('id');

        $apis = [
            ['perm.admin.api.stores.dept_options', '接口：店铺可选部门', 'GET /admin/api/stores/dept-options'],
            ['perm.admin.api.stores.index', '接口：店铺列表', 'GET /admin/api/stores'],
            ['perm.admin.api.stores.store', '接口：店铺新增', 'POST /admin/api/stores'],
            ['perm.admin.api.stores.update', '接口：店铺更新', 'PUT /admin/api/stores/{store}'],
            ['perm.admin.api.stores.status', '接口：店铺状态', 'PATCH /admin/api/stores/{store}/status'],
            ['perm.admin.api.stores.destroy', '接口：店铺删除', 'DELETE /admin/api/stores/{store}'],
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

        $permCodes = [
            'perm.admin.stores',
            'perm.admin.api.stores.dept_options',
            'perm.admin.api.stores.index',
            'perm.admin.api.stores.store',
            'perm.admin.api.stores.update',
            'perm.admin.api.stores.status',
            'perm.admin.api.stores.destroy',
        ];

        $permIds = DB::table('permissions')->whereIn('code', $permCodes)->pluck('id');
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
                ['id' => 13],
                [
                    'id' => 13,
                    'name' => '店铺管理',
                    'permission_code' => 'menu.admin_stores',
                    'path' => '/admin/stores',
                    'component' => '',
                    'parent_id' => 1,
                    'icon' => '',
                    'sort' => 4,
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
            'perm.admin.api.stores.destroy',
            'perm.admin.api.stores.status',
            'perm.admin.api.stores.update',
            'perm.admin.api.stores.store',
            'perm.admin.api.stores.index',
            'perm.admin.api.stores.dept_options',
            'perm.admin.stores',
        ];

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('code', $codes)->delete();
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 13)->delete();
        }
    }
};
