@extends('layouts.admin')

@section('title', '用户')
@section('page-title', '用户')

@section('content')
    <div class="admin-panel admin-panel--flush">
        <div class="admin-panel__head">
            <form method="get" action="{{ route('admin.users.index') }}" class="admin-panel__search" role="search">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <label class="admin-sr-only" for="admin-users-q">搜索账号或姓名</label>
                <input
                    type="search"
                    name="q"
                    id="admin-users-q"
                    value="{{ $searchQuery }}"
                    placeholder="搜索账号或姓名"
                    class="admin-form__input admin-panel__search-input"
                    autocomplete="off"
                    maxlength="100"
                >
                <label class="admin-sr-only" for="admin-users-role">按角色筛选</label>
                <select name="role_id" id="admin-users-role" class="admin-form__input admin-panel__search-input" style="max-width:12rem;">
                    <option value="">全部角色</option>
                    @foreach ($roleFilterOptions as $r)
                        <option value="{{ $r['id'] }}" @selected((int) ($filterRoleId ?? 0) === (int) ($r['id'] ?? 0))>{{ $r['name'] }}{{ !empty($r['is_system']) ? '（系统）' : '' }}</option>
                    @endforeach
                </select>
                <button type="submit" class="admin-btn">搜索</button>
                @php
                    $hasListFilters = $searchQuery !== '' || !empty($filterRoleId);
                @endphp
                @if ($hasListFilters)
                    <a href="{{ route('admin.users.index', ['per_page' => $perPage]) }}" class="admin-btn admin-btn--muted">清除</a>
                @endif
            </form>
            <a href="{{ route('admin.users.create') }}" class="admin-btn admin-btn--primary">新增用户</a>
        </div>
        @if ($errors->has('q'))
            <p class="admin-alert admin-alert--error admin-panel__alert" role="alert">{{ $errors->first('q') }}</p>
        @endif
        @if ($errors->has('role_id'))
            <p class="admin-alert admin-alert--error admin-panel__alert" role="alert">{{ $errors->first('role_id') }}</p>
        @endif
        @if (session('success'))
            <p id="admin-success-alert" class="admin-alert admin-alert--success" role="status">{{ session('success') }}</p>
        @endif
        @if ($errors->has('delete'))
            <p class="admin-alert admin-alert--error" role="alert">{{ $errors->first('delete') }}</p>
        @endif
        @if ($errors->has('status_remark'))
            <p class="admin-alert admin-alert--error" role="alert">{{ $errors->first('status_remark') }}</p>
        @endif

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">姓名</th>
                        <th scope="col">账号</th>
                        <th scope="col">手机</th>
                        <th scope="col">状态</th>
                        <th scope="col">创建时间</th>
                        <th scope="col" class="admin-table__col-actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->real_name ?: '—' }}</td>
                            <td>{{ $user->account }}</td>
                            <td>{{ $user->phone ?: '—' }}</td>
                            <td>
                                @if ($user->isSuperAdminAccount())
                                    @if ((int) $user->status === 1)
                                        <span class="admin-badge admin-badge--ok">正常</span>
                                    @else
                                        <span class="admin-badge admin-badge--off">禁用</span>
                                    @endif
                                @else
                                    @php
                                        $isEnabled = (int) $user->status === 1;
                                    @endphp
                                    <div class="admin-status-switch">
                                        <input
                                            type="checkbox"
                                            class="admin-status-switch__input"
                                            id="admin-status-switch-{{ $user->id }}"
                                            data-user-id="{{ $user->id }}"
                                            data-current-status="{{ (int) $user->status }}"
                                            {{ $isEnabled ? 'checked' : '' }}
                                        >
                                        <label
                                            for="admin-status-switch-{{ $user->id }}"
                                            class="admin-switch"
                                            title="点击切换状态"
                                        ></label>
                                    </div>
                                    <form method="post" action="{{ route('admin.users.status', ['adminUser' => $user]) }}" class="admin-status-form" data-user-id="{{ $user->id }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ (int) $user->status }}">
                                        <input type="hidden" name="status_remark" value="">
                                    </form>
                                @endif
                            </td>
                            <td class="admin-table__num">
                                @if ($user->created_at)
                                    {{ date('Y-m-d H:i:s', (int) $user->created_at) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="admin-table__actions">
                                @if ($user->isSuperAdminAccount())
                                    <span class="admin-table__muted" title="超级管理员">—</span>
                                @else
                                    <a href="{{ route('admin.users.edit', ['adminUser' => $user]) }}" class="admin-btn admin-btn--muted" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">编辑</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table__empty">
                                @if ($searchQuery !== '')
                                    未找到匹配的用户
                                @else
                                    暂无用户
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->total() > 0)
            <div class="admin-panel__pager">
                <p class="admin-panel__pager-meta">
                    共 <strong>{{ $users->total() }}</strong> 条，第 <strong>{{ $users->firstItem() }}</strong>–<strong>{{ $users->lastItem() }}</strong> 条
                </p>
                <div class="admin-panel__pager-actions">
                    <form method="get" action="{{ route('admin.users.index') }}" class="admin-panel__per-page">
                        @if ($searchQuery !== '')
                            <input type="hidden" name="q" value="{{ $searchQuery }}">
                        @endif
                        <label for="admin-users-per-page" class="admin-panel__per-page-label">每页</label>
                        <select name="per_page" id="admin-users-per-page" class="admin-form__select admin-panel__per-page-select" onchange="this.form.submit()">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }} 条</option>
                            @endforeach
                        </select>
                    </form>
                    {{ $users->onEachSide(1)->links('vendor.pagination.admin') }}
                </div>
            </div>
        @endif
    </div>
    <div class="admin-modal" id="admin-status-remark-modal" aria-hidden="true">
        <div class="admin-modal__backdrop" data-close="1"></div>
        <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-label="状态备注">
            <div class="admin-modal__body">
                <textarea
                    id="admin-status-remark-textarea"
                    class="admin-form__textarea"
                    rows="4"
                    maxlength="500"
                    placeholder="请输入原因备注"
                ></textarea>
            </div>
            <div class="admin-modal__actions">
                <button type="button" class="admin-btn" id="admin-status-remark-cancel">取消</button>
                <button type="button" class="admin-btn admin-btn--primary" id="admin-status-remark-confirm">提交</button>
            </div>
        </div>
    </div>
    @push('scripts')
        <script>
            (function () {
                var toast = document.getElementById('admin-success-alert');
                if (toast) {
                    setTimeout(function () {
                        toast.style.transition = 'opacity 0.35s ease';
                        toast.style.opacity = '0';
                    }, 3000);
                    setTimeout(function () {
                        if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
                    }, 3700);
                }

                var modal = document.getElementById('admin-status-remark-modal');
                var backdrop = modal ? modal.querySelector('[data-close="1"]') : null;
                var ta = modal ? document.getElementById('admin-status-remark-textarea') : null;
                var cancelBtn = modal ? document.getElementById('admin-status-remark-cancel') : null;
                var confirmBtn = modal ? document.getElementById('admin-status-remark-confirm') : null;

                var activeForm = null;
                var activeToggle = null;
                var activeTargetStatus = null;

                function setModalText(targetStatus) {
                    if (!modal || !ta) return;
                    if (String(targetStatus) === '0') {
                        ta.placeholder = '请输入禁用原因';
                    } else {
                        ta.placeholder = '请输入启用备注';
                    }
                }

                function openModal(form, toggle, targetStatus) {
                    activeForm = form;
                    activeToggle = toggle;
                    activeTargetStatus = targetStatus;
                    if (!modal || !ta) return;
                    ta.value = '';
                    setModalText(targetStatus);
                    modal.classList.add('is-open');
                    modal.setAttribute('aria-hidden', 'false');
                    setTimeout(function () {
                        ta.focus();
                    }, 50);
                }

                function closeModal() {
                    if (!modal) return;
                    modal.classList.remove('is-open');
                    modal.setAttribute('aria-hidden', 'true');
                    activeForm = null;
                    activeToggle = null;
                    activeTargetStatus = null;
                }

                if (modal && backdrop) {
                    backdrop.addEventListener('click', function () {
                        if (activeToggle) {
                            var cur = String(activeToggle.getAttribute('data-current-status')) === '1';
                            activeToggle.checked = cur;
                        }
                        closeModal();
                    });
                }

                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function () {
                        if (activeToggle) {
                            var cur = String(activeToggle.getAttribute('data-current-status')) === '1';
                            activeToggle.checked = cur;
                        }
                        closeModal();
                    });
                }

                if (confirmBtn) {
                    confirmBtn.addEventListener('click', function () {
                        if (!activeForm || !ta) return;
                        var remark = String(ta.value || '').trim();
                        if (remark === '') {
                            ta.focus();
                            return;
                        }

                        activeForm.querySelector('input[name="status"]').value = String(activeTargetStatus);
                        activeForm.querySelector('input[name="status_remark"]').value = remark;

                        // 注意：closeModal() 会把 activeForm 置空
                        var formToSubmit = activeForm;
                        closeModal();
                        formToSubmit.submit();
                    });
                }

                document.querySelectorAll('.admin-status-switch__input').forEach(function (toggle) {
                    toggle.addEventListener('change', function () {
                        var userId = String(toggle.getAttribute('data-user-id') || '');
                        var form = document.querySelector('.admin-status-form[data-user-id="' + userId + '"]');
                        if (!form) return;

                        var targetStatus = toggle.checked ? '1' : '0';
                        openModal(form, toggle, targetStatus);
                    });
                });
            })();
        </script>
    @endpush
@endsection
