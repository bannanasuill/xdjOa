<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_presence_records')) {
            return;
        }
        if (! Schema::hasColumn('user_presence_records', 'end_at')) {
            return;
        }

        DB::statement('ALTER TABLE user_presence_records MODIFY end_at INT UNSIGNED NULL COMMENT "结束时间（时间戳，未结束可为空）"');
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_presence_records')) {
            return;
        }
        if (! Schema::hasColumn('user_presence_records', 'end_at')) {
            return;
        }

        DB::statement('UPDATE user_presence_records SET end_at = start_at WHERE end_at IS NULL');
        DB::statement('ALTER TABLE user_presence_records MODIFY end_at INT UNSIGNED NOT NULL COMMENT "结束时间（时间戳）"');
    }
};

