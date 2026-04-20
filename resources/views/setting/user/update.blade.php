@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">ユーザー編集</h1>
            <div class="text-muted small">ユーザー情報を更新します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.user.update.submit') }}">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user_data->id }}">

                <div class="mb-3">
                    <label class="form-label small text-muted">ユーザー名</label>
                    <input type="text" name="user_name" class="form-control" value="{{ $user_data->user_name }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">ログインID</label>
                    <input type="text" name="login_id" class="form-control" value="{{ $user_data->login_id }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">権限</label>
                    <input type="number" name="permission" class="form-control" value="{{ $user_data->permission }}" required>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('setting.user.list') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
@endsection

