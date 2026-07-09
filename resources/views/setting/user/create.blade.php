@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1 fw-semibold">ユーザー追加</h1>
    </div>

    @if (!is_null($result))
        <div class="alert {{ $result ? 'alert-success' : 'alert-danger' }} mb-3">
            {{ $result ? '登録しました' : '登録に失敗しました（ログインIDが重複している可能性があります）' }}
        </div>
    @endif

    @if (!empty($initialPassword))
        <div class="alert alert-warning mb-3">
            <div class="fw-semibold mb-1">初期パスワードを本人にお伝えください</div>
            <div class="small mb-2">この画面を離れると再表示できません。本人には安全な方法で伝え、初回ログイン後の変更を案内してください。</div>
            <div class="d-flex align-items-center gap-2">
                <code class="fs-5 fw-bold user-select-all">{{ $initialPassword }}</code>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('setting.user.create.submit') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label small">ユーザー名を入力</label>
            <input type="text" name="user_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label small">ログインIDを入力</label>
            <input type="text" name="login_id" class="form-control" required>
        </div>

        <div class="mb-2 small">
            <label class="me-3"><input type="radio" name="permission" value="1" required> マスター</label>
            <label class="me-3"><input type="radio" name="permission" value="2" required> 担当者</label>
            <label><input type="radio" name="permission" value="3" required> 利用者</label>
        </div>

        <div class="mb-4 small text-muted">
            初期パスワードは登録時にランダム自動発行され、登録後にこの画面へ表示されます（英数字10文字）。
        </div>

        <div class="text-center">
            <button class="btn btn-primary px-4" type="submit">登録</button>
        </div>
    </form>
@endsection

