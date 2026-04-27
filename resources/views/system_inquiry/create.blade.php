@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">お問い合わせ</h1>
            <div class="text-muted small">
                システムの不具合や追加要望をご連絡ください。送信者名と送信日時は送信時に自動で記録されます。
            </div>
        </div>
    </div>

    @if (!empty($current_user_name))
        <p class="small text-muted mb-3">ログインユーザー: {{ $current_user_name }}</p>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="post" action="{{ route('inquiry.store') }}">
                @csrf
                <div class="mb-3">
                    <label for="inquiry_body" class="form-label fw-semibold">内容</label>
                    <textarea
                        id="inquiry_body"
                        name="body"
                        class="form-control @error('body') is-invalid @enderror"
                        rows="10"
                        required
                        maxlength="10000"
                        placeholder="不具合の状況、再現手順、追加したい機能の概要など"
                    >{{ old('body') }}</textarea>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">10,000文字以内</div>
                </div>
                <button type="submit" class="btn btn-primary">送信する</button>
                <a href="{{ route('top.assignment') }}" class="btn btn-outline-secondary ms-2">戻る</a>
            </form>
        </div>
    </div>
@endsection
