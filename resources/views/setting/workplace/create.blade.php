@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">現場追加</h1>
            <div class="text-muted small">現場名を登録します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    @if (!is_null($result))
        <div class="alert {{ $result ? 'alert-success' : 'alert-danger' }} mb-3">
            {{ $result ? '登録しました' : '登録に失敗しました' }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.workplace.create.submit') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label small text-muted">現場名</label>
                    <input type="text" name="workplace_name" class="form-control" required>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('setting.workplace.manage') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-primary">登録</button>
                </div>
            </form>
        </div>
    </div>
@endsection

