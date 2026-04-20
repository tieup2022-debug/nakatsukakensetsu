@extends('layouts.app')

@section('content')
    <div class="mb-3">
        <h1 class="h4 mb-1 fw-semibold">ユーザー追加</h1>
    </div>

    @if (!is_null($result))
        <div class="alert {{ $result ? 'alert-success' : 'alert-danger' }} mb-3">
            {{ $result ? '登録しました' : '登録に失敗しました' }}
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

        <div class="mb-4 small text-danger fw-semibold">
            初期パスワードは「0000」です。
        </div>

        <div class="text-center">
            <button class="btn btn-primary px-4" type="submit">登録</button>
        </div>
    </form>
@endsection

