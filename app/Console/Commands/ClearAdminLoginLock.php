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
        {--ip=* : 客户端 IP，可多次传入或逗号分隔；须与后台登录时 Laravel 中 request->ip() 一致（反代见 TrustProxies）}
        {--with-local-ips : 额外清除 127.0.0.1、::1 的「账号+IP」键（本机直连 PHP/未信任反代时常见）}';

    protected $description = '手动清除后台登录失败限流（Redis/缓存中的 RateLimiter 计数）。另：路由 throttle:30,1 为每分钟请求次数，需等待或换 IP，不在此命令范围内。';

    public function handle(): int
    {
        $accountOpt = trim((string) $this->option('account'));
        $userIdRaw = trim((string) $this->option('user-id'));
        $rawIps = $this->option('ip');
        $ipList = $this->normalizeIpList(is_array($rawIps) ? $rawIps : ($rawIps !== null && $rawIps !== '' ? [(string) $rawIps] : []));
        if ($this->option('with-local-ips') && $accountOpt !== '') {
            foreach (['127.0.0.1', '::1'] as $local) {
                if (! in_array($local, $ipList, true)) {
                    $ipList[] = $local;
                }
            }
        }

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

        if ($accountOpt !== '' && $ipList !== []) {
            foreach ($ipList as $ip) {
                $key = AdminLoginRateLimit::ipAccountKey($accountOpt, $ip);
                AdminLoginRateLimit::clearIpAccount($accountOpt, $ip);
                $clearedKeys[] = $key.'（单 IP + 账号，admin-login，ip='.$ip.'）';
            }
        }

        if ($accountOpt !== '' && $ipList === []) {
            if ($user === null) {
                $this->warn('库中无此账号时，无法按「用户 ID」清除；「账号+IP」限流必须带 --ip（或与 request->ip() 一致的多个 IP）。');
                $this->line('  可先试：php artisan '.$this->name.' --account='.escapeshellarg($accountOpt).' --with-local-ips --ip=公网IP', 'fg=yellow');
            } else {
                $this->comment('未传 --ip：未清除「账号+IP」维度；若仍提示频繁，请外加 --ip 或加 --with-local-ips：');
                $this->line('  php artisan '.$this->name.' --account='.escapeshellarg($accountOpt).' --with-local-ips --ip=<浏览器侧在服务端看到的 IP>', 'fg=yellow');
            }
        }

        if ($user !== null && $accountOpt === '' && $ipList === []) {
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

    /**
     * @param  array<int, string|null>  $optionIp
     * @return array<int, string>
     */
    private function normalizeIpList(array $optionIp): array
    {
        $out = [];
        foreach ($optionIp as $chunk) {
            if ($chunk === null || $chunk === '') {
                continue;
            }
            foreach (preg_split('/\s*,\s*/', trim((string) $chunk), -1, PREG_SPLIT_NO_EMPTY) as $ip) {
                $out[] = $ip;
            }
        }

        return array_values(array_unique($out));
    }
}
