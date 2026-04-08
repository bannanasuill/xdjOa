<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configs')) {
            return;
        }
        DB::table('configs')->where('config_key', 'site_favicon')->delete();
    }

    public function down(): void
    {
        // 不可恢复已删配置
    }
};
