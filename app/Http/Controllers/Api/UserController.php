<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 当前登录用户（小程序）：基础资料 + 当日可打卡门店列表。
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof UserModel) {
            return $this->fail(1006, '未登录');
        }

        $name = trim((string) ($user->real_name ?? ''));
        if ($name === '') {
            $name = trim((string) ($user->account ?? ''));
        }

        $today = date('Y-m-d');
        $places = UserModel::apiClockInPlacesForUserId((int) $user->id, $today);

        return $this->ok('success', [
            'id' => (string) $user->id,
            'account' => (string) ($user->account ?? ''),
            'name' => $name,
            'real_name' => trim((string) ($user->real_name ?? '')),
            'phone' => $user->phone !== null && $user->phone !== '' ? (string) $user->phone : null,
            'clock_in_places' => $places,
            'clock_in_places_date' => $today,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function ok(string $message = 'success', ?array $data = []): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function fail(int $code, string $message, ?array $data = null): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
