@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">現場編集</h1>
            <div class="text-muted small">現場名と状態を更新します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.workplace.update.submit') }}">
                @csrf
                <input type="hidden" name="workplace_id" value="{{ $workplace_data->id }}">

                <div class="mb-3">
                    <label class="form-label small text-muted">現場名</label>
                    <input type="text" name="workplace_name" class="form-control" value="{{ $workplace_data->workplace_name }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">稼働中</label>
                    <div class="form-check">
                        <input type="hidden" name="active_flg" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="active_flg"
                            name="active_flg"
                            value="1"
                            {{ intval($workplace_data->active_flg ?? 0) === 1 ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="active_flg">稼働中にする</label>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('setting.workplace.list') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
@endsection

