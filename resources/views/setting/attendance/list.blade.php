@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠一覧</h1>
            <div class="text-muted small">現場ID: {{ $workplace_id }} / 日付: {{ $work_date }}</div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage', ['workplace_id'=>$workplace_id,'work_date'=>$work_date]) }}">管理画面へ</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">社員</th>
                            <th style="width: 120px;">出勤</th>
                            <th style="width: 120px;">退勤</th>
                            <th style="width: 120px;">休憩</th>
                            <th style="width: 90px;">欠勤</th>
                            <th style="width: 180px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assigned_staff_list as $row)
                            @php
                                $startVal = $row->display_start ?? '';
                                $endVal = $row->display_end ?? '';
                                $breakVal = $row->display_break ?? '';
                                $isAbsent = isset($row->absence_flg) && intval($row->absence_flg) === 1;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $row->staff_name ?? '' }}</div>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" value="{{ $startVal }}" readonly></td>
                                <td><input type="text" class="form-control form-control-sm" value="{{ $endVal }}" readonly></td>
                                <td><input type="text" class="form-control form-control-sm" value="{{ $breakVal }}" readonly></td>
                                <td class="text-center">{{ $isAbsent ? '〇' : '' }}</td>
                                <td>
                                    <a
                                        class="btn btn-outline-primary btn-sm me-2"
                                        href="{{ route('setting.attendance.input', [
                                            'mode' => 'update',
                                            'workplace_id' => $workplace_id,
                                            'work_date' => $work_date,
                                            'staff_id' => $row->staff_id
                                        ]) }}"
                                    >編集</a>
                                    <form
                                        method="POST"
                                        action="{{ route('setting.attendance.delete') }}"
                                        class="d-inline"
                                        onsubmit="return confirm('この社員の勤怠を削除しますか？');"
                                    >
                                        @csrf
                                        <input type="hidden" name="staff_id" value="{{ $row->staff_id }}">
                                        <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                                        <input type="hidden" name="work_date" value="{{ $work_date }}">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">削除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">データなし</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

