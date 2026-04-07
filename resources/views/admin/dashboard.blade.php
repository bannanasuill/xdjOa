@extends('layouts.admin')

@section('title', '后台首页')
@section('page-title', '首页')

@section('content')
    <section class="admin-panel">
        <h2>欢迎回来</h2>
        <p>您已成功登录后台。后续可在此接入报销、审批等模块。</p>
        <p class="admin-meta">
            当前用户：<strong>{{ auth()->user()->real_name ?: auth()->user()->account }}</strong>
        </p>
    </section>
@endsection
