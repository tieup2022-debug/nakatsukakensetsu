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

                <div class="row g-2 mb-1">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">深夜出勤（夜勤の日のみ）</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_start_time" value="{{ $midnight_start_time ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">深夜退勤（夜勤の日のみ）</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_end_time" value="{{ $midnight_end_time ?? '' }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">深夜休憩（夜勤時の既定 01:00）</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_break_time" value="{{ $midnight_break_time ?? '' }}">
                        <div class="form-check mt-1">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                id="midnight_break_deduct"
                                name="midnight_break_deduct"
                                value="1"
                                {{ !empty($midnight_break_deduct) ? 'checked' : '' }}
                            >
                            <label class="form-check-label small" for="midnight_break_deduct">深夜時間から差し引く（休憩を22時〜翌5時に取った場合）</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">時間外（深夜）（未入力可）</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_overtime_time" value="{{ $midnight_overtime_time ?? '' }}">
                    </div>
                </div>
                <div class="mb-3 form-text">
                    夜勤（例 18:00〜翌3:30）は深夜出勤・深夜退勤に入力してください（夜勤のみの日は出勤・退勤を空欄に）。
                    深夜時間は22時〜翌5時の重なりから自動計算されます{{ ($midnight_auto ?? '') !== '' ? '（現在の時刻なら '.$midnight_auto.'）' : '' }}。
                    時間外（深夜）のみ手入力です。
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

