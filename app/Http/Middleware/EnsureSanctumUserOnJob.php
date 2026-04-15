<?php

namespace App\Http\Middleware;

use App\Models\UserModel;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * 小程序 Sanctum 接口：仅「在职」用户可继续访问；离职/试岗/试用等将撤销当前 Token 并返回与登录一致的业务错误。
 */
class EnsureSanctumUserOnJob
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof UserModel) {
            return $next($request);
        }

        $status = (int) ($user->status ?? UserModel::STATUS_ON_JOB);
        if ($status === UserModel::STATUS_ON_JOB) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $label = UserModel::employmentStatusLabel($status);

        return response()->json([
            'code' => 1001,
            'message' => '账号当前状态为「'.$label.'」，暂不可登录',
            'data' => null,
        ]);
    }
}
