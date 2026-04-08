<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        if (! Schema::hasTable('users')) {
            return $this->fail(1999, '用户表未就绪');
        }

        $validator = Validator::make($request->all(), [
            'account' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'remember' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $account = trim((string) $request->input('account', ''));
        $password = (string) $request->input('password', '');
        if ($account === '') {
            return $this->fail(1003, '账号不能为空');
        }

        /** @var UserModel|null $user */
        $user = UserModel::query()
            ->where(function ($q) use ($account) {
                $q->where('account', $account)
                    ->orWhere('real_name', $account);
            })
            ->orderByRaw('case when account = ? then 0 else 1 end', [$account])
            ->first();
        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return $this->fail(1001, '账号或密码错误');
        }

        if ((int) ($user->status ?? 1) !== 1) {
            return $this->fail(1001, '账号已禁用');
        }

        $token = $user->createToken('app_login')->plainTextToken;
        $name = trim((string) ($user->real_name ?? '')) ?: trim((string) ($user->account ?? ''));

        return $this->ok('登录成功', [
            'token' => $token,
            'userInfo' => [
                'id' => (string) $user->id,
                'name' => $name,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        if (! Schema::hasTable('users')) {
            return $this->fail(1999, '用户表未就绪');
        }

        $validator = Validator::make($request->all(), [
            'account' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:20'],
            'confirmPassword' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return $this->fail(1003, $validator->errors()->first() ?: '参数校验失败');
        }

        $account = trim((string) $request->input('account', ''));
        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('confirmPassword', '');

        if ($account === '') {
            return $this->fail(1003, '账号不能为空');
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 20) {
            return $this->fail(1004, '密码需为 6-20 位');
        }
        if ($password !== $confirmPassword) {
            return $this->fail(1005, '两次密码不一致');
        }

        if (UserModel::query()->where('account', $account)->exists()) {
            return $this->fail(1002, '账号已存在');
        }

        $now = time();
        $user = new UserModel;
        $user->account = $account;
        $user->password = $password;
        $user->real_name = $account;
        $user->status = 1;
        $user->created_at = $now;
        $user->updated_at = $now;
        $user->save();

        return $this->ok('注册成功', [
            'userId' => (string) $user->id,
            'account' => $account,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->fail(1006, '未登录');
        }

        $token = $user->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return $this->ok('登出成功', null);
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
