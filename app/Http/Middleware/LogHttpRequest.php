<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 请求结束时写入 Laravel 日志（与业务 user_log 审计表并行，用于排障与访问审计）。
 * 通过 config('logging.http_access') 开关；默认仅 local 环境开启。
 */
class LogHttpRequest
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('logging.http_access.enabled')) {
            return;
        }

        $reqPath = $request->path();
        foreach (config('logging.http_access.skip_path_prefixes', []) as $prefix) {
            $prefix = ltrim((string) $prefix, '/');
            if ($prefix !== '' && str_starts_with($reqPath, $prefix)) {
                return;
            }
        }

        $path = $request->path();
        if (preg_match('/\.(?:ico|png|jpe?g|gif|webp|css|js|map|woff2?|svg|ttf)$/i', $path)) {
            return;
        }

        $channel = config('logging.http_access.channel', config('logging.default'));
        $durationMs = defined('LARAVEL_START')
            ? round((microtime(true) - LARAVEL_START) * 1000, 2)
            : null;

        Log::channel($channel)->info('http_access', [
            'method' => $request->method(),
            'path' => '/'.$path,
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'duration_ms' => $durationMs,
        ]);
    }
}
