@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠の初期時間</h1>
            <div class="text-muted small">
                勤怠の一括登録などで使う初期の出勤・退勤・休憩を保存します（管理者のみ）。
            </div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger mb-3">
            <ul class="mb-0 small">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.attendance.defaults.submit') }}">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted" for="start_time">出勤</label>
                        <input
                            type="time"
                            class="form-control form-control-sm @error('start_time') is-invalid @enderror"
                            id="start_time"
                            name="start_time"
                            value="{{ old('start_time', $start_display) }}"
                            required
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted" for="end_time">退勤</label>
                        <input
                            type="time"
                            class="form-control form-control-sm @error('end_time') is-invalid @enderror"
                            id="end_time"
                            name="end_time"
                            value="{{ old('end_time', $end_display) }}"
                            required
                        >
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted" for="break_minutes">休憩（分）</label>
                        <input
                            type="number"
                            class="form-control form-control-sm @error('break_minutes') is-invalid @enderror"
                            id="break_minutes"
                            name="break_minutes"
                            value="{{ old('break_minutes', (int) $break_minutes) }}"
                            min="0"
                            max="1440"
                            step="1"
                            required
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <input type="hidden" name="is_enabled" value="0">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="is_enabled"
                            value="1"
                            id="is_enabled"
                            {{ old('is_enabled', $is_enabled ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label class="form-check-label small" for="is_enabled">この初期値を有効にする</label>
                    </div>
                    <div class="text-muted small mt-1">無効にすると、システム内蔵のデフォルト（8:00〜17:00・休憩60分相当）が使われます。</div>
                </div>

                <button type="submit" class="btn btn-primary">保存</button>
            </form>
        </div>
    </div>
@endsection
