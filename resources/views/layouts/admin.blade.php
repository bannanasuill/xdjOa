<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '后台') — {{ $adminSiteName ?? '洗多家后台' }}</title>
    @php
        $faviconIcoHref = \App\Models\SystemSettingModel::faviconIcoHref();
        $adminIconHref = $adminFaviconHref ?? \App\Models\SystemSettingModel::resolvedFaviconHref();
    @endphp
    <link rel="icon" type="image/png" href="{{ $faviconIcoHref }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ $adminIconHref }}" sizes="32x32">
    <link rel="shortcut icon" type="image/png" href="{{ $faviconIcoHref }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin-layout.css') }}">
    <script>
        (function () {
            var k = 'xdj_oa_admin_theme';
            try {
                var t = localStorage.getItem(k);
                if (t !== 'light' && t !== 'dark') {
                    var h = new Date().getHours();
                    t = (h >= 6 && h <= 18) ? 'light' : 'dark';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
            try {
                if (localStorage.getItem('xdj_oa_admin_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('admin-sidebar-collapsed');
                }
            } catch (e) { /* ignore */ }
        })();
    </script>
    @stack('head')
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="主导航">
            <div class="admin-sidebar__brand">
                <div class="admin-sidebar__brand-text">
                    <p class="admin-sidebar__brand-title">洗多家管理后台</p>
                </div>
                <button type="button" class="admin-sidebar__collapse-btn" id="admin-sidebar-toggle" aria-expanded="true" aria-controls="admin-sidebar-nav" title="收起侧栏">
                    <svg class="admin-sidebar__collapse-icon admin-sidebar__collapse-icon--shrink" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                    </svg>
                    <svg class="admin-sidebar__collapse-icon admin-sidebar__collapse-icon--expand" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>
            <nav class="admin-sidebar__nav" id="admin-sidebar-nav">
                <a href="{{ route('admin.dashboard') }}" class="admin-sidebar__link @if(request()->routeIs('admin.dashboard')) is-active @endif" title="首页">
                    <svg class="admin-sidebar__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span class="admin-sidebar__link-text">首页</span>
                </a>
                @foreach ($adminNavMenus as $menu)
                    @if ($menu->children->isNotEmpty())
                        @php
                            $menuBranchOpen = $adminMenuBranchIsActive($menu);
                        @endphp
                        @php
                            $anyChildPathActive = $menu->children->contains(fn ($c) => $adminMenuPathIsActive($c->path));
                        @endphp
                        <div class="admin-sidebar__group {{ $menuBranchOpen ? 'is-open' : '' }}" data-menu-id="{{ $menu->id }}">
                            <a
                                href="{{ $adminMenuUrl($menu->path) }}"
                                class="admin-sidebar__link admin-sidebar__link--parent"
                                title="{{ $menu->name }}"
                            >
                                @include('layouts.partials.admin-menu-icon', ['icon' => $menu->icon])
                                <span class="admin-sidebar__link-text">{{ $menu->name }}</span>
                            </a>
                            <div class="admin-sidebar__sub {{ $menuBranchOpen ? '' : 'admin-sidebar__sub--collapsed' }}">
                                @foreach ($menu->children as $child)
                                    @php
                                        $childActive = $adminMenuPathIsActive($child->path);
                                        if (! $childActive && ! $anyChildPathActive && $loop->first && $adminMenuPathIsActive($menu->path)) {
                                            $childActive = true;
                                        }
                                    @endphp
                                    <a
                                        href="{{ $adminMenuUrl($child->path) }}"
                                        class="admin-sidebar__link admin-sidebar__link--sub {{ $childActive ? 'is-active' : '' }}"
                                        title="{{ $child->name }}"
                                    >
                                        @include('layouts.partials.admin-menu-icon', ['icon' => $child->icon])
                                        <span class="admin-sidebar__link-text">{{ $child->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @php
                            $menuHref = $adminMenuUrl($menu->path);
                            $menuActive = $adminMenuPathIsActive($menu->path);
                        @endphp
                        <a
                            href="{{ $menuHref }}"
                            class="admin-sidebar__link {{ $menuActive ? 'is-active' : '' }}"
                            title="{{ $menu->name }}"
                        >
                            @include('layouts.partials.admin-menu-icon', ['icon' => $menu->icon])
                            <span class="admin-sidebar__link-text">{{ $menu->name }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>
            <div class="admin-sidebar__footer">
                <form method="post" action="{{ route('logout') }}" class="admin-sidebar__logout-form">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--logout admin-btn--sidebar-footer" title="退出登录">
                        <svg class="admin-btn__icon-logout" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                        </svg>
                        <span class="admin-btn__label">退出登录</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <nav class="admin-breadcrumb admin-breadcrumb--title-area" aria-label="面包屑导航">
                    <ol class="admin-breadcrumb__list">
                        @foreach ($adminBreadcrumbs as $crumb)
                            <li class="admin-breadcrumb__item">
                                @if (! empty($crumb['url']))
                                    <a href="{{ $crumb['url'] }}" class="admin-breadcrumb__link">{{ $crumb['label'] }}</a>
                                @else
                                    <span class="admin-breadcrumb__current" aria-current="page">{{ $crumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
                <h1 class="admin-sr-only">@yield('page-title', '概览')</h1>
                <div class="admin-header__toolbar">
                    <time id="admin-header-time" class="admin-header__time" datetime="" aria-live="polite">—</time>
                    <span class="admin-header__user" title="{{ auth()->user()->real_name ?: auth()->user()->account }}">
                        <span class="admin-header__user-text">当前用户：<strong class="admin-header__user-name">{{ auth()->user()->real_name ?: auth()->user()->account }}</strong></span>
                    </span>
                    <button type="button" class="admin-btn admin-btn--theme admin-btn--header" id="admin-theme-toggle" title="切换白天/夜晚模式" aria-label="切换主题">
                        <svg class="admin-icon-sun" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                        </svg>
                        <svg class="admin-icon-moon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>
                </div>
            </header>
            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="{{ asset('js/admin-theme.js') }}"></script>
    @stack('scripts')
</body>
</html>
