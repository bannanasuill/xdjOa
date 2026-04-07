<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = time();
        DB::table('roles')
            ->where('id', 1)
            ->where('is_system', 1)
            ->update([
                'code' => 'super_admin',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $now = time();
        DB::table('roles')
            ->where('id', 1)
            ->where('is_system', 1)
            ->update([
                'code' => 'admin',
                'updated_at' => $now,
            ]);
    }
};
