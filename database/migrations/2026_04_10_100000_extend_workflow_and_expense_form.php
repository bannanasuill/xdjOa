<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workflows')) {
            Schema::table('workflows', function (Blueprint $table) {
                if (! Schema::hasColumn('workflows', 'code')) {
                    $table->string('code', 50)->nullable()->unique()->comment('流程编码，便于种子幂等');
                }
            });
        }

        if (Schema::hasTable('workflow_nodes')) {
            Schema::table('workflow_nodes', function (Blueprint $table) {
                if (! Schema::hasColumn('workflow_nodes', 'node_name')) {
                    $table->string('node_name', 100)->nullable()->after('node_order')->comment('节点展示名');
                }
                if (! Schema::hasColumn('workflow_nodes', 'role_code')) {
                    $table->string('role_code', 50)->nullable()->after('node_name')->comment('审批角色 roles.code');
                }
            });
        }

        if (Schema::hasTable('expense_forms')) {
            Schema::table('expense_forms', function (Blueprint $table) {
                if (! Schema::hasColumn('expense_forms', 'workflow_id')) {
                    $table->unsignedBigInteger('workflow_id')->nullable()->after('template_id')->comment('审批流程ID');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expense_forms') && Schema::hasColumn('expense_forms', 'workflow_id')) {
            Schema::table('expense_forms', function (Blueprint $table) {
                $table->dropColumn('workflow_id');
            });
        }

        if (Schema::hasTable('workflow_nodes')) {
            Schema::table('workflow_nodes', function (Blueprint $table) {
                if (Schema::hasColumn('workflow_nodes', 'node_name')) {
                    $table->dropColumn('node_name');
                }
                if (Schema::hasColumn('workflow_nodes', 'role_code')) {
                    $table->dropColumn('role_code');
                }
            });
        }

        if (Schema::hasTable('workflows') && Schema::hasColumn('workflows', 'code')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->dropUnique(['code']);
            });
            Schema::table('workflows', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }
    }
};
