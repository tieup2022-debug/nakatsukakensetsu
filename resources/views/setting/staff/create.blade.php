@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">スタッフ登録</h1>
            <div class="text-muted small">社員名と種別を登録します。</div>
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
            <form method="POST" action="{{ route('setting.staff.create.submit') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label small text-muted">社員名</label>
                    <input type="text" name="staff_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">種別</label>
                    <select name="staff_type" class="form-select" required>
                        <option value="1">担当</option>
                        <option value="2">OP</option>
                        <option value="3">作業員</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('setting.staff.list') }}">戻る</a>
                    <button class="btn btn-primary" type="submit">登録</button>
                </div>
            </form>
        </div>
    </div>
@endsection

