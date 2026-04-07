<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_log')) {
            return;
        }

        Schema::create('user_log', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('日志ID');

            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->string('account', 50)->nullable()->comment('操作账号（冗余）');
            $table->string('real_name', 50)->nullable()->comment('操作人（冗余）');

            $table->string('log_type', 20)->nullable()->comment('日志类型：login/operation/error');
            $table->string('module', 50)->nullable()->comment('模块：user/expense/approval');
            $table->string('action', 50)->nullable()->comment('操作类型：login/create/update/delete/approve');

            $table->string('target_type', 50)->nullable()->comment('操作对象类型');
            $table->unsignedBigInteger('target_id')->nullable()->comment('操作对象ID');

            $table->string('method', 10)->nullable()->comment('请求方式');
            $table->string('url', 255)->nullable()->comment('请求地址');

            $table->string('ip', 50)->nullable()->comment('IP地址');
            $table->string('user_agent', 255)->nullable()->comment('浏览器信息');

            $table->json('request_data')->nullable()->comment('请求参数（JSON）');
            $table->json('response_data')->nullable()->comment('响应数据（JSON）');

            $table->tinyInteger('status')->default(1)->comment('状态：1成功 0失败');
            $table->string('message', 255)->nullable()->comment('结果说明');

            $table->string('trace_id', 100)->nullable()->comment('链路追踪ID');

            $table->unsignedInteger('created_at')->nullable()->comment('创建时间');

            $table->index('user_id', 'idx_user_id');
            $table->index('log_type', 'idx_log_type');
            $table->index('module', 'idx_module');
            $table->index('action', 'idx_action');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_log');
    }
};

