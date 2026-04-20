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
            <form method="POST" action="{{ route('setting.attendance.create') }}">
                @csrf
                <input type="hidden" name="mode" value="create">
                <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                <input type="hidden" name="work_date" value="{{ $work_date }}">

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
                        <label class="form-label small text-muted">休憩時間を入力（単位：分）</label>
                        <input
                            type="number"
                            class="form-control form-control-sm"
                            name="break_minutes"
                            value="{{ isset($break_minutes) ? (int)$break_minutes : 60 }}"
                            min="0"
                            step="1"
                        >
                    </div>
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
@endsection

