<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLogModel;
use App\Models\UserModel;
use App\Support\AdminLoginRateLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthService extends Controller
{
    /** 同一账号 + IP：失败次数上限，窗口内超限则临时禁止登录 */
    private const LOGIN_IP_ACCOUNT_MAX_ATTEMPTS = 5;

    /** 同一账号 + IP：失败次数窗口期，窗口期结束后重置失败次数 */
    private const LOGIN_IP_ACCOUNT_DECAY_SECONDS = 900;

    /** 同一已存在用户 ID：全 IP 累计失败上限（防分布式暴破） */
    private const LOGIN_USER_MAX_ATTEMPTS = 20;

    /** 同一已存在用户 ID：失败次数窗口期，窗口期结束后重置失败次数 */
    private const LOGIN_USER_DECAY_SECONDS = 3600;

    public function showLoginForm(): View
    {
        return view('admin.auth.login');
    }

    /**
     * @return RedirectResponse|null 若已限流则返回重定向，否则 null
     */
    private function ensureLoginNotRateLimited(Request $request, string $account, ?UserModel $user): ?RedirectResponse
    {
        $ipAccKey = AdminLoginRateLimit::ipAccountKey($account, (string) $request->ip());
        if (RateLimiter::tooManyAttempts($ipAccKey, self::LOGIN_IP_ACCOUNT_MAX_ATTEMPTS)) {
            $sec = RateLimiter::availableIn($ipAccKey);

            return $this->loginLockedResponse($request, $account, $sec, '登录尝试过于频繁（本机或当前账号），请稍后再试。');
        }

        if ($user !== null && RateLimiter::tooManyAttempts(AdminLoginRateLimit::userKey($user->id), self::LOGIN_USER_MAX_ATTEMPTS)) {
            $sec = RateLimiter::availableIn(AdminLoginRateLimit::userKey($user->id));

            return $this->loginLockedResponse($request, $account, $sec, '该账号登录失败次数过多，已临时限制，请稍后再试。');
        }

        return null;
    }

    private function loginLockedResponse(Request $request, string $account, int $seconds, string $logMessage): RedirectResponse
    {
        $minutes = max(1, (int) ceil($seconds / 60));
        UserLogModel::insertAuthAudit($request, null, 'login', 0, $logMessage, ['account' => $account], $account, null);

        return back()
            ->withErrors(['account' => "登录尝试过于频繁，请 {$minutes} 分钟后再试。"])
            ->withInput($request->only('account'));
    }

    private function clearAdminLoginRateLimit(Request $request, string $account, UserModel $user): void
    {
        AdminLoginRateLimit::clearAfterSuccessfulLogin($account, (string) $request->ip(), $user->id);
    }

    private function hitAdminLoginRateLimitOnFailure(Request $request, string $account, ?UserModel $user): void
    {
        RateLimiter::hit(
            AdminLoginRateLimit::ipAccountKey($account, (string) $request->ip()),
            self::LOGIN_IP_ACCOUNT_DECAY_SECONDS
        );

        if ($user !== null) {
            RateLimiter::hit(
                AdminLoginRateLimit::userKey($user->id),
                self::LOGIN_USER_DECAY_SECONDS
            );
        }
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'account' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        $user = UserModel::findByAccount($credentials['account']);

        if ($locked = $this->ensureLoginNotRateLimited($request, $credentials['account'], $user)) {
            return $locked;
        }

        if (
            ! $user
            || (int) $user->status !== 1
            || ! Hash::check($credentials['password'], $user->password)
        ) {
            $this->hitAdminLoginRateLimitOnFailure($request, $credentials['account'], $user);

            UserLogModel::insertAuthAudit(
                $request,
                null,
                'login',
                0,
                '登录失败：账号或密码错误，或账号已禁用。',
                ['account' => $credentials['account']],
                $credentials['account'],
                $user?->real_name ?: null,
            );

            return back()
                ->withErrors(['account' => '账号或密码错误，或账号已禁用。'])
                ->withInput($request->only('account'));
        }

        if (! $user->canAccessAdminPanel()) {
            $this->hitAdminLoginRateLimitOnFailure($request, $credentials['account'], $user);

            UserLogModel::insertAuthAudit(
                $request,
                null,
                'login',
                0,
                '登录失败：无后台登录权限。',
                ['account' => $credentials['account']],
                $credentials['account'],
                $user->real_name ?: null,
            );

            return back()
                ->withErrors(['account' => '您没有登录后台的权限，请联系管理员。'])
                ->withInput($request->only('account'));
        }

        $this->clearAdminLoginRateLimit($request, $credentials['account'], $user);

        Auth::login($user);
        $request->session()->regenerate();

        UserLogModel::insertAuthAudit(
            $request,
            $user,
            'login',
            1,
            '登录成功。',
            [
                'account' => $user->account,
            ],
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            UserLogModel::insertAuthAudit($request, $user, 'logout', 1, '退出成功。');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
