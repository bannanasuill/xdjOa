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
                $table->string('config_key', 100)->unique()->comment('配置key');
                $table->text('config_value')->nullable()->comment('配置值');
                $table->string('group_name', 50)->nullable()->comment('分组（system/user/expense等）');
                $table->string('name', 100)->nullable()->comment('配置名称');
                $table->string('type', 20)->default('string')->comment('类型：string/number/boolean/json');
                $table->integer('sort')->default(0)->comment('排序');
                $table->string('remark', 255)->nullable()->comment('说明');
                $table->unsignedInteger('created_at')->nullable()->comment('创建时间');
                $table->unsignedInteger('updated_at')->nullable()->comment('更新时间');

                $table->index('group_name');
            });
        }

        if (Schema::hasTable('system_settings')) {
            $meta = [
                'default_user_password' => [
                    'group_name' => 'system',
                    'name' => '新增用户默认密码',
                    'type' => 'string',
                    'sort' => 10,
                    'remark' => '后台新增用户未填写密码时使用',
                ],
                'site_favicon' => [
                    'group_name' => 'system',
                    'name' => '网站图标',
                    'type' => 'string',
                    'sort' => 20,
                    'remark' => '后台与登录页 favicon，支持 URL 或站内路径',
                ],
                'site_name' => [
                    'group_name' => 'system',
                    'name' => '站点名称',
                    'type' => 'string',
                    'sort' => 30,
                    'remark' => '浏览器标题与登录页展示名称',
                ],
            ];
            $now = time();
            foreach (DB::table('system_settings')->cursor() as $row) {
                $key = (string) $row->key;
                $base = $meta[$key] ?? [
                    'group_name' => 'system',
                    'name' => $key,
                    'type' => 'string',
                    'sort' => 0,
                    'remark' => null,
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
