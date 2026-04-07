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

        $parentId = DB::table('permissions')->where('code', 'perm.admin')->value('id');
        if (! $parentId) {
            return;
        }

        $now = time();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.settings'],
            [
                'name' => '系统配置',
                'type' => 'menu',
                'parent_id' => (int) $parentId,
                'path' => '/admin/settings',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $settingsMenuId = DB::table('permissions')->where('code', 'perm.admin.settings')->value('id');

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.api.settings.index'],
            [
                'name' => '接口：系统配置读取',
                'type' => 'api',
                'parent_id' => (int) $settingsMenuId,
                'path' => 'GET /admin/api/system-config',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.api.settings.update'],
            [
                'name' => '接口：系统配置保存',
                'type' => 'api',
                'parent_id' => (int) $settingsMenuId,
                'path' => 'PUT /admin/api/system-config',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $pIndex = DB::table('permissions')->where('code', 'perm.admin.api.settings.index')->value('id');
        $pUpdate = DB::table('permissions')->where('code', 'perm.admin.api.settings.update')->value('id');

        if (Schema::hasTable('role_permissions') && $settingsMenuId && $pIndex && $pUpdate) {
            foreach ([$settingsMenuId, $pIndex, $pUpdate] as $pid) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => 2, 'permission_id' => (int) $pid],
                    ['role_id' => 2, 'permission_id' => (int) $pid]
                );
            }
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->updateOrInsert(
                ['id' => 7],
                [
                    'id' => 7,
                    'name' => '系统配置',
                    'permission_code' => 'menu.admin_settings',
                    'path' => '/admin/settings',
                    'component' => '',
                    'parent_id' => null,
                    'icon' => 'el-icon-setting',
                    'sort' => 99,
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
            'perm.admin.api.settings.update',
            'perm.admin.api.settings.index',
            'perm.admin.settings',
        ];

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('code', $codes)->delete();
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 7)->delete();
        }
    }
};
