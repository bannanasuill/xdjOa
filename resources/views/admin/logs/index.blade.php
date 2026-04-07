@extends('layouts.admin')

@section('title', '日志管理')
@section('page-title', '日志管理')

@section('content')
    <div class="admin-panel admin-panel--flush">
        <div class="admin-panel__head">
            <form method="get" action="{{ route('admin.logs.index') }}" class="admin-panel__search admin-panel__search--logs" role="search">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <label class="admin-sr-only" for="admin-logs-q">搜索账号/姓名/URL</label>
                <input
                    type="search"
                    name="q"
                    id="admin-logs-q"
                    value="{{ $searchQuery }}"
                    placeholder="搜索账号/姓名/URL"
                    class="admin-form__input admin-panel__search-input"
                    autocomplete="off"
                    maxlength="100"
                >

                <label class="admin-sr-only" for="admin-logs-type">日志类型</label>
                <select name="log_type" id="admin-logs-type" class="admin-form__select" style="min-width: 6.5rem; max-width: 9rem;">
                    <option value="" @selected(empty($filters['log_type']))>全部类型</option>
                    @foreach (($logTypeOptions ?? []) as $t)
                        <option value="{{ $t }}" @selected(($filters['log_type'] ?? null) === $t)>{{ $t }}</option>
                    @endforeach
                </select>

                <label class="admin-sr-only" for="admin-logs-tagtype">对象类型</label>
                <select name="tagtype" id="admin-logs-tagtype" class="admin-form__select" style="min-width: 7.5rem; max-width: 11rem;">
                    <option value="" @selected(empty($filters['tagtype']))>全部对象类型</option>
                    @foreach (($tagTypeOptions ?? []) as $t)
                        <option value="{{ $t }}" @selected(($filters['tagtype'] ?? null) === $t)>{{ $t }}</option>
                    @endforeach
                </select>

                <label class="admin-sr-only" for="admin-logs-module">模块</label>
                <select name="module" id="admin-logs-module" class="admin-form__select" style="min-width: 6.5rem; max-width: 9rem;">
                    <option value="" @selected(empty($filters['module']))>全部模块</option>
                    @foreach (($moduleOptions ?? []) as $m)
                        <option value="{{ $m }}" @selected(($filters['module'] ?? null) === $m)>{{ $m }}</option>
                    @endforeach
                </select>

                <label class="admin-sr-only" for="admin-logs-action">操作类型</label>
                <select name="action" id="admin-logs-action" class="admin-form__select" style="min-width: 6.5rem; max-width: 9rem;">
                    <option value="" @selected(empty($filters['action']))>全部操作</option>
                    @foreach (($actionOptions ?? []) as $a)
                        <option value="{{ $a }}" @selected(($filters['action'] ?? null) === $a)>{{ $a }}</option>
                    @endforeach
                </select>

                <label class="admin-sr-only" for="admin-logs-start-at">开始时间</label>
                <input
                    type="datetime-local"
                    name="start_at"
                    id="admin-logs-start-at"
                    value="{{ $filters['start_at'] ?? '' }}"
                    class="admin-form__input"
                    style="min-width: 11.5rem; max-width: 12.5rem;"
                >

                <label class="admin-sr-only" for="admin-logs-end-at">结束时间</label>
                <input
                    type="datetime-local"
                    name="end_at"
                    id="admin-logs-end-at"
                    value="{{ $filters['end_at'] ?? '' }}"
                    class="admin-form__input"
                    style="min-width: 11.5rem; max-width: 12.5rem;"
                >

                <button type="submit" class="admin-btn">搜索</button>
                @if ($searchQuery !== '' || ! empty($filters['log_type']) || ! empty($filters['tagtype']) || ! empty($filters['module']) || ! empty($filters['action']) || ! empty($filters['start_at']) || ! empty($filters['end_at']))
                    <a href="{{ route('admin.logs.index', ['per_page' => $perPage]) }}" class="admin-btn admin-btn--muted">清除</a>
                @endif
            </form>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">账号</th>
                        <th scope="col">操作人</th>
                        <th scope="col">类型</th>
                        <th scope="col">模块</th>
                        <th scope="col">操作</th>
                        <th scope="col">对象</th>
                        <th scope="col">状态</th>
                        <th scope="col">消息</th>
                        <th scope="col">时间</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $log->account ?: '—' }}</td>
                            <td>{{ $log->real_name ?: '—' }}</td>
                            <td>{{ $log->log_type ?: '—' }}</td>
                            <td>{{ $log->module ?: '—' }}</td>
                            <td>{{ $log->action ?: '—' }}</td>
                            <td>
                                {{ $log->target_type ?: '—' }}
                                @if (! empty($log->target_id))
                                    (#{{ $log->target_id }})
                                @endif
                            </td>
                            <td>
                                @if ((int) $log->status === 1)
                                    <span class="admin-badge admin-badge--ok">成功</span>
                                @else
                                    <span class="admin-badge admin-badge--off">失败</span>
                                @endif
                            </td>
                            <td title="{{ $log->message ?: '' }}" style="max-width: 16rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $log->message ?: '—' }}
                            </td>
                            <td class="admin-table__num">
                                @if ($log->created_at)
                                    {{ date('Y-m-d H:i:s', (int) $log->created_at) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $log->ip ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="admin-table__empty">暂无用户日志</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->total() > 0)
            <div class="admin-panel__pager">
                <p class="admin-panel__pager-meta">
                    共 <strong>{{ $logs->total() }}</strong> 条，第 <strong>{{ $logs->firstItem() }}</strong>–<strong>{{ $logs->lastItem() }}</strong> 条
                </p>
                <div class="admin-panel__pager-actions">
                    <form method="get" action="{{ route('admin.logs.index') }}" class="admin-panel__per-page">
                        <input type="hidden" name="q" value="{{ $searchQuery }}">
                        <input type="hidden" name="log_type" value="{{ $filters['log_type'] ?? '' }}">
                        <input type="hidden" name="tagtype" value="{{ $filters['tagtype'] ?? '' }}">
                        <input type="hidden" name="module" value="{{ $filters['module'] ?? '' }}">
                        <input type="hidden" name="action" value="{{ $filters['action'] ?? '' }}">
                        <input type="hidden" name="start_at" value="{{ $filters['start_at'] ?? '' }}">
                        <input type="hidden" name="end_at" value="{{ $filters['end_at'] ?? '' }}">
                        <label for="admin-logs-per-page" class="admin-panel__per-page-label">每页</label>
                        <select name="per_page" id="admin-logs-per-page" class="admin-form__select admin-panel__per-page-select" onchange="this.form.submit()">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} 条</option>
                            @endforeach
                        </select>
                    </form>
                    {{ $logs->onEachSide(1)->links('vendor.pagination.admin') }}
                </div>
            </div>
        @endif
    </div>
@endsection

