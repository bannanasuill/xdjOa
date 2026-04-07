<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->nullable()->comment('角色名称');
            $table->string('code', 50)->nullable()->comment('角色标识');
            $table->string('data_scope', 20)->default('self')->comment('数据权限范围');
            $table->unsignedTinyInteger('is_system')->default(0)->comment('是否系统角色');
            $table->unsignedInteger('created_at')->nullable();
            $table->unsignedInteger('updated_at')->nullable();

            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
