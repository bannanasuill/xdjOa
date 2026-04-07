<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanAccessAdminPanel
{
    /**
     * 已登录用户必须具备后台登录权限（或系统管理员），否则终止会话并回到登录页。
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && ! $user->canAccessAdminPanel()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['account' => '您没有登录后台的权限，请联系管理员。']);
        }

        return $next($request);
    }
}
