<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $parentId = DB::table('permissions')->where('code', 'perm.admin.expense.templates')->value('id');
        if (! $parentId) {
            return;
        }

        $now = time();
        $parentId = (int) $parentId;

        $rows = [
            [
                'code' => 'perm.admin.api.expense_templates.index',
                'name' => '接口：报销模板列表',
                'path' => 'GET /admin/api/expense-templates',
            ],
            [
                'code' => 'perm.admin.api.expense_templates.store',
                'name' => '接口：报销模板新增',
                'path' => 'POST /admin/api/expense-templates',
            ],
            [
                'code' => 'perm.admin.api.expense_templates.update',
                'name' => '接口：报销模板更新',
                'path' => 'PUT /admin/api/expense-templates/{expenseTemplate}',
            ],
            [
                'code' => 'perm.admin.api.expense_templates.status',
                'name' => '接口：报销模板状态',
                'path' => 'PATCH /admin/api/expense-templates/{expenseTemplate}/status',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => 'api',
                    'parent_id' => $parentId,
                    'path' => $row['path'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $ids = DB::table('permissions')
            ->whereIn('code', array_column($rows, 'code'))
            ->pluck('id');

        if (Schema::hasTable('role_permissions') && $ids->isNotEmpty()) {
            foreach ($ids as $pid) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => 2, 'permission_id' => (int) $pid],
                    ['role_id' => 2, 'permission_id' => (int) $pid]
                );
            }
        }
    }

    public function down(): void
    {
        $codes = [
            'perm.admin.api.expense_templates.status',
            'perm.admin.api.expense_templates.update',
            'perm.admin.api.expense_templates.store',
            'perm.admin.api.expense_templates.index',
        ];

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
        if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        }
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};
