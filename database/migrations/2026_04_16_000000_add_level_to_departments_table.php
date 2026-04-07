<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 兼容早期 departments 表未含 level 的情况（applyHierarchy / apiStore 会写入 level）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        if (! Schema::hasColumn('departments', 'level')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->integer('level')->default(1)->comment('层级');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        if (Schema::hasColumn('departments', 'level')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('level');
            });
        }
    }
};
