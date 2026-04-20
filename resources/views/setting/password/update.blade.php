@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">パスワード変更</h1>
            <div class="text-muted small">ログインパスワードを更新します。</div>
        </div>
    </div>

    @if (!is_null($result))
        <div class="alert {{ $result ? 'alert-success' : 'alert-danger' }} mb-3">
            {{ $result ? '更新しました' : '更新に失敗しました' }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.account.password.update.submit') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label small text-muted">新しいパスワード</label>
                    <input type="password" name="password1" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">新しいパスワード（確認）</label>
                    <input type="password" name="password2" class="form-control" required>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('top.setting') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
@endsection

