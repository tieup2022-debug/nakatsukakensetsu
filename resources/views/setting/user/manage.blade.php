@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">ユーザー管理</h1>
            <div class="text-muted small">ユーザーの追加・編集・削除を行います。</div>
        </div>
        <div>
            <a class="btn btn-primary btn-sm" href="{{ route('setting.user.create') }}">追加</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <a class="btn btn-primary" href="{{ route('setting.user.list') }}">一覧へ</a>
        </div>
    </div>
@endsection

