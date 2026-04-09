<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $adminSiteName ?? '洗多家后台' }} — 后台</title>
    @php
        $faviconIcoHref = \App\Models\SystemSettingModel::faviconIcoHref();
        $adminIconHref = $adminFaviconHref ?? \App\Models\SystemSettingModel::resolvedFaviconHref();
    @endphp
    {{-- 根路径 /favicon.ico 便于浏览器默认抓取；与 logo.png 同源并带版本号避免强缓存旧图标 --}}
    <link rel="icon" type="image/png" href="{{ $faviconIcoHref }}" sizes="any">
    <link rel="icon" type="image/png" href="{{ $adminIconHref }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ $adminIconHref }}">
    <script>
        window.__ADMIN_SITE_NAME__ = @json($adminSiteName ?? '洗多家后台');
        window.__ADMIN_FAVICON__ = @json($adminIconHref);
        window.__BAIDU_MAP_BROWSER_AK__ = @json((string) config('services.baidu_map.browser_ak', ''));
    </script>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('admin_theme');
                var theme = (saved === 'dark' || saved === 'light')
                    ? saved
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                window.__ADMIN_THEME__ = theme;
                document.documentElement.setAttribute('data-admin-theme', theme);
                document.documentElement.style.background = theme === 'dark' ? '#0b1220' : '#f5f7fa';
            } catch (e) {}
        })();
    </script>
    @vite(['resources/js/admin/main.js'])
</head>
<body style="margin:0; background: inherit;">
    <div id="admin-app"></div>
</body>
</html>

