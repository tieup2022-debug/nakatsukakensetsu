@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠入力（個別編集）</h1>
            <div class="text-muted small">
                現場ID: {{ $workplace_id }} / 日付: {{ $work_date }} / 社員: {{ $staff_name }}
            </div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.list', ['workplace_id'=>$workplace_id,'work_date'=>$work_date]) }}">一覧へ</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.attendance.create') }}">
                @csrf
                <input type="hidden" name="mode" value="update">
                <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                <input type="hidden" name="work_date" value="{{ $work_date }}">
                <input type="hidden" name="staff_id" value="{{ $staff_id }}">

                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">出勤</label>
                        <input type="time" class="form-control form-control-sm" name="start_time" value="{{ $start_time ? substr((string)$start_time, 0, 5) : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">退勤</label>
                        <input type="time" class="form-control form-control-sm" name="end_time" value="{{ $end_time ? substr((string)$end_time, 0, 5) : '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">休憩</label>
                        <input type="time" class="form-control form-control-sm" name="break_time" value="{{ $break_time ? substr((string)$break_time, 0, 5) : '' }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted">欠勤</label>
                    <div class="form-check">
                        <input type="hidden" name="absence_flg" value="0">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            name="absence_flg"
                            value="1"
                            {{ $absence_flg ? 'checked' : '' }}
                        >
                        <label class="form-check-label">欠勤（〇）</label>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-primary" type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

