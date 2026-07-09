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
                                    <th style="min-width: 120px;">出勤</th>
                                    <th style="min-width: 120px;">退勤</th>
                                    <th style="min-width: 110px;">休憩</th>
                                    <th style="min-width: 120px;">深夜出勤</th>
                                    <th style="min-width: 120px;">深夜退勤</th>
                                    <th style="min-width: 110px;">深夜時間</th>
                                    <th style="min-width: 120px;">時間外(深夜)</th>
                                    <th style="min-width: 90px;">欠勤</th>
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
                                            $midnightStartVal = '';
                                            $midnightEndVal = '';
                                            $midnightAutoVal = '';
                                            $midnightOvertimeVal = '';
                                            $dayPrefill = '0';
                                            $dayRequired = false;
                                        } else {
                                            $startVal = (string) ($row->display_start ?? '');
                                            $endVal = (string) ($row->display_end ?? '');
                                            $breakVal = (string) ($row->display_break ?? '');
                                            $midnightStartVal = (string) ($row->display_midnight_start ?? '');
                                            $midnightEndVal = (string) ($row->display_midnight_end ?? '');
                                            $nightPair = $midnightStartVal !== '' && $midnightEndVal !== '';
                                            // 夜勤のみの行は昼欄を空のまま（08:00等で埋めると昼勤務として保存されてしまう）
                                            if (! ($startVal === '' && $endVal === '' && $nightPair)) {
                                                $startVal = $startVal !== '' ? $startVal : '08:00';
                                                $endVal = $endVal !== '' ? $endVal : '17:00';
                                            }
                                            $breakVal = $breakVal !== '' ? $breakVal : '01:00';
                                            // 深夜時間は表示専用（昼＋夜それぞれの22時〜翌5時重なりの合計を自動計算）
                                            $midnightAutoVal = (string) ($row->display_midnight_auto ?? '');
                                            // 時間外（深夜）は任意入力（既定値なし・未入力は空欄）
                                            $midnightOvertimeVal = (string) ($row->display_midnight_overtime ?? '');
                                            // 昼欄が既定値の仮表示か（夜勤入力時に JS が昼欄を自動クリアしてよいか）
                                            $dayPrefill = ($row->display_day_is_fallback ?? false) ? '1' : '0';
                                            $dayRequired = ! $nightPair;
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
                                                title="半角 時:分 例 08:00（夜勤のみの日は空欄）"
                                                placeholder="{{ $isAbsent ? '' : '08:00' }}"
                                                autocomplete="off"
                                                data-day-prefill="{{ $dayPrefill }}"
                                                @if($dayRequired) required @endif
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
                                                title="半角 時:分 例 17:30（夜勤のみの日は空欄）"
                                                placeholder="{{ $isAbsent ? '' : '17:00' }}"
                                                autocomplete="off"
                                                data-day-prefill="{{ $dayPrefill }}"
                                                @if($dayRequired) required @endif
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
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-attendance-time"
                                                name="times[{{ $sid }}][midnight_start]"
                                                value="{{ $midnightStartVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="深夜出勤 例 18:00（夜勤の日のみ入力）"
                                                placeholder=""
                                                autocomplete="off"
                                                data-optional="1"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-attendance-time"
                                                name="times[{{ $sid }}][midnight_end]"
                                                value="{{ $midnightEndVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="深夜退勤 例 03:30（夜勤の日のみ入力）"
                                                placeholder=""
                                                autocomplete="off"
                                                data-optional="1"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-midnight-total"
                                                value="{{ $midnightAutoVal }}"
                                                title="22時〜翌5時の重なりから自動計算（入力不要）"
                                                readonly
                                                tabindex="-1"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                class="form-control form-control-sm font-monospace js-attendance-time"
                                                name="times[{{ $sid }}][midnight_overtime]"
                                                value="{{ $midnightOvertimeVal }}"
                                                inputmode="numeric"
                                                maxlength="5"
                                                pattern="^(?:[01]?[0-9]|2[0-3]):[0-5][0-9]$"
                                                title="時間外（深夜） 半角 時:分 例 00:30（未入力可）"
                                                placeholder=""
                                                autocomplete="off"
                                                data-optional="1"
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

                        // 深夜時間の自動計算（サーバー側 calcAutoMidnightMinutes と同じ 22:00〜翌5:00 の重なり）
                        function timeToMin(v) {
                            var m = /^(\d{1,2}):(\d{2})$/.exec((v || '').trim());
                            if (!m) return null;
                            var h = +m[1], mi = +m[2];
                            if (h > 23 || mi > 59) return null;
                            return h * 60 + mi;
                        }
                        function midnightOverlapMinutes(startVal, endVal) {
                            var s = timeToMin(startVal), e = timeToMin(endVal);
                            if (s === null || e === null) return 0;
                            if (e < s) e += 1440;
                            function ov(a, b) { return Math.max(0, Math.min(e, b) - Math.max(s, a)); }
                            return ov(0, 300) + ov(1320, 1440) + ov(1440, 1740);
                        }
                        function rowInput(tr, suffix) {
                            return tr.querySelector('input[name$="' + suffix + '"]');
                        }
                        function rowValue(tr, suffix) {
                            var el = rowInput(tr, suffix);
                            return el ? el.value : '';
                        }
                        function nightPairFilled(tr) {
                            return rowValue(tr, '[midnight_start]').trim() !== ''
                                && rowValue(tr, '[midnight_end]').trim() !== '';
                        }
                        // 深夜時間（表示専用）: 昼＋夜それぞれの重なり合計
                        function updateMidnightTotal(tr) {
                            var total = tr.querySelector('input.js-midnight-total');
                            if (!total) return;
                            if (tr.getAttribute('data-absent-row') === '1') {
                                total.value = '';
                                return;
                            }
                            var t = midnightOverlapMinutes(rowValue(tr, '[start]'), rowValue(tr, '[end]'))
                                + midnightOverlapMinutes(rowValue(tr, '[midnight_start]'), rowValue(tr, '[midnight_end]'));
                            total.value = t > 0
                                ? ('0' + Math.floor(t / 60)).slice(-2) + ':' + ('0' + (t % 60)).slice(-2)
                                : '';
                        }
                        // 夜勤（深夜出勤・退勤）が入っている行は昼の出退勤を必須にしない
                        function recalcDayRequired(tr) {
                            var absent = tr.getAttribute('data-absent-row') === '1';
                            var night = nightPairFilled(tr);
                            ['[start]', '[end]'].forEach(function (suffix) {
                                var el = rowInput(tr, suffix);
                                if (el) {
                                    el.required = !absent && !night;
                                }
                            });
                        }
                        // 夜勤を入力したとき、昼欄が「既定値の仮表示のまま」なら自動で空にする
                        // （放置すると 08:00〜17:00 の昼勤務として保存されてしまうため）
                        function clearPrefilledDayIfNight(tr) {
                            if (!nightPairFilled(tr)) return;
                            ['[start]', '[end]'].forEach(function (suffix) {
                                var el = rowInput(tr, suffix);
                                if (el && el.dataset.dayPrefill === '1' && el.value === el.defaultValue) {
                                    el.value = '';
                                    el.dataset.dayPrefill = '0';
                                }
                            });
                        }

                        function syncAbsentRowTimes(tr) {
                            var absentCb = tr.querySelector('input[type="checkbox"][name^="absence_flg"]');
                            var isAbsent = absentCb && absentCb.checked;
                            tr.classList.toggle('table-warning', isAbsent);
                            tr.setAttribute('data-absent-row', isAbsent ? '1' : '0');
                            tr.querySelectorAll('input.js-attendance-time').forEach(function (el) {
                                // 深夜など data-optional の欄は出勤時も必須にしない
                                el.required = !isAbsent && el.dataset.optional !== '1';
                                if (isAbsent) {
                                    el.value = '';
                                    el.placeholder = '';
                                }
                            });
                        }

                        saveForm.querySelectorAll('tbody tr[data-absent-row]').forEach(function (tr) {
                            syncAbsentRowTimes(tr);
                            recalcDayRequired(tr);
                            updateMidnightTotal(tr);
                            ['[start]', '[end]'].forEach(function (suffix) {
                                var el = rowInput(tr, suffix);
                                if (el) {
                                    el.addEventListener('input', function () {
                                        updateMidnightTotal(tr);
                                    });
                                }
                            });
                            ['[midnight_start]', '[midnight_end]'].forEach(function (suffix) {
                                var el = rowInput(tr, suffix);
                                if (el) {
                                    el.addEventListener('input', function () {
                                        clearPrefilledDayIfNight(tr);
                                        recalcDayRequired(tr);
                                        updateMidnightTotal(tr);
                                    });
                                }
                            });
                        });

                        saveForm.querySelectorAll('input[type="checkbox"][name^="absence_flg"]').forEach(function (cb) {
                            cb.addEventListener('change', function () {
                                var tr = cb.closest('tr');
                                if (tr) {
                                    var force = tr.querySelector('input[type="hidden"][name^="absence_force"]');
                                    if (force) {
                                        force.value = cb.checked ? '1' : '0';
                                    }
                                    syncAbsentRowTimes(tr);
                                    recalcDayRequired(tr);
                                    updateMidnightTotal(tr);
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
