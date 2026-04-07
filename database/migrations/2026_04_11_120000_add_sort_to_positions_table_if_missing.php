<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('positions')) {
            return;
        }

        if (! Schema::hasColumn('positions', 'sort')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->integer('sort')->default(0)->comment('排序');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('positions') && Schema::hasColumn('positions', 'sort')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropColumn('sort');
            });
        }
    }
};
