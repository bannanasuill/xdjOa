<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserLogModel extends Model
{
    protected $table = 'user_log';

    public $timestamps = false;

    /**
     * 允许在日志管理里筛选的 target_type 列表。
     * 你只需要在这里维护类型，UI 下拉框与后端校验都会联动。
     *
     * @return array<int, string>
     */
    public static function targetTypeOptions(): array
    {
        return [
            // Http\Controllers\Admin\UserService 写入的 target_type（如 create/update/status）
            'admin_user',
        ];
    }

    /**
     * 对象类型中文映射（用于前端展示）。
     *
     * @return array<string, string>
     */
    public static function targetTypeLabels(): array
    {
        return [
            'admin_user' => '用户',
        ];
    }

    /**
     * 允许在日志管理里筛选的 log_type 列表。
     *
     * @return array<int, string>
     */
    public static function logTypeOptions(): array
    {
        return [
            'login',
            'operation',
            'error',
        ];
    }

    /**
     * 日志类型中文映射（用于前端展示）。
     *
     * @return array<string, string> 例如 ["login"=>"登录", ...]
     */
    public static function logTypeLabels(): array
    {
        return [
            'login' => '登录',
            'operation' => '操作',
            'error' => '错误',
        ];
    }

    /**
     * 允许在日志管理里筛选的 module 列表。
     *
     * @return array<int, string>
     */
    public static function moduleOptions(): array
    {
        return [
            // 当前只实现了 users 模块日志
            'user',
        ];
    }

    /**
     * 模块中文映射（用于前端展示）。
     *
     * @return array<string, string>
     */
    public static function moduleLabels(): array
    {
        return [
            'user' => '用户',
        ];
    }

    /**
     * 允许在日志管理里筛选的 action 列表。
     *
     * @return array<int, string>
     */
    public static function actionOptions(): array
    {
        return [
            'login',
            'logout',
            'create',
            'update',
            'delete',
            'approve',
        ];
    }

    /**
     * 动作中文映射（用于前端展示）。
     *
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'login' => '登录',
            'logout' => '退出',
            'create' => '新增',
            'update' => '修改',
            'delete' => '删除',
            'approve' => '审批',
        ];
    }

    /**
     * 日志时间筛选：下拉日期选项（最近 N 天，含今天）。
     *
     * @return array<int, string> 例如 ["2026-04-02", "2026-04-01", ...]
     */
    public static function dateOptions(int $days = 30): array
    {
        $days = max(1, min(180, $days));
        $out = [];
        $today = strtotime(date('Y-m-d'));
        for ($i = 0; $i < $days; $i++) {
            $out[] = date('Y-m-d', $today - ($i * 86400));
        }
        return $out;
    }

    /**
     * 写入一条后台审计日志（前缀 + user_log）；表不存在时直接返回。
     */
    public static function insertFromRequest(
        Request $request,
        string $logType,
        string $module,
        string $action,
        ?string $targetType,
        ?int $targetId,
        int $status,
        string $message,
        array $requestData = [],
        ?string $traceId = null,
    ): void {
        if (! Schema::hasTable('user_log')) {
            return;
        }

        $actor = $request->user();
        $traceId = $traceId ?: ($request->header('X-Request-Id') ?: $request->header('trace_id'));

        self::insertAuditRow(
            $actor,
            $request->method(),
            $request->fullUrl(),
            $request->ip(),
            $request->userAgent(),
            $traceId,
            $logType,
            $module,
            $action,
            $targetType,
            $targetId,
            $status,
            $message,
            $requestData,
            null,
            null,
        );
    }

    /**
     * 登录 / 登出等业务：失败登录无 UserModel 实例时可传 account、real_name。
     */
    public static function insertAuthAudit(
        Request $request,
        ?UserModel $actor,
        string $action,
        int $status,
        string $message,
        array $requestData = [],
        ?string $account = null,
        ?string $realName = null,
    ): void {
        if (! Schema::hasTable('user_log')) {
            return;
        }

        $traceId = $request->header('X-Request-Id') ?: $request->header('trace_id');

        self::insertAuditRow(
            $actor,
            $request->method(),
            $request->fullUrl(),
            $request->ip(),
            $request->userAgent(),
            $traceId,
            'login',
            'user',
            $action,
            null,
            null,
            $status,
            $message,
            $requestData,
            $account,
            $realName,
        );
    }

    /**
     * 模型事件审计：before/after 写入 request_data（可与 insertFromRequest 并存）。
     *
     * @param  array<string, mixed>  $auditPayload 须可 json_encode
     */
    public static function insertForModelAudit(
        ?Request $request,
        string $logType,
        string $module,
        string $action,
        string $targetType,
        ?int $targetId,
        int $status,
        string $message,
        array $auditPayload,
    ): void {
        if (! Schema::hasTable('user_log')) {
            return;
        }

        $actor = $request?->user();
        $traceId = $request ? ($request->header('X-Request-Id') ?: $request->header('trace_id')) : null;

        self::insertAuditRow(
            $actor,
            $request?->method(),
            $request?->fullUrl(),
            $request?->ip(),
            $request?->userAgent(),
            $traceId,
            $logType,
            $module,
            $action,
            $targetType,
            $targetId,
            $status,
            $message,
            $auditPayload,
            null,
            null,
        );
    }

    /**
     * 后台日志列表统一查询（Blade / SPA 共用条件）。
     *
     * @param  array{
     *     q?: string,
     *     log_type?: string|null,
     *     target_type?: string|null,
     *     module?: string|null,
     *     action?: string|null,
     *     start_at?: string|null,
     *     end_at?: string|null,
     *     start_date?: string|null,
     *     end_date?: string|null,
     * }  $filters
     */
    public static function adminFilteredQuery(array $filters): Builder
    {
        $query = static::query()->orderByDesc('id');

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $like = '%'.addcslashes($keyword, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('account', 'like', $like)
                    ->orWhere('real_name', 'like', $like)
                    ->orWhere('url', 'like', $like)
                    ->orWhere('ip', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        }

        if (! empty($filters['log_type'])) {
            $query->where('log_type', $filters['log_type']);
        }
        if (! empty($filters['target_type'])) {
            $query->where('target_type', $filters['target_type']);
        }
        if (! empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        $startAt = $filters['start_at'] ?? null;
        $endAt = $filters['end_at'] ?? null;
        if ($startAt || $endAt) {
            $startTs = $startAt ? strtotime(str_replace('T', ' ', $startAt).':00') : null;
            $endTs = $endAt ? strtotime(str_replace('T', ' ', $endAt).':59') : null;
            if ($startTs !== null && $endTs !== null && $startTs > $endTs) {
                [$startTs, $endTs] = [$endTs, $startTs];
            }
            if ($startTs !== null) {
                $query->where('created_at', '>=', $startTs);
            }
            if ($endTs !== null) {
                $query->where('created_at', '<=', $endTs);
            }
        } else {
            $startDate = $filters['start_date'] ?? null;
            $endDate = $filters['end_date'] ?? null;
            if ($startDate || $endDate) {
                $startTs = $startDate ? strtotime($startDate.' 00:00:00') : null;
                $endTs = $endDate ? strtotime($endDate.' 23:59:59') : null;
                if ($startTs !== null && $endTs !== null && $startTs > $endTs) {
                    [$startTs, $endTs] = [$endTs, $startTs];
                }
                if ($startTs !== null) {
                    $query->where('created_at', '>=', $startTs);
                }
                if ($endTs !== null) {
                    $query->where('created_at', '<=', $endTs);
                }
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $requestData
     */
    private static function insertAuditRow(
        mixed $actor,
        ?string $method,
        ?string $url,
        ?string $ip,
        ?string $userAgent,
        ?string $traceId,
        string $logType,
        string $module,
        string $action,
        ?string $targetType,
        ?int $targetId,
        int $status,
        string $message,
        array $requestData,
        ?string $accountOverride,
        ?string $realNameOverride,
    ): void {
        DB::table('user_log')->insert([
            'user_id' => $actor?->id,
            'account' => $accountOverride ?? $actor?->account ?? null,
            'real_name' => $realNameOverride ?? $actor?->real_name ?? null,
            'log_type' => $logType,
            'module' => $module,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'method' => $method,
            'url' => $url !== null ? mb_substr($url, 0, 255) : null,
            'ip' => $ip !== null ? mb_substr($ip, 0, 50) : null,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'request_data' => $requestData !== [] ? json_encode($requestData, JSON_UNESCAPED_UNICODE) : null,
            'response_data' => null,
            'status' => $status,
            'message' => mb_substr($message, 0, 255),
            'trace_id' => $traceId !== null ? mb_substr($traceId, 0, 100) : null,
            'created_at' => time(),
        ]);
    }
}
