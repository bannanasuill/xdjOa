<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 部门 + 职位示例数据（与 database/sql/xdj_departments_positions_seed.sql 一致）。
 * 使用 Laravel 连接与表前缀，避免手工执行 SQL 时表名/前缀不一致导致「没有插入」。
 */
class OrgStructureSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('departments') || ! Schema::hasTable('positions')) {
            $this->command?->warn('跳过组织种子：departments 或 positions 表不存在，请先执行 php artisan migrate。');

            return;
        }

        $now = time();

        $deptColumns = array_flip(Schema::getColumnListing('departments'));
        $departments = [
            ['id' => 1, 'name' => '总公司', 'parent_id' => 0, 'leader_id' => null, 'level' => 1, 'path' => '1', 'status' => 1, 'sort' => 0, 'type' => 'company', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '财务部', 'parent_id' => 1, 'leader_id' => null, 'level' => 2, 'path' => '1/2', 'status' => 1, 'sort' => 2, 'type' => 'department', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => '市场部', 'parent_id' => 1, 'leader_id' => null, 'level' => 2, 'path' => '1/3', 'status' => 1, 'sort' => 3, 'type' => 'department', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => '上海分公司', 'parent_id' => 1, 'leader_id' => null, 'level' => 2, 'path' => '1/10', 'status' => 1, 'sort' => 10, 'type' => 'branch', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => '上海门店1', 'parent_id' => 10, 'leader_id' => null, 'level' => 3, 'path' => '1/10/11', 'status' => 1, 'sort' => 11, 'type' => 'store', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'name' => '上海门店2', 'parent_id' => 10, 'leader_id' => null, 'level' => 3, 'path' => '1/10/12', 'status' => 1, 'sort' => 12, 'type' => 'store', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'name' => '北京分公司', 'parent_id' => 1, 'leader_id' => null, 'level' => 2, 'path' => '1/20', 'status' => 1, 'sort' => 20, 'type' => 'branch', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 21, 'name' => '北京门店1', 'parent_id' => 20, 'leader_id' => null, 'level' => 3, 'path' => '1/20/21', 'status' => 1, 'sort' => 21, 'type' => 'store', 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($departments as $row) {
            $payload = array_intersect_key($row, $deptColumns);
            $id = (int) $payload['id'];
            DB::table('departments')->updateOrInsert(['id' => $id], $payload);
        }

        $this->realignAutoIncrement('departments');

        $posColumns = array_flip(Schema::getColumnListing('positions'));
        $positions = [
            ['id' => 1, 'name' => '总经理', 'code' => 'ceo', 'dept_id' => 1, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '财务经理', 'code' => 'finance_manager', 'dept_id' => 2, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => '财务助理', 'code' => 'finance_assistant', 'dept_id' => 2, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => '市场经理', 'code' => 'marketing_manager', 'dept_id' => 3, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => '分公司负责人', 'code' => 'branch_manager', 'dept_id' => 10, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => '督导', 'code' => 'supervisor', 'dept_id' => 10, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 20, 'name' => '店长', 'code' => 'store_manager', 'dept_id' => 11, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 21, 'name' => '店员', 'code' => 'staff', 'dept_id' => 11, 'level' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($positions as $row) {
            $payload = array_intersect_key($row, $posColumns);
            $id = (int) $payload['id'];
            DB::table('positions')->updateOrInsert(['id' => $id], $payload);
        }

        $this->realignAutoIncrement('positions');

        $this->command?->info('已写入组织示例数据（部门 + 职位）。');
    }

    private function realignAutoIncrement(string $table): void
    {
        $max = DB::table($table)->max('id');
        if ($max === null) {
            return;
        }
        $next = (int) $max + 1;
        $prefix = Schema::getConnection()->getTablePrefix();
        $quoted = '`'.$prefix.$table.'`';
        DB::statement("ALTER TABLE {$quoted} AUTO_INCREMENT = {$next}");
    }
}
