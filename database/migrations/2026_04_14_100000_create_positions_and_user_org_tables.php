<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('positions') && ! Schema::hasColumn('positions', 'dept_id')) {
            Schema::drop('positions');
        }

        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('职位ID');
                $table->string('name', 100)->comment('职位名称');
                $table->string('code', 50)->comment('职位标识');
                $table->unsignedBigInteger('dept_id')->comment('所属部门ID');
                $table->integer('level')->default(1)->comment('职级（可用于审批）');
                $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');
                $table->unique('code', 'uk_positions_code');
                $table->index('dept_id', 'idx_positions_dept');
            });
        }

        if (! Schema::hasTable('user_departments')) {
            Schema::create('user_departments', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('主键ID');
                $table->unsignedBigInteger('user_id')->comment('用户ID');
                $table->unsignedBigInteger('dept_id')->comment('部门ID');
                $table->tinyInteger('is_primary')->default(0)->comment('是否主部门');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unique(['user_id', 'dept_id'], 'uk_user_departments_user_dept');
                $table->index('user_id', 'idx_user_departments_user');
                $table->index('dept_id', 'idx_user_departments_dept');
            });
        }

        if (! Schema::hasTable('user_positions')) {
            Schema::create('user_positions', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('主键ID');
                $table->unsignedBigInteger('user_id')->comment('用户ID');
                $table->unsignedBigInteger('position_id')->comment('职位ID');
                $table->tinyInteger('is_primary')->default(0)->comment('是否主职位');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unique(['user_id', 'position_id'], 'uk_user_positions_user_position');
                $table->index('user_id', 'idx_user_positions_user');
                $table->index('position_id', 'idx_user_positions_position');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_positions');
        Schema::dropIfExists('user_departments');
        Schema::dropIfExists('positions');
    }
};
