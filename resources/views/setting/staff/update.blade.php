@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">スタッフ編集</h1>
            <div class="text-muted small">社員情報を更新します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.staff.update.submit') }}">
                @csrf
                <input type="hidden" name="staff_id" value="{{ $staff_data->id }}">

                <div class="mb-3">
                    <label class="form-label small text-muted">社員名</label>
                    <input type="text" name="staff_name" class="form-control" value="{{ $staff_data->staff_name }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">種別</label>
                    <select name="staff_type" class="form-select" required>
                        <option value="1" {{ (string)$staff_data->staff_type === '1' ? 'selected' : '' }}>技術者</option>
                        <option value="2" {{ (string)$staff_data->staff_type === '2' ? 'selected' : '' }}>OP</option>
                        <option value="3" {{ (string)$staff_data->staff_type === '3' ? 'selected' : '' }}>作業員</option>
                        <option value="4" {{ (string)$staff_data->staff_type === '4' ? 'selected' : '' }}>総務部</option>
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('setting.staff.list') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
@endsection

