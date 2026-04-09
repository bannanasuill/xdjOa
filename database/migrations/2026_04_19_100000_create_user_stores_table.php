<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_stores')) {
            return;
        }

        Schema::create('user_stores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('用户ID（users）');
            $table->unsignedBigInteger('store_id')->comment('门店ID（stores）');
            $table->unsignedBigInteger('position_id')->comment('职务ID（positions）');
            $table->unsignedTinyInteger('is_main')->default(1)->comment('是否主门店：1是 0否');
            $table->date('start_date')->comment('生效日期');
            $table->date('end_date')->default('9999-12-31')->comment('失效日期');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();

            $table->index('user_id', 'idx_user');
            $table->index('store_id', 'idx_store');
            $table->index(['user_id', 'is_main'], 'idx_main');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stores');
    }
};
