<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('部门ID');
                $table->string('name', 100)->nullable()->comment('部门名称');
                $table->unsignedBigInteger('parent_id')->default(0)->comment('父部门ID');
                $table->unsignedBigInteger('leader_id')->nullable()->comment('负责人ID');
                $table->integer('level')->default(1)->comment('层级');
                $table->string('path', 255)->nullable()->comment('层级路径（如：1/2/3）');
                $table->string('type', 32)->default('department')->comment('组织类型：company/department/branch/store');
                $table->tinyInteger('status')->default(1)->comment('状态：1启用 0禁用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');
                $table->index('parent_id', 'idx_parent');
                $table->index('leader_id', 'idx_leader');
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
