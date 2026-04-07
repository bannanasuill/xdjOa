<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODES = [
        'perm.admin.api.me',
        'perm.admin.api.menus',
        'perm.admin.api.logout',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('code', self::CODES)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        }

        DB::table('permissions')->whereIn('code', self::CODES)->delete();
    }

    public function down(): void
    {
        // 公共接口不再作为 RBAC 节点；不回插种子以外的数据
    }
};
