@extends('layouts.admin')

@section('title', '编辑用户')
@section('page-title', '编辑用户')

@section('content')
    <div class="admin-panel">
        @if ($errors->any())
            <div class="admin-alert admin-alert--error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="post" action="{{ route('admin.users.update', ['adminUser' => $user]) }}" class="admin-form">
            @csrf
            @method('PUT')

            <div class="admin-form__row">
                <label class="admin-form__label" for="account">账号 <span class="admin-form__required" aria-hidden="true">*</span></label>
                <input class="admin-form__input" type="text" id="account" name="account" value="{{ old('account', $user->account) }}" required maxlength="50">
            </div>
            <div class="admin-form__row">
                <label class="admin-form__label" for="real_name">姓名 <span class="admin-form__required" aria-hidden="true">*</span></label>
                <input class="admin-form__input" type="text" id="real_name" name="real_name" value="{{ old('real_name', $user->real_name) }}" maxlength="50">
            </div>
            <div class="admin-form__row">
                <label class="admin-form__label" for="password">密码</label>
                <input class="admin-form__input" type="text" id="password" name="password" value="{{ old('password') }}" autocomplete="off" placeholder="不修改请留空">
            </div>
            <div class="admin-form__row">
                <label class="admin-form__label" for="phone">手机</label>
                <input class="admin-form__input" type="text" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20">
            </div>
            <div class="admin-form__row">
                <label class="admin-form__label" for="email">邮箱</label>
                <input class="admin-form__input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="100">
            </div>

            <div class="admin-form__actions">
                <a href="{{ route('admin.users.index') }}" class="admin-btn">返回列表</a>
                <button type="submit" class="admin-btn admin-btn--primary">保存</button>
            </div>
        </form>
    </div>
@endsection
