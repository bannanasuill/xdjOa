<?php

use App\Support\ExpenseDefaultWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflows') || ! Schema::hasTable('workflow_nodes')) {
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('workflows')) {
            return;
        }

        $wid = DB::table('workflows')->where('code', ExpenseDefaultWorkflow::WORKFLOW_CODE)->value('id');
        if ($wid && Schema::hasTable('workflow_nodes')) {
            DB::table('workflow_nodes')->where('workflow_id', (int) $wid)->delete();
        }

        if (Schema::hasTable('workflows')) {
            DB::table('workflows')->where('code', ExpenseDefaultWorkflow::WORKFLOW_CODE)->delete();
        }
    }
};
