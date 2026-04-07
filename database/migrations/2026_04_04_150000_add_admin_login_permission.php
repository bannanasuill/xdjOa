<?php

use App\Models\UserModel;
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

        DB::table('permissions')->updateOrInsert(
            ['id' => 3],
            [
                'id' => 3,
                'name' => '后台登录',
                'code' => UserModel::ADMIN_PANEL_LOGIN_PERMISSION,
                'type' => 'api',
                'parent_id' => 1,
                'path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permId = 3;

        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        foreach ([2, 3] as $roleId) {
            if (! DB::table('roles')->where('id', $roleId)->exists()) {
                continue;
            }
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permId],
                ['role_id' => $roleId, 'permission_id' => $permId]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('permission_id', 3)->delete();
        }

        DB::table('permissions')->where('id', 3)->where('code', UserModel::ADMIN_PANEL_LOGIN_PERMISSION)->delete();
    }
};
