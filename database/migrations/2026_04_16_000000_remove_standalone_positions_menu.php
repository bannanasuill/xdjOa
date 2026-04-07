<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = time();
        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('id', 12)->delete();
            DB::table('menus')->where('id', 11)->update([
                'name' => '部门与职务',
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $now = time();
        DB::table('menus')->where('id', 11)->update([
            'name' => '部门列表',
            'updated_at' => $now,
        ]);
        DB::table('menus')->updateOrInsert(
            ['id' => 12],
            [
                'id' => 12,
                'name' => '职务列表',
                'permission_code' => 'menu.admin_positions',
                'path' => '/admin/positions',
                'component' => '',
                'parent_id' => 1,
                'icon' => '',
                'sort' => 3,
                'visible' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
};
