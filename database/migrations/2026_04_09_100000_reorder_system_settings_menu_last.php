<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 侧栏按 sort 排序：系统配置置后，便于与「用户 / 报销 / 日志 / 菜单 / 权限 / 角色」区分。
     */
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $now = time();
        DB::table('menus')->where('id', 7)->update([
            'sort' => 99,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $now = time();
        DB::table('menus')->where('id', 7)->update([
            'sort' => 15,
            'updated_at' => $now,
        ]);
    }
};
