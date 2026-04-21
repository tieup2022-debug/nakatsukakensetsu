@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠</h1>
            <div class="text-muted small">
                {{ $display_date ?: '—' }}
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('top.attendance') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">現場</label>
                    <select class="form-select" name="workplace_id" onchange="this.form.submit()">
                        @foreach($workplace_list as $w)
                            <option value="{{ $w->id }}" {{ (string)$w->id === (string)$workplace_id ? 'selected' : '' }}>
                                {{ $w->workplace_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">作業日</label>
                    <input
                        type="text"
                        class="form-control js-datepicker"
                        name="work_date"
                        value="{{ $work_date }}"
                        data-datepicker-submit
                        readonly
                        autocomplete="off"
                    >
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-outline-primary btn-sm" type="submit">更新</button>
                    <a
                        class="btn btn-outline-secondary btn-sm ms-2"
                        href="{{ route('top.attendance', ['workplace_id'=>$workplace_id,'work_date'=>$work_date,'output_pdf'=>1]) }}"
                    >
                        月次表表示
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            @php
                // $attendance_data は DB有無によって Collection/配列/false などになるため安全に件数判定する
                $attendanceCount = 0;
                if (is_array($attendance_data)) {
                    $attendanceCount = count($attendance_data);
                } elseif (is_object($attendance_data) && method_exists($attendance_data, 'count')) {
                    $attendanceCount = $attendance_data->count();
                }
            @endphp

            @if (empty($attendance_data) || $attendanceCount === 0)
                <div class="text-muted">
                    表示できる勤怠データがありません。
                </div>
            @else
                <form method="POST" action="{{ route('top.attendance.update') }}">
                    @csrf
                    <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                    <input type="hidden" name="work_date" value="{{ $work_date }}">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 160px;">社員</th>
                                    <th style="min-width: 140px;">出勤</th>
                                    <th style="min-width: 140px;">退勤</th>
                                    <th style="min-width: 120px;">休憩</th>
                                    <th style="min-width: 110px;">欠勤</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attendance_data as $row)
                                    @php
                                        // 未入力時は一括登録と同じ初期値（08:00 / 17:00 / 休憩60分=01:00）
                                        $start = $row->start_time ?? '';
                                        $end = $row->end_time ?? '';
                                        $breakTime = $row->break_time ?? '';
                                        $startVal = $start ? substr((string)$start, 0, 5) : '08:00';
                                        $endVal = $end ? substr((string)$end, 0, 5) : '17:00';
                                        $breakVal = $breakTime ? substr((string)$breakTime, 0, 5) : '01:00';
                                        $isAbsent = isset($row->absence_flg) && intval($row->absence_flg) === 1;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $row->staff_name ?? '' }}</div>
                                            <input type="hidden" name="staff_ids[{{ $row->staff_id }}]" value="{{ $row->staff_id }}">
                                        </td>
                                        <td>
                                            <input type="time" class="form-control form-control-sm" name="start_time[{{ $row->staff_id }}]" value="{{ $startVal }}">
                                        </td>
                                        <td>
                                            <input type="time" class="form-control form-control-sm" name="end_time[{{ $row->staff_id }}]" value="{{ $endVal }}">
                                        </td>
                                        <td>
                                            <input type="time" class="form-control form-control-sm" name="break_time[{{ $row->staff_id }}]" value="{{ $breakVal }}">
                                        </td>
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="absence_flg[{{ $row->staff_id }}]"
                                                value="1"
                                                {{ $isAbsent ? 'checked' : '' }}
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button class="btn btn-primary" type="submit">保存</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

