<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_rules')) {
            return;
        }

        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id')->nullable()->comment('门店ID，NULL=全局默认');
            $table->unsignedBigInteger('position_id')->nullable()->comment('职位ID，NULL=所有职位');
            $table->time('work_start_time')->comment('上班时间');
            $table->time('work_end_time')->comment('下班时间');
            $table->unsignedInteger('late_minutes')->nullable()->default(30)->comment('迟到容忍分钟');
            $table->unsignedInteger('early_minutes')->nullable()->default(30)->comment('早退容忍分钟');
            $table->unsignedTinyInteger('allow_remote')->default(0)->comment('允许远程打卡');
            $table->unsignedTinyInteger('need_photo')->default(1)->comment('需要拍照');
            $table->unsignedInteger('priority')->nullable()->default(0)->comment('优先级，越小越优先');
            $table->unsignedTinyInteger('status')->default(1)->comment('1启用 0禁用');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();

            $table->index('store_id', 'idx_store');
            $table->index('position_id', 'idx_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
    }
};
