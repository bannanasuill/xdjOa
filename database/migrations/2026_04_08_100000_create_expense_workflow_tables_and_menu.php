<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_templates')) {
            Schema::create('expense_templates', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('模板ID');
                $table->string('name', 100)->nullable()->comment('模板名称');
                $table->string('code', 50)->nullable()->comment('模板编码');
                $table->tinyInteger('status')->default(1)->comment('状态');
                $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');
                $table->unique('code', 'uk_code');
            });
        }

        if (! Schema::hasTable('expense_template_fields')) {
            Schema::create('expense_template_fields', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('字段ID');
                $table->unsignedBigInteger('template_id')->nullable()->comment('模板ID');
                $table->string('field_key', 50)->nullable()->comment('字段key');
                $table->string('field_label', 100)->nullable()->comment('字段名称');
                $table->string('field_type', 50)->nullable()->comment('字段类型');
                $table->tinyInteger('is_required')->default(0)->comment('是否必填');
                $table->integer('sort')->nullable()->comment('排序');
                $table->text('options')->nullable()->comment('选项JSON');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unique(['template_id', 'field_key'], 'uk_field');
            });
        }

        if (! Schema::hasTable('expense_forms')) {
            Schema::create('expense_forms', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('报销单ID');
                $table->unsignedBigInteger('template_id')->nullable()->comment('模板ID');
                $table->unsignedBigInteger('user_id')->nullable()->comment('提交人');
                $table->string('title', 255)->nullable()->comment('标题');
                $table->decimal('total_amount', 10, 2)->nullable()->comment('总金额');
                $table->string('status', 20)->nullable()->comment('状态');
                $table->integer('current_node')->default(1)->comment('当前节点');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');
            });
        }

        if (! Schema::hasTable('expense_form_values')) {
            Schema::create('expense_form_values', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('ID');
                $table->unsignedBigInteger('form_id')->nullable()->comment('报销单ID');
                $table->string('field_key', 50)->nullable()->comment('字段key');
                $table->text('field_value')->nullable()->comment('字段值');
            });
        }

        if (! Schema::hasTable('workflows')) {
            Schema::create('workflows', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('流程ID');
                $table->string('name', 100)->nullable()->comment('流程名称');
                $table->unsignedBigInteger('template_id')->nullable()->comment('模板ID');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
            });
        }

        if (! Schema::hasTable('workflow_nodes')) {
            Schema::create('workflow_nodes', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('节点ID');
                $table->unsignedBigInteger('workflow_id')->nullable()->comment('流程ID');
                $table->integer('node_order')->nullable()->comment('节点顺序');
                $table->string('approver_type', 20)->nullable()->comment('审批人类型');
                $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人ID');
                $table->text('condition_json')->nullable()->comment('条件JSON');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
            });
        }

        if (! Schema::hasTable('approvals')) {
            Schema::create('approvals', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('审批记录ID');
                $table->unsignedBigInteger('form_id')->nullable()->comment('报销单ID');
                $table->integer('node_order')->nullable()->comment('节点顺序');
                $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人ID');
                $table->string('status', 20)->nullable()->comment('审批状态');
                $table->text('comment')->nullable()->comment('审批意见');
                $table->unsignedInteger('approved_at')->nullable()->comment('审批时间');
            });
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $adminRootId = DB::table('permissions')->where('code', 'perm.admin')->value('id');
        if (! $adminRootId) {
            return;
        }

        $now = time();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.expense'],
            [
                'name' => '报销管理',
                'type' => 'menu',
                'parent_id' => (int) $adminRootId,
                'path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $expId = (int) DB::table('permissions')->where('code', 'perm.admin.expense')->value('id');

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.expense.templates'],
            [
                'name' => '报销模板',
                'type' => 'menu',
                'parent_id' => $expId,
                'path' => '/admin/expense/templates',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('permissions')->updateOrInsert(
            ['code' => 'perm.admin.expense.apply'],
            [
                'name' => '报销申请',
                'type' => 'menu',
                'parent_id' => $expId,
                'path' => '/admin/expense/apply',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permIds = DB::table('permissions')
            ->whereIn('code', [
                'perm.admin.expense',
                'perm.admin.expense.templates',
                'perm.admin.expense.apply',
            ])
            ->pluck('id');

        if (Schema::hasTable('role_permissions') && $permIds->isNotEmpty()) {
            foreach ($permIds as $pid) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => 2, 'permission_id' => (int) $pid],
                    ['role_id' => 2, 'permission_id' => (int) $pid]
                );
            }
        }

        if (! Schema::hasTable('menus')) {
            return;
        }

        DB::table('menus')->updateOrInsert(
            ['id' => 8],
            [
                'id' => 8,
                'name' => '报销管理',
                'permission_code' => 'menu.admin_expense',
                'path' => '/admin/expense/templates',
                'component' => '',
                'parent_id' => null,
                'icon' => 'el-icon-s-order',
                'sort' => 17,
                'visible' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('menus')->updateOrInsert(
            ['id' => 9],
            [
                'id' => 9,
                'name' => '报销模板',
                'permission_code' => 'menu.admin_expense_templates',
                'path' => '/admin/expense/templates',
                'component' => '',
                'parent_id' => 8,
                'icon' => '',
                'sort' => 1,
                'visible' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('menus')->updateOrInsert(
            ['id' => 10],
            [
                'id' => 10,
                'name' => '报销申请',
                'permission_code' => 'menu.admin_expense_apply',
                'path' => '/admin/expense/apply',
                'component' => '',
                'parent_id' => 8,
                'icon' => '',
                'sort' => 2,
                'visible' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        $codes = [
            'perm.admin.expense.apply',
            'perm.admin.expense.templates',
            'perm.admin.expense',
        ];

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('role_permissions')) {
                DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('permissions')->whereIn('code', $codes)->delete();
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->whereIn('id', [8, 9, 10])->delete();
        }

        Schema::dropIfExists('approvals');
        Schema::dropIfExists('workflow_nodes');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('expense_form_values');
        Schema::dropIfExists('expense_forms');
        Schema::dropIfExists('expense_template_fields');
        Schema::dropIfExists('expense_templates');
    }
};
