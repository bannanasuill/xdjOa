<?php

use App\Support\ExpenseDefaultWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 移除基于系统角色（approver_type=role）的审批节点，按部门/职务重新写入默认流程；
 * 删除历史种子插入的报销专用 roles（非系统角色）及其 user_roles 关联。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_nodes')) {
            return;
        }

        DB::table('workflow_nodes')->where('approver_type', 'role')->delete();
        DB::table('workflow_nodes')->whereNull('approver_type')->delete();
        DB::table('workflow_nodes')->where('approver_type', '=', '')->delete();

        if (! Schema::hasTable('workflows')) {
            return;
        }

        $now = time();
        DB::table('workflows')->updateOrInsert(
            ['code' => ExpenseDefaultWorkflow::WORKFLOW_CODE],
            [
                'name' => '标准报销审批',
                'template_id' => null,
                'created_at' => $now,
            ]
        );

        $wid = DB::table('workflows')->where('code', ExpenseDefaultWorkflow::WORKFLOW_CODE)->value('id');
        if (! $wid) {
            return;
        }
        $wid = (int) $wid;

        DB::table('workflow_nodes')->where('workflow_id', $wid)->delete();

        foreach (ExpenseDefaultWorkflow::NODES as $n) {
            DB::table('workflow_nodes')->insert([
                'workflow_id' => $wid,
                'node_order' => $n['node_order'],
                'node_name' => $n['node_name'],
                'role_code' => $n['role_code'],
                'approver_type' => $n['approver_type'],
                'approver_id' => null,
                'condition_json' => null,
                'created_at' => $now,
            ]);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('user_roles')) {
            return;
        }

        $legacyIds = DB::table('roles')
            ->whereIn('code', ExpenseDefaultWorkflow::LEGACY_EXPENSE_ROLE_CODES)
            ->where('is_system', 0)
            ->pluck('id');

        if ($legacyIds->isEmpty()) {
            return;
        }

        $ids = $legacyIds->all();
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('role_id', $ids)->delete();
        }
        DB::table('user_roles')->whereIn('role_id', $ids)->delete();
        DB::table('roles')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // 不可逆：已删除的角色与节点无法自动还原
    }
};
