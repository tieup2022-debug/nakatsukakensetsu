@extends('layouts.app')

@section('content')
    <style>
        .monthly-wrap { overflow-x: auto; background: #fff; border: 1px solid #dbe3ea; border-radius: 8px; }
        .monthly-table { border-collapse: collapse; min-width: 1600px; width: 100%; font-size: 12px; }
        .monthly-table th, .monthly-table td { border: 1px solid #8b8f96; padding: 2px 4px; text-align: center; white-space: nowrap; }
        .monthly-title { background: #2f8dde; color: #fff; font-weight: 700; text-align: center; }
        .monthly-head { background: #eef3f8; font-weight: 700; }
        .staff-col { min-width: 92px; font-weight: 700; }
        .label-col { min-width: 56px; background: #f7f9fc; font-weight: 700; }
        .day-col { min-width: 40px; }
        .sun { color: #d43f3a; }
        .sat { color: #2a74d4; }
    </style>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠（月次表）</h1>
            <div class="text-muted small">添付イメージ形式の一覧表示</div>
        </div>
        <div>
            <a
                class="btn btn-outline-secondary btn-sm"
                href="{{ route('top.attendance', array_filter(['workplace_id' => $filter_workplace_id ?? null, 'work_date' => $filter_work_date ?? null], fn ($v) => $v !== null && $v !== '')) }}"
            >勤怠へ戻る</a>
        </div>
    </div>

    @php
        $weekdays = ['月', '火', '水', '木', '金', '土', '日'];
    @endphp

    @foreach(($attendance_table_list ?? []) as $pageIndex => $pageStaffList)
        <div class="monthly-wrap mb-3">
            <table class="monthly-table">
                <tr>
                    <th class="monthly-title" colspan="{{ count($date_list ?? []) + 2 }}">勤怠</th>
                </tr>
                <tr>
                    <th class="monthly-head" colspan="{{ count($date_list ?? []) + 2 }}">{{ $display_date ?? '' }}　出　面　表</th>
                </tr>
                <tr>
                    <th class="monthly-head staff-col"></th>
                    <th class="monthly-head label-col"></th>
                    @foreach(($date_list ?? []) as $date)
                        @php
                            $dow = $weekdays[(int)date('N', strtotime($date)) - 1];
                            $d = (int)substr($date, 8, 2);
                            $cls = $dow === '日' ? 'sun' : ($dow === '土' ? 'sat' : '');
                        @endphp
                        <th class="monthly-head day-col {{ $cls }}">{{ $d }}/{{ $dow }}</th>
                    @endforeach
                </tr>

                @foreach($pageStaffList as $staffRow)
                    @php $staffName = $staffRow['staff_name'] ?? ''; @endphp
                    @foreach(['現場', '出勤', '退勤', '休憩', '実働'] as $rowIdx => $label)
                        <tr>
                            @if($rowIdx === 0)
                                <td class="staff-col" rowspan="5">{{ $staffName }}</td>
                            @endif
                            <td class="label-col">{{ $label }}</td>
                            @foreach(($date_list ?? []) as $date)
                                @php
                                    $cell = $staffRow[$date] ?? null;
                                    $value = '';
                                    if ($cell) {
                                        if (($cell['workplace_name'] ?? '') === '#absence') {
                                            $value = $label === '現場' ? '欠' : '';
                                        } elseif ($label === '現場') {
                                            $value = $cell['workplace_name'] ?? '';
                                        } elseif ($label === '出勤') {
                                            $value = $cell['start_time'] ?? '';
                                        } elseif ($label === '退勤') {
                                            $value = $cell['end_time'] ?? '';
                                        } elseif ($label === '休憩') {
                                            $value = $cell['break_time'] ?? '';
                                        } elseif ($label === '実働') {
                                            $value = '';
                                        }
                                    }
                                @endphp
                                <td class="day-col">{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </table>
        </div>
    @endforeach
@endsection
