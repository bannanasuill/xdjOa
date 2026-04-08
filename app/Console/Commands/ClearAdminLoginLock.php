<?php

namespace App\Console\Commands;

use App\Models\UserModel;
use App\Support\AdminLoginRateLimit;
use Illuminate\Console\Command;

class ClearAdminLoginLock extends Command
{
    protected $signature = 'admin:clear-login-lock
        {--account= : 后台登录账号}
        {--user-id= : 用户表主键（与 account 可只填其一；同时填时会校验是否同一用户）}
        {--ip= : 客户端 IP，用于清除「账号+IP」维度；本机测试请填当前访问后台的公网/内网 IP}';

    protected $description = '手动清除后台登录失败限流（Redis/缓存中的 RateLimiter 计数）。另：路由 throttle:30,1 为每分钟请求次数，需等待或换 IP，不在此命令范围内。';

    public function handle(): int
    {
        $accountOpt = trim((string) $this->option('account'));
        $userIdRaw = trim((string) $this->option('user-id'));
        $ipOpt = trim((string) $this->option('ip'));

        if ($accountOpt === '' && $userIdRaw === '') {
            $this->error('请至少指定 --account 或 --user-id。');

            return self::FAILURE;
        }

        $user = null;
        if ($userIdRaw !== '') {
            if (! ctype_digit($userIdRaw)) {
                $this->error('--user-id 须为数字。');

                return self::FAILURE;
            }
            $user = UserModel::query()->find((int) $userIdRaw);
            if ($user === null) {
                $this->error("不存在 user_id={$userIdRaw}。");

                return self::FAILURE;
            }
        }

        if ($accountOpt !== '') {
            $byAccount = UserModel::findByAccount($accountOpt);
            if ($user !== null && $byAccount !== null && (int) $byAccount->id !== (int) $user->id) {
                $this->error('--account 与 --user-id 不是同一用户。');

                return self::FAILURE;
            }
            if ($user === null) {
                $user = $byAccount;
            }
        }

        $clearedKeys = [];

        if ($user !== null) {
            $key = AdminLoginRateLimit::userKey((int) $user->id);
            AdminLoginRateLimit::clearUser((int) $user->id);
            $clearedKeys[] = $key.'（全 IP 累计，admin-login-acc）';
        }

        if ($accountOpt !== '' && $ipOpt !== '') {
            $key = AdminLoginRateLimit::ipAccountKey($accountOpt, $ipOpt);
            AdminLoginRateLimit::clearIpAccount($accountOpt, $ipOpt);
            $clearedKeys[] = $key.'（单 IP + 账号，admin-login）';
        }

        if ($accountOpt !== '' && $ipOpt === '') {
            if ($user === null) {
                $this->warn('账号在库中不存在时，「账号+IP」限流不会清掉；请同时传入 --ip。');
            } else {
                $this->comment('未传 --ip：未清除「账号+IP」维度；若仍提示本机频繁，请执行：');
                $this->line('  php artisan '.$this->name.' --account='.escapeshellarg($accountOpt).' --ip=<客户端IP>', 'fg=yellow');
            }
        }

        if ($user !== null && $accountOpt === '' && $ipOpt === '') {
            $this->comment('仅清除全 IP 账号维度；若仍被锁，请用该用户 account 加上 --ip 再执行一次。');
        }

        if ($clearedKeys === []) {
            $this->error('没有清除任何键（请检查参数）。');

            return self::FAILURE;
        }

        $this->info('已清除限流键：');
        foreach ($clearedKeys as $line) {
            $this->line('  '.$line);
        }

        return self::SUCCESS;
    }
}
