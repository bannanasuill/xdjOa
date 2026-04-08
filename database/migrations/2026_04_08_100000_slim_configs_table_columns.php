<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 与 xdj_configs 目标结构对齐：去掉 name/type/sort/remark，仅保留 config_key、config_value、group_name 与整型时间戳。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configs')) {
            return;
        }

        Schema::table('configs', function (Blueprint $table) {
            $drop = [];
            foreach (['name', 'type', 'sort', 'remark'] as $col) {
                if (Schema::hasColumn('configs', $col)) {
                    $drop[] = $col;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configs')) {
            return;
        }

        Schema::table('configs', function (Blueprint $table) {
            if (! Schema::hasColumn('configs', 'name')) {
                $table->string('name', 100)->nullable()->comment('配置名称')->after('group_name');
                $table->string('type', 20)->default('string')->comment('类型：string/number/boolean/json')->after('name');
                $table->integer('sort')->default(0)->comment('排序')->after('type');
                $table->string('remark', 255)->nullable()->comment('说明')->after('sort');
            }
        });
    }
};
