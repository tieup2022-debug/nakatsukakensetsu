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
                                        $isAbsent = isset($row->absence_flg) && (int) $row->absence_flg !== 0;
                                        if ($isAbsent) {
                                            $startVal = '';
                                            $endVal = '';
                                            $breakVal = '';
                                        } else {
                                            $startVal = (string) ($row->display_start ?? '');
                                            $endVal = (string) ($row->display_end ?? '');
                                            $breakVal = (string) ($row->display_break ?? '');
                                            $startVal = $startVal !== '' ? $startVal : '08:00';
                                            $endVal = $endVal !== '' ? $endVal : '17:00';
                                            $breakVal = $breakVal !== '' ? $breakVal : '01:00';
                                        }
                                    @endphp
                                    @if($sid > 0)
                                    <tr class="{{ $isAbsent ? 'table-warning' : '' }}" data-absent-row="{{ $isAbsent ? '1' : '0' }}" data-staff-id="{{ $sid }}">
                                        <td>
                                            <div class="fw-medium">{{ $row->staff_name ?? '' }}</div>
                                            <input type="hidden" name="staff_ids[{{ $sid }}]" value="{{ $sid }}">
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-attendance-time"
                                                name="times[{{ $sid }}][start]"
                                                value="{{ $startVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="半角 時:分 例 08:00"
                                                placeholder="{{ $isAbsent ? '' : '08:00' }}"
                                                autocomplete="off"
                                                @unless($isAbsent) required @endunless
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-attendance-time"
                                                name="times[{{ $sid }}][end]"
                                                value="{{ $endVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="半角 時:分 例 17:30"
                                                placeholder="{{ $isAbsent ? '' : '17:00' }}"
                                                autocomplete="off"
                                                @unless($isAbsent) required @endunless
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-attendance-time"
                                                name="times[{{ $sid }}][break]"
                                                value="{{ $breakVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="半角 時:分 例 01:00"
                                                placeholder="{{ $isAbsent ? '' : '01:00' }}"
                                                autocomplete="off"
                                                @unless($isAbsent) required @endunless
                                            >
                                        </td>
                                        <td>
                                            <input type="hidden" name="absence_force[{{ $sid }}]" value="{{ $isAbsent ? '1' : '0' }}">
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

                        function syncAbsentRowTimes(tr) {
                            var absentCb = tr.querySelector('input[type="checkbox"][name^="absence_flg"]');
                            var isAbsent = absentCb && absentCb.checked;
                            tr.classList.toggle('table-warning', isAbsent);
                            tr.setAttribute('data-absent-row', isAbsent ? '1' : '0');
                            tr.querySelectorAll('input.js-attendance-time').forEach(function (el) {
                                el.required = !isAbsent;
                                if (isAbsent) {
                                    el.value = '';
                                    el.placeholder = '';
                                }
                            });
                        }

                        saveForm.querySelectorAll('tbody tr[data-absent-row]').forEach(syncAbsentRowTimes);

                        saveForm.querySelectorAll('input[type="checkbox"][name^="absence_flg"]').forEach(function (cb) {
                            cb.addEventListener('change', function () {
                                var tr = cb.closest('tr');
                                if (tr) {
                                    var force = tr.querySelector('input[type="hidden"][name^="absence_force"]');
                                    if (force) {
                                        force.value = cb.checked ? '1' : '0';
                                    }
                                    syncAbsentRowTimes(tr);
                                }
                            });
                        });

                        saveForm.addEventListener('submit', function () {
                            saveForm.querySelectorAll('input[name="absence_active[]"]').forEach(function (el) {
                                el.remove();
                            });
                            saveForm.querySelectorAll('tbody tr[data-absent-row]').forEach(function (tr) {
                                var cb = tr.querySelector('input[type="checkbox"][name^="absence_flg"]');
                                var force = tr.querySelector('input[type="hidden"][name^="absence_force"]');
                                if (cb && cb.checked) {
                                    var sid = tr.getAttribute('data-staff-id') || '';
                                    if (sid !== '') {
                                        var marker = document.createElement('input');
                                        marker.type = 'hidden';
                                        marker.name = 'absence_active[]';
                                        marker.value = sid;
                                        saveForm.appendChild(marker);
                                    }
                                }
                                if (force) {
                                    force.value = (cb && cb.checked) ? '1' : '0';
                                }
                            });

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
                            saveForm.querySelectorAll('tbody tr[data-absent-row]').forEach(function (tr) {
                                var cb = tr.querySelector('input[type="checkbox"][name^="absence_flg"]');
                                if (cb && cb.checked) {
                                    syncAbsentRowTimes(tr);
                                }
                            });
                        });
                    })();
                </script>
            @endif
        </div>
    </div>
@endsection
