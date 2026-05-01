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

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('top.attendance') }}" id="top-attendance-filter-form" class="row g-2 align-items-end">
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
                <form method="POST" action="{{ route('top.attendance.update') }}" id="top-attendance-save-form" autocomplete="off" novalidate>
                    @csrf
                    <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                    <input type="hidden" name="work_date" value="{{ $work_date }}">
                    @php
                        $attendanceService = app(\App\Services\AttendanceService::class);
                    @endphp

                    <div class="alert alert-warning small mb-3" role="note">
                        <strong>欠勤にチェックが入っている行</strong>は、出勤・退勤として記録する場合は
                        <strong>先に「欠勤」のチェックを外してから</strong>時刻を直し、「保存」してください。
                        （チェックが入ったままだと、画面上は時刻を変えても欠勤扱いのままです。）
                    </div>
                    <div class="alert alert-secondary small mb-3 py-2" role="note">
                        時刻は「<span class="font-monospace">17:30</span>」のようにコロン区切りが基本です。
                        スマホなどで打ちにくい場合は <span class="font-monospace">1730</span>（4桁）や <span class="font-monospace">930</span>（3桁＝9:30）でも保存できます。
                    </div>

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
                                        $sid = (int) ($row->staff_id ?? 0);
                                        // 未入力時は一括登録と同じ初期値（08:00 / 17:00 / 休憩60分=01:00）
                                        $startVal = $attendanceService->formatTimeForDisplay($row->start_time ?? null);
                                        $endVal = $attendanceService->formatTimeForDisplay($row->end_time ?? null);
                                        $breakVal = $attendanceService->formatTimeForDisplay($row->break_time ?? null);
                                        $startVal = $startVal !== '' ? $startVal : '08:00';
                                        $endVal = $endVal !== '' ? $endVal : '17:00';
                                        $breakVal = $breakVal !== '' ? $breakVal : '01:00';
                                        $isAbsent = isset($row->absence_flg) && intval($row->absence_flg) === 1;
                                    @endphp
                                    @if($sid > 0)
                                    <tr class="{{ $isAbsent ? 'table-warning' : '' }}">
                                        <td>
                                            <div class="fw-medium">{{ $row->staff_name ?? '' }}</div>
                                            @if($isAbsent)
                                                <div class="small text-danger mt-1">
                                                    ※<strong>欠勤</strong>にチェックが入っています。出勤として記録する場合は<strong>チェックを外してから</strong>退勤などを直し、保存してください。
                                                </div>
                                            @endif
                                            <input type="hidden" name="staff_ids[{{ $sid }}]" value="{{ $sid }}">
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace"
                                                name="times[{{ $sid }}][start]"
                                                value="{{ $startVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="半角 時:分 例 08:00"
                                                placeholder="08:00"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace"
                                                name="times[{{ $sid }}][end]"
                                                value="{{ $endVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="半角 時:分 例 17:30"
                                                placeholder="17:00"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace"
                                                name="times[{{ $sid }}][break]"
                                                value="{{ $breakVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="半角 時:分 例 01:00"
                                                placeholder="01:00"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <input type="hidden" name="absence_flg[{{ $sid }}]" value="0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="absence_flg[{{ $sid }}]"
                                                value="1"
                                                {{ $isAbsent ? 'checked' : '' }}
                                            >
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-3">
                        <button class="btn btn-primary" type="submit">保存</button>
                    </div>
                </form>
                <script>
                    (function () {
                        var saveForm = document.getElementById('top-attendance-save-form');
                        var filterForm = document.getElementById('top-attendance-filter-form');
                        if (!saveForm) return;
                        saveForm.addEventListener('submit', function () {
                            // 画面上の「現場・作業日」と保存用 hidden がずれると、別日・別現場の t_attendance が更新される
                            if (filterForm) {
                                var wd = filterForm.querySelector('input[name="work_date"]');
                                var wp = filterForm.querySelector('select[name="workplace_id"]');
                                var hWd = saveForm.querySelector('input[name="work_date"]');
                                var hWp = saveForm.querySelector('input[name="workplace_id"]');
                                if (wd && hWd) {
                                    // flatpickr はカレンダー表示と input.value がずれることがあるため、選択中の日付を優先する
                                    var fp = wd._flatpickr;
                                    if (fp && fp.selectedDates && fp.selectedDates.length > 0) {
                                        if (typeof flatpickr !== 'undefined' && typeof flatpickr.formatDate === 'function') {
                                            hWd.value = flatpickr.formatDate(fp.selectedDates[0], 'Y-m-d');
                                        } else if (typeof fp.formatDate === 'function') {
                                            hWd.value = fp.formatDate(fp.selectedDates[0], 'Y-m-d');
                                        } else {
                                            hWd.value = wd.value;
                                        }
                                    } else {
                                        hWd.value = wd.value;
                                    }
                                }
                                if (wp && hWp) {
                                    hWp.value = wp.value;
                                }
                            }
                            saveForm.querySelectorAll('input[name^="times["]').forEach(function (el) {
                                el.disabled = false;
                                el.readOnly = false;
                            });
                        });
                    })();
                </script>
            @endif
        </div>
    </div>
@endsection
