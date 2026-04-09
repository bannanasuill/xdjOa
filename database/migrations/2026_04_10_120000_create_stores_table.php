<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            return;
        }

        Schema::create('stores', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('门店ID');
            $table->unsignedBigInteger('dept_id')->nullable()->comment('所属部门ID（关联 departments）');
            $table->string('code', 32)->comment('门店编码（唯一）');
            $table->string('name', 64)->comment('门店名称');
            $table->unsignedTinyInteger('store_type')->default(1)->comment('类型：1门店 2总部 3仓库');
            $table->string('address', 255)->nullable()->comment('详细地址');
            $table->decimal('longitude', 10, 6)->nullable()->comment('经度（GCJ02）');
            $table->decimal('latitude', 10, 6)->nullable()->comment('纬度（GCJ02）');
            $table->unsignedInteger('radius')->nullable()->default(100)->comment('允许打卡半径（米）');
            $table->string('wifi_mac', 255)->nullable()->comment('允许的WiFi MAC（逗号分隔）');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1正常 0停用');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();

            $table->unique('code', 'uk_code');
            $table->index('dept_id', 'idx_dept');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
