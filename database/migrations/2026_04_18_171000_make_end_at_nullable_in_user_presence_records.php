<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'user_presence_records';
        if (! Schema::hasTable($table)) {
            return;
        }
        if (! Schema::hasColumn($table, 'end_at')) {
            return;
        }

        DB::statement('ALTER TABLE user_presence_records MODIFY end_at INT UNSIGNED NULL COMMENT "结束时间（时间戳，未结束可为空）"');
    }

    public function down(): void
    {
        $table = 'user_presence_records';
        if (! Schema::hasTable($table)) {
            return;
        }
        if (! Schema::hasColumn($table, 'end_at')) {
            return;
        }

        DB::statement('UPDATE user_presence_records SET end_at = start_at WHERE end_at IS NULL');
        DB::statement('ALTER TABLE user_presence_records MODIFY end_at INT UNSIGNED NOT NULL COMMENT "结束时间（时间戳）"');
    }
};

