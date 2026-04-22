@extends('layouts.app')

@section('content')
    <div class="row justify-content-center align-items-center" style="min-height: 70vh;">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h4 mb-3 text-center text-primary fw-semibold">ログイン</h1>
                    <p class="text-muted small text-center mb-4">
                        毎日の勤怠・配置管理システムにサインインしてください。
                    </p>

                    <form method="POST" action="{{ route('login.attempt') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="login_id" class="form-label">ログインID</label>
                            <input
                                id="login_id"
                                type="text"
                                name="login_id"
                                value="{{ old('login_id') }}"
                                class="form-control @error('login_id') is-invalid @enderror"
                                autofocus
                                required
                            >
                            @error('login_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">パスワード</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                {{-- 未チェック時も remember=0 を送る（バリデーションエラー後の表示ずれ防止） --}}
                                <input type="hidden" name="remember" value="0">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" {{ old('remember', '1') === '1' ? 'checked' : '' }}>
                                <label class="form-check-label small" for="remember">
                                    ログイン状態を保持する
                                    <span class="d-block text-muted mt-1" style="font-size: 0.7rem;">スマホでタブを閉じたあとも、チェック時は再ログインしにくくなります。</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            ログイン
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

