<?php

use App\Models\RoleModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = time();

        DB::table('roles')->updateOrInsert(
            ['id' => 2],
            [
                'id' => 2,
                'name' => '用户',
                'code' => RoleModel::CODE_ADMIN,
                'data_scope' => 'all',
                'is_system' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('roles')->updateOrInsert(
            ['id' => 3],
            [
                'id' => 3,
                'name' => '员工',
                'code' => RoleModel::CODE_EMPLOYEE,
                'data_scope' => 'self',
                'is_system' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('role_id', [2, 3])->delete();
        }
        if (Schema::hasTable('user_roles')) {
            DB::table('user_roles')->whereIn('role_id', [2, 3])->delete();
        }

        DB::table('roles')->whereIn('id', [2, 3])->delete();
    }
};
