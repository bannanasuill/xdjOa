<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_templates')) {
            return;
        }

        Schema::table('expense_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_templates', 'workflow_id')) {
                $table->unsignedBigInteger('workflow_id')->nullable()->after('code')->comment('关联 workflows.id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('expense_templates') && Schema::hasColumn('expense_templates', 'workflow_id')) {
            Schema::table('expense_templates', function (Blueprint $table) {
                $table->dropColumn('workflow_id');
            });
        }
    }
};
