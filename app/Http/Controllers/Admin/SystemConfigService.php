<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSettingModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SystemConfigService extends Controller
{
    public function apiShow(): JsonResponse
    {
        $fromDb = SystemSettingModel::get(SystemSettingModel::KEY_DEFAULT_USER_PASSWORD);
        $fromDb = $fromDb !== null ? trim((string) $fromDb) : '';
        $pwdInDb = $fromDb !== '';

        // 未写入 configs 表时，展示当前实际会用的值（来自 config/admin.php → .env 的 DEFAULT_USER_PASSWORD）
        $displayPassword = $pwdInDb ? $fromDb : (string) config('admin.default_user_password', '');

        return response()->json([
            'data' => [
                'default_user_password_set' => $pwdInDb,
                'default_user_password' => $displayPassword,
                'site_favicon' => SystemSettingModel::get(SystemSettingModel::KEY_SITE_FAVICON) ?? '',
                'site_name' => SystemSettingModel::get(SystemSettingModel::KEY_SITE_NAME) ?? '',
                'site_name_display' => SystemSettingModel::resolvedSiteName(),
                'site_favicon_resolved' => SystemSettingModel::resolvedFaviconHref(),
            ],
        ]);
    }

    public function apiUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'site_favicon' => ['sometimes', 'nullable', 'string', 'max:500'],
            'site_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if ($request->has('default_user_password')) {
            $raw = $request->input('default_user_password');
            if ($raw === null || trim((string) $raw) === '') {
                SystemSettingModel::deleteKey(SystemSettingModel::KEY_DEFAULT_USER_PASSWORD);
            } else {
                $pw = trim((string) $raw);
                if (strlen($pw) < 6) {
                    throw ValidationException::withMessages([
                        'default_user_password' => ['默认密码至少 6 位。'],
                    ]);
                }
                SystemSettingModel::set(SystemSettingModel::KEY_DEFAULT_USER_PASSWORD, $pw);
            }
        }

        if ($request->has('site_favicon')) {
            $fv = trim((string) $request->input('site_favicon', ''));
            if ($fv === '') {
                SystemSettingModel::deleteKey(SystemSettingModel::KEY_SITE_FAVICON);
            } else {
                SystemSettingModel::set(SystemSettingModel::KEY_SITE_FAVICON, $fv);
            }
        }

        if ($request->has('site_name')) {
            $sn = trim((string) $request->input('site_name', ''));
            if ($sn === '') {
                SystemSettingModel::deleteKey(SystemSettingModel::KEY_SITE_NAME);
            } else {
                SystemSettingModel::set(SystemSettingModel::KEY_SITE_NAME, $sn);
            }
        }

        return response()->json(['ok' => true, 'message' => '已保存。']);
    }
}
