<?php

namespace Database\Seeders;

use App\Models\RoleModel;
use App\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $now = time();

        DB::table('roles')->updateOrInsert(
            ['id' => 1],
            [
                'id' => 1,
                'name' => '超级管理员',
                'code' => RoleModel::CODE_SUPER_ADMIN,
                'data_scope' => 'all',
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

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

        $adminUserRow = [
            'password' => Hash::make('123456'),
            'real_name' => '超级管理员',
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        UserModel::query()->updateOrInsert(
            ['account' => 'admin'],
            $adminUserRow
        );

        $adminId = UserModel::query()->where('account', 'admin')->value('id');
        if ($adminId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $adminId, 'role_id' => 1],
                ['user_id' => $adminId, 'role_id' => 1]
            );
        }

        if (Schema::hasTable('permissions')) {
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
        }

        $loginPermId = 3;
        if ($loginPermId && Schema::hasTable('role_permissions')) {
            foreach ([2, 3] as $roleId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $loginPermId],
                    ['role_id' => $roleId, 'permission_id' => $loginPermId]
                );
            }
        }

        $this->call(OrgStructureSeeder::class);
    }
}
