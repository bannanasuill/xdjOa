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

        $deptMenu = (int) (DB::table('permissions')->where('code', 'perm.admin.departments')->value('id') ?: 0);
        if ($deptMenu > 0) {
            DB::table('permissions')->updateOrInsert(
                ['code' => 'perm.admin.api.departments.destroy'],
                [
                    'name' => '接口：部门删除',
                    'type' => 'api',
                    'parent_id' => $deptMenu,
                    'path' => 'DELETE /admin/api/departments/{department}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $posMenu = (int) (DB::table('permissions')->where('code', 'perm.admin.positions')->value('id') ?: 0);
        if ($posMenu > 0) {
            DB::table('permissions')->updateOrInsert(
                ['code' => 'perm.admin.api.positions.destroy'],
                [
                    'name' => '接口：职务删除',
                    'type' => 'api',
                    'parent_id' => $posMenu,
                    'path' => 'DELETE /admin/api/positions/{position}',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $permIds = DB::table('permissions')
            ->whereIn('code', [
                'perm.admin.api.departments.destroy',
                'perm.admin.api.positions.destroy',
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $codes = ['perm.admin.api.departments.destroy', 'perm.admin.api.positions.destroy'];
        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        }
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
