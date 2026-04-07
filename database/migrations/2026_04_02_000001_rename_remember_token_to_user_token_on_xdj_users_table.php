<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 移除 users 表上与「记住我」相关的遗留列（物理表名含连接前缀；remember_token 或历史 user_token）。
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
