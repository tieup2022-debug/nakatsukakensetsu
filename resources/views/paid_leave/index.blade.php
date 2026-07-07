@extends('layouts.app')

@section('content')
    @php
        $showActions = !empty($can_approve_paid_leave) || !empty($can_manage_paid_leave);
        $actionColspan = $showActions ? 7 : 6;
        $sort = $sort ?? 'id';
        $direction = $direction ?? 'desc';
        $sortLink = function (string $key) use ($sort, $direction) {
            $nextDirection = $sort === $key && $direction === 'asc' ? 'desc' : 'asc';

            return request()->fullUrlWithQuery(['sort' => $key, 'direction' => $nextDirection]);
        };
        $sortMark = function (string $key) use ($sort, $direction) {
            if ($sort !== $key) {
                return '';
            }

            return $direction === 'asc' ? ' ↑' : ' ↓';
        };
    @endphp
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">有給申請</h1>
            <div class="text-muted small">有給対象者を選び、<strong>休む日</strong>とその日の<strong>開始・終了時刻</strong>を入力します（1日単位）。下部で全員の申請状況を確認できます。</div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('paid-leave.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted">有給対象者</label>
                    <select name="applicant_staff_id" class="form-select @error('applicant_staff_id') is-invalid @enderror" required>
                        <option value="">選択してください</option>
                        @foreach ($staff_list as $s)
                            <option value="{{ $s->id }}" @selected((string) old('applicant_staff_id') === (string) $s->id)>{{ $s->staff_name }}</option>
                        @endforeach
                    </select>
                    @error('applicant_staff_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted">休む日</label>
                    <input
                        type="text"
                        name="leave_date"
                        class="form-control js-datepicker @error('leave_date') is-invalid @enderror"
                        value="{{ old('leave_date') }}"
                        readonly
                        required
                        autocomplete="off"
                        placeholder="日付を選択"
                    >
                    @error('leave_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted">開始時刻</label>
                    <input
                        type="text"
                        name="start_time"
                        class="form-control js-timepicker @error('start_time') is-invalid @enderror"
                        value="{{ old('start_time') }}"
                        required
                        autocomplete="off"
                        inputmode="numeric"
                        placeholder="例）08:00"
                    >
                    @error('start_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted">終了時刻</label>
                    <input
                        type="text"
                        name="end_time"
                        class="form-control js-timepicker @error('end_time') is-invalid @enderror"
                        value="{{ old('end_time') }}"
                        required
                        autocomplete="off"
                        inputmode="numeric"
                        placeholder="例）17:00"
                    >
                    @error('end_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted">備考（任意）</label>
                    <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" maxlength="2000" placeholder="任意">
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="btn btn-primary w-100">申請</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>
                                <a href="{{ $sortLink('target_staff') }}" class="text-decoration-none text-dark">
                                    有給対象者{{ $sortMark('target_staff') }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('starts_at') }}" class="text-decoration-none text-dark">
                                    休む日（開始〜終了）{{ $sortMark('starts_at') }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('created_at') }}" class="text-decoration-none text-dark">
                                    申請日時{{ $sortMark('created_at') }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('requester') }}" class="text-decoration-none text-dark">
                                    申請者{{ $sortMark('requester') }}
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortLink('status') }}" class="text-decoration-none text-dark">
                                    状態{{ $sortMark('status') }}
                                </a>
                            </th>
                            <th>備考</th>
                            @if ($showActions)
                                <th style="width: 180px;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $r)
                        @php
                            $leaveDate = \Carbon\Carbon::parse($r->starts_at)->format('Y-m-d');
                            $startTime = \Carbon\Carbon::parse($r->starts_at)->format('H:i');
                            $endTime = \Carbon\Carbon::parse($r->ends_at)->format('H:i');
                        @endphp
                        <tr>
                            <td class="fw-medium">{{ $r->target_staff_name }}</td>
                            <td class="small">
                                {{ \App\Support\DatetimeDisplay::formatWallClock($r->starts_at) }}
                                〜
                                {{ \App\Support\DatetimeDisplay::formatWallClock($r->ends_at) }}
                            </td>
                            <td class="small">{{ \App\Support\DatetimeDisplay::formatStoredAt($r->created_at) }}</td>
                            <td class="small">{{ $r->requester_user_name }}</td>
                            <td>
                                @if($r->status === 'approved')
                                    <span class="badge text-bg-success">承認済</span>
                                    @if(!empty($r->approver_display_name))
                                        <div class="small text-muted mt-1">承認: {{ $r->approver_display_name }}</div>
                                    @endif
                                @else
                                    <span class="badge text-bg-warning text-dark">申請中</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?? '—', 40) }}</td>
                            @if ($showActions)
                                <td class="text-end text-nowrap">
                                    @if (!empty($can_manage_paid_leave))
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary btn-sm me-1 js-paid-leave-edit"
                                            data-id="{{ $r->id }}"
                                            data-applicant-staff-id="{{ $r->applicant_staff_id }}"
                                            data-leave-date="{{ $leaveDate }}"
                                            data-start-time="{{ $startTime }}"
                                            data-end-time="{{ $endTime }}"
                                            data-reason="{{ $r->reason ?? '' }}"
                                            data-target-name="{{ $r->target_staff_name }}"
                                        >編集</button>
                                        <form method="POST" action="{{ route('paid-leave.destroy', ['id' => $r->id]) }}" class="d-inline" onsubmit="return confirm('この有給申請を削除しますか？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
                                        </form>
                                    @endif
                                    @if (!empty($can_approve_paid_leave) && $r->status === 'pending')
                                        <form method="POST" action="{{ route('paid-leave.approve', ['id' => $r->id]) }}" class="d-inline {{ !empty($can_manage_paid_leave) ? 'ms-1' : '' }}" onsubmit="return confirm('この申請を承認しますか？');">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">承認</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $actionColspan }}" class="text-center text-muted py-4">申請はまだありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if (!empty($can_manage_paid_leave))
        <div class="modal fade" id="paidLeaveEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" id="paidLeaveEditForm" action="">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h5 class="modal-title">有給申請の編集</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">有給対象者</label>
                                    <select name="applicant_staff_id" id="paidLeaveEditStaff" class="form-select" required>
                                        @foreach ($manage_staff_list as $s)
                                            <option value="{{ $s->id }}">{{ $s->staff_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">休む日</label>
                                    <input type="text" name="leave_date" id="paidLeaveEditDate" class="form-control js-datepicker-modal" readonly required autocomplete="off">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted">開始時刻</label>
                                    <input type="text" name="start_time" id="paidLeaveEditStart" class="form-control js-timepicker-modal" required autocomplete="off" inputmode="numeric" placeholder="例）08:00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted">終了時刻</label>
                                    <input type="text" name="end_time" id="paidLeaveEditEnd" class="form-control js-timepicker-modal" required autocomplete="off" inputmode="numeric" placeholder="例）17:00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">備考（任意）</label>
                                    <input type="text" name="reason" id="paidLeaveEditReason" class="form-control" maxlength="2000">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button>
                            <button type="submit" class="btn btn-primary">更新</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($can_manage_paid_leave))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('paidLeaveEditModal');
                var formEl = document.getElementById('paidLeaveEditForm');
                if (!modalEl || !formEl || typeof bootstrap === 'undefined') return;

                var modal = new bootstrap.Modal(modalEl);
                var staffEl = document.getElementById('paidLeaveEditStaff');
                var dateEl = document.getElementById('paidLeaveEditDate');
                var startEl = document.getElementById('paidLeaveEditStart');
                var endEl = document.getElementById('paidLeaveEditEnd');
                var reasonEl = document.getElementById('paidLeaveEditReason');
                var datePicker = null;
                var startPicker = null;
                var endPicker = null;

                function initModalPickers() {
                    if (typeof flatpickr === 'undefined') return;
                    var ja = flatpickr.l10ns.ja;
                    if (!datePicker) {
                        datePicker = flatpickr(dateEl, {
                            locale: ja,
                            dateFormat: 'Y-m-d',
                            allowInput: false,
                            disableMobile: true,
                        });
                    }
                    if (!startPicker) {
                        startPicker = flatpickr(startEl, {
                            locale: ja,
                            enableTime: true,
                            noCalendar: true,
                            time_24hr: true,
                            dateFormat: 'H:i',
                            minuteIncrement: 15,
                            allowInput: true,
                            disableMobile: true,
                        });
                    }
                    if (!endPicker) {
                        endPicker = flatpickr(endEl, {
                            locale: ja,
                            enableTime: true,
                            noCalendar: true,
                            time_24hr: true,
                            dateFormat: 'H:i',
                            minuteIncrement: 15,
                            allowInput: true,
                            disableMobile: true,
                        });
                    }
                }

                document.querySelectorAll('.js-paid-leave-edit').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var id = btn.getAttribute('data-id');
                        formEl.action = @json(route('paid-leave.update', ['id' => '__ID__'])).replace('__ID__', id);
                        staffEl.value = btn.getAttribute('data-applicant-staff-id') || '';
                        reasonEl.value = btn.getAttribute('data-reason') || '';
                        initModalPickers();
                        if (datePicker) datePicker.setDate(btn.getAttribute('data-leave-date') || '', false);
                        if (startPicker) startPicker.setDate(btn.getAttribute('data-start-time') || '', false);
                        if (endPicker) endPicker.setDate(btn.getAttribute('data-end-time') || '', false);
                        modal.show();
                    });
                });
            });
        </script>
    @endif
@endsection
