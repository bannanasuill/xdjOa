<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 已执行过旧版 2026_04_02_000001 的环境不会再次运行该文件；此处幂等删除遗留列。
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('remember_token');
            });
        }

        if (Schema::hasColumn('users', 'user_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('user_token');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
