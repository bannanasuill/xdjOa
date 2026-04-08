<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_presence_records')) {
            return;
        }

        Schema::create('user_presence_records', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID（对应 users.id）');
            $table->date('work_date')->comment('业务日期（yyyy-mm-dd）');
            $table->unsignedTinyInteger('record_type')->comment('记录类型：1到岗 2外出');
            $table->unsignedInteger('start_at')->comment('开始时间（时间戳）');
            $table->unsignedInteger('end_at')->comment('结束时间（时间戳）');
            $table->unsignedTinyInteger('source')->default(1)->comment('来源：1小程序 2后台 3导入');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1有效 0作废');
            $table->string('reason', 500)->nullable()->comment('原因/说明（外出必填，补录可填）');
            $table->string('address', 255)->nullable()->comment('地址（可选）');
            $table->decimal('longitude', 10, 6)->nullable()->comment('经度');
            $table->decimal('latitude', 10, 6)->nullable()->comment('纬度');
            $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
            $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');

            $table->index(['user_id', 'start_at', 'end_at'], 'idx_user_time_range');
            $table->index('work_date', 'idx_work_date');
            $table->index('record_type', 'idx_record_type');
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_presence_records');
    }
};

