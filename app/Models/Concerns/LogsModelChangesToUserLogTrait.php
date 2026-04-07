<?php

namespace App\Models\Concerns;

use App\Models\UserLogModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * 将 Eloquent 模型的 created / updated / deleted 自动写入 user_log 审计表（表前缀来自 DB_TABLE_PREFIX）。
 * request_data 中带 source=model_audit、before、after、changed（更新时字段级旧/新值）。
 *
 * 排除敏感字段请在模型中重写 {@see userLogExcludedAttributes()}。
 * 不需记日志的保存请使用 saveQuietly() / withoutEvents()。
 */
trait LogsModelChangesToUserLogTrait
{
    /** 更新前：待对比的旧值（字段 => 值） */
    protected array $userLogPendingBefore = [];

    /** 删除前：快照（删除后模型仍在内存，用独立属性承接） */
    protected ?array $userLogDeleteSnapshot = null;

    public static function bootLogsModelChangesToUserLogTrait(): void
    {
        static::updating(function (Model $model) {
            if (! $model instanceof static || ! static::modelAuditTableReady()) {
                return;
            }
            $excluded = $model->userLogExcludedAttributes();
            $before = [];
            foreach (array_keys($model->getDirty()) as $key) {
                if (in_array($key, $excluded, true)) {
                    continue;
                }
                $before[$key] = $model->getOriginal($key);
            }
            $model->userLogPendingBefore = $before;
        });

        static::updated(function (Model $model) {
            if (! $model instanceof static || ! static::modelAuditTableReady()) {
                return;
            }
            $pending = $model->userLogPendingBefore;
            $model->userLogPendingBefore = [];
            if ($pending === []) {
                return;
            }
            $changed = [];
            foreach ($pending as $key => $old) {
                $new = $model->getAttribute($key);
                if ($old !== $new) {
                    $changed[$key] = ['old' => $old, 'new' => $new];
                }
            }
            if ($changed === []) {
                return;
            }
            $after = [];
            foreach (array_keys($changed) as $key) {
                $after[$key] = $model->getAttribute($key);
            }
            $before = array_intersect_key($pending, $changed);

            static::submitModelAuditLog(
                $model,
                'update',
                $before,
                $after,
                $changed
            );
        });

        static::created(function (Model $model) {
            if (! $model instanceof static || ! static::modelAuditTableReady()) {
                return;
            }
            $after = $model->userLogFilteredAttributes($model->getAttributes());
            if ($after === []) {
                return;
            }
            static::submitModelAuditLog($model, 'create', [], $after, $after);
        });

        static::deleting(function (Model $model) {
            if (! $model instanceof static || ! static::modelAuditTableReady()) {
                return;
            }
            $model->userLogDeleteSnapshot = $model->userLogFilteredAttributes($model->getAttributes());
        });

        static::deleted(function (Model $model) {
            if (! $model instanceof static || ! static::modelAuditTableReady()) {
                return;
            }
            $before = $model->userLogDeleteSnapshot ?? [];
            $model->userLogDeleteSnapshot = null;
            if ($before === []) {
                return;
            }
            static::submitModelAuditLog($model, 'delete', $before, [], []);
        });
    }

    protected static function modelAuditTableReady(): bool
    {
        return Schema::hasTable('user_log');
    }

    /**
     * 不落库审计的字段（如密码、令牌）。
     *
     * @return list<string>
     */
    protected function userLogExcludedAttributes(): array
    {
        return ['password'];
    }

    /**
     * 写入 user_log 时的 target_type、module（模型可覆盖）。
     *
     * @return array{target_type: string, module: string}
     */
    protected function userLogAuditTarget(): array
    {
        return [
            'target_type' => property_exists($this, 'userLogTargetType')
                ? (string) $this->userLogTargetType
                : 'model',
            'module' => property_exists($this, 'userLogModule')
                ? (string) $this->userLogModule
                : 'model',
        ];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    protected function userLogFilteredAttributes(array $attrs): array
    {
        $out = [];
        $excluded = $this->userLogExcludedAttributes();
        foreach ($attrs as $key => $val) {
            if (in_array($key, $excluded, true)) {
                continue;
            }
            $out[$key] = $val;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>|array<string, array{old: mixed, new: mixed}>  $changed
     */
    protected static function submitModelAuditLog(
        Model $model,
        string $action,
        array $before,
        array $after,
        array $changed,
    ): void {
        $cfg = $model->userLogAuditTarget();
        $message = static::buildModelAuditMessage($model, $action, $changed);
        $payload = [
            'source' => 'model_audit',
            'model' => class_basename($model),
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'changed' => $action === 'update' && $changed !== [] ? $changed : null,
            'context' => static::modelAuditRequestContext(),
        ];
        $req = static::resolveRequestForModelAudit();
        UserLogModel::insertForModelAudit(
            $req,
            'operation',
            $cfg['module'],
            $action,
            $cfg['target_type'],
            $model->getKey() !== null ? (int) $model->getKey() : null,
            1,
            $message,
            $payload,
        );
    }

    protected static function resolveRequestForModelAudit(): ?Request
    {
        if (! function_exists('request')) {
            return null;
        }
        $r = request();
        if (! $r instanceof Request) {
            return null;
        }

        return $r;
    }

    /**
     * 与模型无映射的请求补充信息（如状态切换备注）。
     *
     * @return array<string, mixed>|null
     */
    protected static function modelAuditRequestContext(): ?array
    {
        $req = static::resolveRequestForModelAudit();
        if ($req === null) {
            return null;
        }
        $ctx = [];
        if ($req->has('status_remark')) {
            $t = trim((string) $req->input('status_remark', ''));
            if ($t !== '') {
                $ctx['status_remark'] = $t;
            }
        }

        return $ctx === [] ? null : $ctx;
    }

    /**
     * @param  array<string, mixed>|array<string, array{old: mixed, new: mixed}>  $changed
     */
    protected static function buildModelAuditMessage(Model $model, string $action, array $changed): string
    {
        if ($action === 'create') {
            return '用户已创建（含新建字段快照）。';
        }
        if ($action === 'delete') {
            return '用户已删除（删除前字段快照已记录）。';
        }
        $keys = array_keys($changed);
        $label = '用户资料已变更：'.implode('、', $keys);
        $req = static::resolveRequestForModelAudit();
        if ($req !== null && isset($changed['status']) && $req->has('status_remark')) {
            $remark = trim((string) $req->input('status_remark', ''));
            if ($remark !== '') {
                $label .= '；备注：'.$remark;
            }
        }

        return mb_substr($label, 0, 255);
    }
}
