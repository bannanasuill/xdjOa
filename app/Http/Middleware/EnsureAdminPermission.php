<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => '未登录'], 401);
        }

        if ($permission !== '' && ! $user->canAdminPermission($permission)) {
            return response()->json(['message' => '无权执行此操作'], 403);
        }

        return $next($request);
    }
}
