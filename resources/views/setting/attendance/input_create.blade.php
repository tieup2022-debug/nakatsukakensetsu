@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠入力（一括登録）</h1>
            <div class="text-muted small">現場ID: {{ $workplace_id }} / 日付: {{ $work_date }}</div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">戻る</a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.attendance.create') }}" id="attendance-bulk-create-form">
                @csrf
                <input type="hidden" name="mode" value="create">
                <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                <input type="hidden" name="work_date" value="{{ $work_date }}">

                <div class="small fw-semibold mb-2">昼勤務</div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">出勤</label>
                        <input type="time" class="form-control form-control-sm" name="start_time" value="{{ $start_time ? substr((string)$start_time, 0, 5) : '' }}" data-day-prefill="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">退勤</label>
                        <input type="time" class="form-control form-control-sm" name="end_time" value="{{ $end_time ? substr((string)$end_time, 0, 5) : '' }}" data-day-prefill="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">休憩時間を入力（単位：分）</label>
                        <input
                            type="number"
                            class="form-control form-control-sm"
                            name="break_minutes"
                            value="{{ isset($break_minutes) ? (int)$break_minutes : 60 }}"
                            min="0"
                            step="1"
                            data-day-prefill="1"
                        >
                    </div>
                </div>

                <div class="small fw-semibold mb-2">深夜勤務</div>
                <div class="row g-2 mb-2">
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">深夜出勤</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_start_time" value="">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">深夜退勤</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_end_time" value="">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">深夜時間（自動）</label>
                        <input type="text" class="form-control form-control-sm font-monospace" id="bulk-midnight-total" value="" readonly tabindex="-1">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">深夜休憩</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_break_time" value="">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small text-muted">時間外（深夜）</label>
                        <input type="time" class="form-control form-control-sm" name="midnight_overtime_time" value="">
                    </div>
                    <div class="col-lg-2 col-md-4 d-flex align-items-end">
                        <div class="form-check mb-1">
                            <input type="hidden" name="midnight_break_deduct" value="0">
                            <input type="checkbox" class="form-check-input" id="bulk_midnight_break_deduct" name="midnight_break_deduct" value="1">
                            <label class="form-check-label small" for="bulk_midnight_break_deduct">深夜休憩を深夜時間から控除</label>
                        </div>
                    </div>
                </div>
                <div class="form-text mb-3">
                    深夜出勤・深夜退勤を入力すると深夜休憩に01:00を自動設定します。夜勤のみの場合は、昼勤務の初期値と昼休憩を自動で空にします。
                </div>

                <div class="mb-3">
                    <div class="text-muted small mb-2">欠勤者の入力</div>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="absence_mode"
                            value="1"
                            id="absence_mode_1"
                            {{ (int)($absence_mode ?? 1) === 1 ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="absence_mode_1">欠勤者の入力</label>
                    </div>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            name="absence_mode"
                            value="0"
                            id="absence_mode_0"
                            {{ (int)($absence_mode ?? 1) === 0 ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="absence_mode_0">出退勤の一括登録（欠勤なし）</label>
                    </div>
                </div>

                <div class="table-responsive">
                    @if((int)($absence_mode ?? 1) === 1)
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="min-width: 220px;">社員</th>
                                    <th style="width: 140px;">欠勤</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($assigned_staff_list as $staff)
                                    @php
                                        $isAbsent = isset($staff->absence_flg) && intval($staff->absence_flg) === 1;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-medium">{{ $staff->staff_name ?? '' }}</div>
                                            <input type="hidden" name="staff_ids[{{ $staff->staff_id }}]" value="{{ $staff->staff_id }}">
                                        </td>
                                        <td>
                                            <input type="hidden" name="absenceStaffList[{{ $staff->staff_id }}]" value="0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="absenceStaffList[{{ $staff->staff_id }}]"
                                                value="1"
                                                {{ $isAbsent ? 'checked' : '' }}
                                            >
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-muted">対象のスタッフがいません。</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <div class="text-muted">
                            欠勤者は入力しません（全員出勤扱いで登録します）。
                        </div>
                    @endif
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button
                        class="btn btn-primary {{ empty($assigned_staff_list) ? 'disabled' : '' }}"
                        type="submit"
                    >登録</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var form = document.getElementById('attendance-bulk-create-form');
            if (!form) return;

            var dayStart = form.querySelector('input[name="start_time"]');
            var dayEnd = form.querySelector('input[name="end_time"]');
            var dayBreak = form.querySelector('input[name="break_minutes"]');
            var nightStart = form.querySelector('input[name="midnight_start_time"]');
            var nightEnd = form.querySelector('input[name="midnight_end_time"]');
            var nightBreak = form.querySelector('input[name="midnight_break_time"]');
            var nightDeduct = form.querySelector('input[type="checkbox"][name="midnight_break_deduct"]');
            var midnightTotal = document.getElementById('bulk-midnight-total');

            function timeToMinutes(value) {
                var match = /^(\d{1,2}):(\d{2})$/.exec((value || '').trim());
                if (!match) return null;
                var hours = Number(match[1]);
                var minutes = Number(match[2]);
                if (hours > 23 || minutes > 59) return null;
                return hours * 60 + minutes;
            }

            function midnightOverlapMinutes(startValue, endValue) {
                var start = timeToMinutes(startValue);
                var end = timeToMinutes(endValue);
                if (start === null || end === null) return 0;
                if (end < start) end += 1440;
                function overlap(from, to) {
                    return Math.max(0, Math.min(end, to) - Math.max(start, from));
                }
                return overlap(0, 300) + overlap(1320, 1440) + overlap(1440, 1740);
            }

            function nightPairFilled() {
                return nightStart.value.trim() !== '' && nightEnd.value.trim() !== '';
            }

            function clearDayPrefillForNightOnly() {
                if (!nightPairFilled()) return;
                [dayStart, dayEnd, dayBreak].forEach(function (input) {
                    if (input && input.dataset.dayPrefill === '1' && input.value === input.defaultValue) {
                        input.value = '';
                        input.dataset.dayPrefill = '0';
                    }
                });
            }

            function syncMidnightBreakDefault() {
                if (nightBreak.dataset.breakEdited === '1') return;
                if (nightPairFilled()) {
                    if (nightBreak.value.trim() === '') {
                        nightBreak.value = '01:00';
                        nightBreak.dataset.autoBreak = '1';
                    }
                } else if (nightBreak.dataset.autoBreak === '1' && nightBreak.value === '01:00') {
                    nightBreak.value = '';
                    nightBreak.dataset.autoBreak = '';
                }
            }

            function updateMidnightTotal() {
                var total = midnightOverlapMinutes(dayStart.value, dayEnd.value)
                    + midnightOverlapMinutes(nightStart.value, nightEnd.value);
                if (nightDeduct.checked) {
                    total -= timeToMinutes(nightBreak.value) || 0;
                }
                total = Math.max(0, total);
                midnightTotal.value = total > 0
                    ? String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0')
                    : '';
            }

            [nightStart, nightEnd].forEach(function (input) {
                input.addEventListener('input', function () {
                    clearDayPrefillForNightOnly();
                    syncMidnightBreakDefault();
                    updateMidnightTotal();
                });
            });
            [dayStart, dayEnd].forEach(function (input) {
                input.addEventListener('input', updateMidnightTotal);
            });
            nightBreak.addEventListener('input', function () {
                nightBreak.dataset.breakEdited = '1';
                updateMidnightTotal();
            });
            nightDeduct.addEventListener('change', updateMidnightTotal);
        })();
    </script>
@endsection
