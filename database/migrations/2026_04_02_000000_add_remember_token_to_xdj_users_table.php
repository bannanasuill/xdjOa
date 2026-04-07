<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * 保留文件名以兼容已登记 migrations 表的环境；已不再添加 remember / user 令牌列。
     */
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
