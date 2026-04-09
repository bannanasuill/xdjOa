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
            ['code' => 'perm.admin.attendance_rules'],
            [
                'name' => '考勤规则',
                'type' => 'menu',
                'parent_id' => $adminRoot,
                'path' => '/admin/attendance-rules',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $menuPid = (int) DB::table('permissions')->where('code', 'perm.admin.attendance_rules')->value('id');

        $apis = [
            ['perm.admin.api.attendance_rules.form_options', '接口：考勤规则表单选项', 'GET /admin/api/attendance-rules/form-options'],
            ['perm.admin.api.attendance_rules.index', '接口：考勤规则列表', 'GET /admin/api/attendance-rules'],
            ['perm.admin.api.attendance_rules.store', '接口：考勤规则新增', 'POST /admin/api/attendance-rules'],
            ['perm.admin.api.attendance_rules.update', '接口：考勤规则更新', 'PUT /admin/api/attendance-rules/{attendanceRule}'],
            ['perm.admin.api.attendance_rules.status', '接口：考勤规则状态', 'PATCH /admin/api/attendance-rules/{attendanceRule}/status'],
            ['perm.admin.api.attendance_rules.destroy', '接口：考勤规则删除', 'DELETE /admin/api/attendance-rules/{attendanceRule}'],
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
            'perm.admin.attendance_rules',
            'perm.admin.api.attendance_rules.form_options',
            'perm.admin.api.attendance_rules.index',
            'perm.admin.api.attendance_rules.store',
            'perm.admin.api.attendance_rules.update',
            'perm.admin.api.attendance_rules.status',
            'perm.admin.api.attendance_rules.destroy',
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
                ['id' => 14],
                [
                    'id' => 14,
                    'name' => '考勤规则',
                    'permission_code' => 'menu.admin_attendance_rules',
                    'path' => '/admin/attendance-rules',
                    'component' => '',
                    'parent_id' => 1,
                    'icon' => '',
                    'sort' => 5,
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
            'perm.admin.api.attendance_rules.destroy',
            'perm.admin.api.attendance_rules.status',
            'perm.admin.api.attendance_rules.update',
            'perm.admin.api.attendance_rules.store',
            'perm.admin.api.attendance_rules.index',
            'perm.admin.api.attendance_rules.form_options',
            'perm.admin.attendance_rules',
        ];

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('code', $codes)->delete();
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 14)->delete();
        }
    }
};
