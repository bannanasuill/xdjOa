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

        $now = time();

        if (DB::table('permissions')->where('code', 'perm.admin.positions')->exists()) {
            $renames = [
                ['perm.admin.positions', 'perm.admin.departments', '部门管理', '/admin/departments'],
                ['perm.admin.api.positions.index', 'perm.admin.api.departments.index', '接口：部门列表', 'GET /admin/api/departments'],
                ['perm.admin.api.positions.store', 'perm.admin.api.departments.store', '接口：部门新增', 'POST /admin/api/departments'],
                ['perm.admin.api.positions.update', 'perm.admin.api.departments.update', '接口：部门更新', 'PUT /admin/api/departments/{department}'],
                ['perm.admin.api.positions.status', 'perm.admin.api.departments.status', '接口：部门状态', 'PATCH /admin/api/departments/{department}/status'],
            ];

            foreach ($renames as [$from, $to, $name, $path]) {
                DB::table('permissions')->where('code', $from)->update([
                    'code' => $to,
                    'name' => $name,
                    'path' => $path,
                    'updated_at' => $now,
                ]);
            }
        }

        $menuPid = DB::table('permissions')->where('code', 'perm.admin.departments')->value('id');
        if ($menuPid && ! DB::table('permissions')->where('code', 'perm.admin.api.departments.leader_options')->exists()) {
            DB::table('permissions')->insert([
                'name' => '接口：部门负责人候选',
                'code' => 'perm.admin.api.departments.leader_options',
                'type' => 'api',
                'parent_id' => (int) $menuPid,
                'path' => 'GET /admin/api/departments/leader-options',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $newPermId = (int) DB::table('permissions')->where('code', 'perm.admin.api.departments.leader_options')->value('id');
            if ($newPermId > 0 && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => 2, 'permission_id' => $newPermId],
                    ['role_id' => 2, 'permission_id' => $newPermId]
                );
            }
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 11)->update([
                'name' => '部门列表',
                'permission_code' => 'menu.admin_departments',
                'path' => '/admin/departments',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = time();

        $leaderId = DB::table('permissions')->where('code', 'perm.admin.api.departments.leader_options')->value('id');
        if ($leaderId && Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('permission_id', (int) $leaderId)->delete();
        }
        DB::table('permissions')->where('code', 'perm.admin.api.departments.leader_options')->delete();

        if (DB::table('permissions')->where('code', 'perm.admin.departments')->exists()) {
            $renames = [
                ['perm.admin.departments', 'perm.admin.positions', '职务管理', '/admin/positions'],
                ['perm.admin.api.departments.index', 'perm.admin.api.positions.index', '接口：职务列表', 'GET /admin/api/positions'],
                ['perm.admin.api.departments.store', 'perm.admin.api.positions.store', '接口：职务新增', 'POST /admin/api/positions'],
                ['perm.admin.api.departments.update', 'perm.admin.api.positions.update', '接口：职务更新', 'PUT /admin/api/positions/{position}'],
                ['perm.admin.api.departments.status', 'perm.admin.api.positions.status', '接口：职务状态', 'PATCH /admin/api/positions/{position}/status'],
            ];

            foreach ($renames as [$from, $to, $name, $path]) {
                DB::table('permissions')->where('code', $from)->update([
                    'code' => $to,
                    'name' => $name,
                    'path' => $path,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 11)->update([
                'name' => '职务列表',
                'permission_code' => 'menu.admin_positions',
                'path' => '/admin/positions',
                'updated_at' => $now,
            ]);
        }
    }
};
