@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">欠勤者管理（作業日: {{ $work_date }}）</h1>
            <div class="text-muted small">配置に入っていないスタッフの欠勤設定を管理します。</div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.absence.workdate') }}">日付へ</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.attendance.absence.update') }}">
                @csrf
                <input type="hidden" name="work_date" value="{{ $work_date }}">

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="min-width: 220px;">社員</th>
                                <th style="width: 140px;">欠勤</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($staff_list as $staff)
                                @php
                                    $isAbsent = isset($staff['absence_flg']) && intval($staff['absence_flg']) === 1;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="fw-medium">{{ $staff['staff_name'] ?? '' }}</div>
                                        <input type="hidden" name="staff_id_list[]" value="{{ $staff['staff_id'] ?? '' }}">
                                    </td>
                                    <td>
                                        <input type="hidden" name="staff_list[{{ $staff['staff_id'] ?? '' }}]" value="0">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            name="staff_list[{{ $staff['staff_id'] ?? '' }}]"
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
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-primary" type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

