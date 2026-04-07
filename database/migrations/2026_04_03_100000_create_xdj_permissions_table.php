<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            return;
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable()->comment('权限名称');
            $table->string('code', 100)->nullable()->comment('权限标识');
            $table->string('type', 20)->nullable()->comment('类型：menu/button/api');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父权限ID');
            $table->string('path', 255)->nullable()->comment('接口或路由路径');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
