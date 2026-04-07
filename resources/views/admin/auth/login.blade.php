<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 — {{ $adminSiteName ?? '洗多家后台' }}</title>
    <link rel="icon" href="{{ $adminFaviconHref ?? asset('favicon.ico') }}" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin-login.css') }}">
</head>
<body>
    <div class="bg-grid" aria-hidden="true"></div>
    <div class="card">
        <div class="brand">
            <h1>{{ $adminSiteName ?? '洗多家后台' }}</h1>
            <p>请使用账号和密码登录</p>
        </div>

        @if ($errors->any())
            <div class="error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form id="login-form" method="post" action="{{ url('/login') }}" novalidate>
            @csrf
            <div class="field">
                <label for="account">账号</label>
                <input
                    id="account"
                    name="account"
                    type="text"
                    value="{{ old('account') }}"
                    required
                    autocomplete="username"
                    autofocus
                >
            </div>
            <div class="field">
                <label for="password">密码</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                >
            </div>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html>
