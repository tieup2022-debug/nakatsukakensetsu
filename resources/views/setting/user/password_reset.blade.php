@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">パスワード再設定</h1>
            <div class="text-muted small">対象ユーザーのログインパスワードを上書きします。</div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="mb-4 p-3 bg-light rounded border">
                <div class="small text-muted mb-1">対象ユーザー</div>
                <div class="fw-semibold">{{ $target->user_name }}</div>
                <div class="text-muted small">ログインID: {{ $target->login_id }}</div>
            </div>

            <p class="text-muted small mb-3">
                再設定後は、対象ユーザーに新しいパスワードを安全な方法で伝えてください。
                ログイン状態を保持している端末のセッションは、次回アクセス時に再ログインが必要になる場合があります。
            </p>

            @if ($errors->any())
                <div class="alert alert-danger mb-3">
                    <ul class="mb-0 small">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('setting.user.password.reset') }}" onsubmit="return confirm('このユーザーのパスワードを上書きします。よろしいですか？');">
                @csrf
                <input type="hidden" name="user_id" value="{{ $target->id }}">

                <div class="mb-3">
                    <label class="form-label small text-muted">新しいパスワード（4文字以上）</label>
                    <input type="password" name="password1" class="form-control" required autocomplete="new-password" minlength="4">
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">新しいパスワード（確認）</label>
                    <input type="password" name="password2" class="form-control" required autocomplete="new-password" minlength="4">
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('setting.user.list') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-warning">再設定する</button>
                </div>
            </form>
        </div>
    </div>
@endsection
