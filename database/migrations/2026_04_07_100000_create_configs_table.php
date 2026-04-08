<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configs')) {
            Schema::create('configs', function (Blueprint $table) {
                $table->bigIncrements('id')->comment('配置ID');
                $table->string('config_key', 100)->comment('配置key');
                $table->text('config_value')->nullable()->comment('配置值');
                $table->string('group_name', 50)->nullable()->comment('分组（system/user/expense等）');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');

                $table->unique('config_key', 'uk_config_key');
                $table->index('group_name', 'idx_group');
            });
        }

        if (Schema::hasTable('system_settings')) {
            $meta = [
                'default_user_password' => [
                    'group_name' => 'system',
                ],
                'site_favicon' => [
                    'group_name' => 'system',
                ],
                'site_name' => [
                    'group_name' => 'system',
                ],
            ];
            $now = time();
            foreach (DB::table('system_settings')->cursor() as $row) {
                $key = (string) $row->key;
                $base = $meta[$key] ?? [
                    'group_name' => 'system',
                ];
                DB::table('configs')->updateOrInsert(
                    ['config_key' => $key],
                    array_merge($base, [
                        'config_value' => $row->value,
                        'created_at' => $now,
                        'updated_at' => (int) ($row->updated_at ?? $now),
                    ])
                );
            }
            Schema::drop('system_settings');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};
