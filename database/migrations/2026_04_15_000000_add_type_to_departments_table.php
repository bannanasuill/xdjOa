<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 兼容早期仅有部分列的 departments 表：补齐 path / status / sort / type / 时间戳等，避免 after('path') 因列不存在而失败。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        if (! Schema::hasColumn('departments', 'path')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->string('path', 255)->nullable()->comment('层级路径（如：1/2/3）');
            });
        }

        if (! Schema::hasColumn('departments', 'status')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用');
            });
        }

        if (! Schema::hasColumn('departments', 'sort')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->integer('sort')->default(0)->comment('排序');
            });
        }

        if (! Schema::hasColumn('departments', 'type')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->string('type', 32)->default('department')->comment('组织类型：company/department/branch/store');
            });
        }

        if (! Schema::hasColumn('departments', 'created_at')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
            });
        }

        if (! Schema::hasColumn('departments', 'updated_at')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        if (Schema::hasColumn('departments', 'type')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
