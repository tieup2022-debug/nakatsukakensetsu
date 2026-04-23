@extends('layouts.app')

@section('content')
    @php
        $todayDate = date('Y-m-d');
    @endphp
    <style>
        .personal-wrap { overflow-x: auto; background: #fff; border: 1px solid #dbe3ea; border-radius: 8px; }
        .personal-table { border-collapse: collapse; min-width: 1400px; width: 100%; font-size: 12px; }
        .personal-table th, .personal-table td { border: 1px solid #8b8f96; padding: 3px 5px; text-align: center; white-space: nowrap; }
        .head-title { background: #eef3f8; font-weight: 700; }
        .staff-col { min-width: 150px; text-align: left; font-weight: 700; }
        .label-col { min-width: 58px; background: #f8fafc; font-weight: 700; }
        .sum-col { min-width: 86px; background: #f8fafc; font-weight: 700; }
        .day-col { min-width: 44px; }
        .abs-cell { background: #fee2e2; color: #b91c1c; font-weight: 700; }
        .sat-col { background: #eaf3ff; }
        .sun-col { background: #ffeef0; }
        /* フィルタ行は左に詰め、更新ボタンが画面外に押し出されないようにする */
        .personal-summary-filter {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 0.5rem 0.75rem;
            max-width: 100%;
        }
        .personal-summary-filter .field-date { width: 10.5rem; max-width: 100%; flex: 0 0 auto; }
        .personal-summary-filter .field-type { width: min(17rem, 100%); max-width: 100%; flex: 0 1 auto; min-width: 0; }
        .personal-summary-filter .field-submit { flex: 0 0 auto; }
    </style>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">個人別集計（月次）</h1>
            <div class="text-muted small">社員ごとの月次集計（出退勤/休憩/実働/普通/休日/深夜）</div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">勤怠管理へ戻る</a>
        </div>
    </div>

    @if(!empty($status))
        <div class="alert alert-warning mb-3">{{ $status }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('setting.attendance.personal.summary') }}" class="personal-summary-filter">
                <div class="field-date">
                    <label class="form-label small text-muted mb-0">基準日</label>
                    <input
                        type="text"
                        name="work_date"
                        class="form-control form-control-sm js-datepicker"
                        value="{{ $work_date }}"
                        data-datepicker-submit
                        readonly
                        autocomplete="off"
                    >
                </div>
                <div class="field-type">
                    <label class="form-label small text-muted mb-0">スタッフ種別</label>
                    <select class="form-select form-select-sm" name="staff_type">
                        <option value="" {{ (string)($selected_staff_type ?? '') === '' ? 'selected' : '' }}>全員</option>
                        <option value="1" {{ (string)($selected_staff_type ?? '') === '1' ? 'selected' : '' }}>スタッフ1（技術者）</option>
                        <option value="2" {{ (string)($selected_staff_type ?? '') === '2' ? 'selected' : '' }}>スタッフ2（OP）</option>
                        <option value="3" {{ (string)($selected_staff_type ?? '') === '3' ? 'selected' : '' }}>スタッフ3（作業員）</option>
                        <option value="4" {{ (string)($selected_staff_type ?? '') === '4' ? 'selected' : '' }}>スタッフ4（総務部）</option>
                    </select>
                </div>
                <div class="field-submit align-self-end d-flex flex-wrap gap-1">
                    <button class="btn btn-outline-primary btn-sm" type="submit">更新</button>
                    @if(!empty($summary_list))
                        @php
                            $pdfQuery = ['work_date' => $work_date];
                            if (($selected_staff_type ?? '') !== '' && ($selected_staff_type ?? '') !== null) {
                                $pdfQuery['staff_type'] = $selected_staff_type;
                            }
                        @endphp
                        <a
                            class="btn btn-outline-secondary btn-sm"
                            href="{{ route('setting.attendance.personal.summary.pdf', $pdfQuery) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >PDF出力</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if(empty($summary_list))
        <div class="card shadow-sm border-0">
            <div class="card-body text-muted">表示できる集計データがありません。</div>
        </div>
    @else
        @foreach($summary_list as $person)
            <div class="personal-wrap mb-3">
                <table class="personal-table">
                    <thead>
                        <tr>
                            <th class="head-title staff-col">社員名</th>
                            <th class="head-title label-col">区分</th>
                            <th class="head-title sum-col">平日出勤</th>
                            <th class="head-title sum-col">休日出勤</th>
                            <th class="head-title sum-col">普通時間</th>
                            <th class="head-title sum-col">時間外</th>
                            <th class="head-title sum-col">休日時間</th>
                            <th class="head-title sum-col">(深夜)</th>
                            @foreach($date_list as $date)
                                @php
                                    $dowClass = '';
                                    $dow = (int)date('N', strtotime($date));
                                    if ($dow === 6) $dowClass = 'sat-col';
                                    if ($dow === 7) $dowClass = 'sun-col';
                                @endphp
                                <th class="head-title day-col {{ $dowClass }}">{{ (int)substr($date, 8, 2) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $rows = [
                                'start' => '出勤時間',
                                'end' => '退勤時間',
                                'break' => '休憩時間',
                                'worked' => '実働時間',
                                'holiday' => '休日',
                                'midnight' => '(深夜)',
                            ];
                        @endphp
                        @foreach($rows as $key => $label)
                            <tr>
                                @if($loop->first)
                                    <td class="staff-col" rowspan="{{ count($rows) }}">{{ $person['staff_name'] }}</td>
                                @endif
                                <td class="label-col">{{ $label }}</td>
                                @if($loop->first)
                                    <td class="sum-col" rowspan="{{ count($rows) }}">{{ $person['weekday_work_days'] }}日</td>
                                    <td class="sum-col" rowspan="{{ count($rows) }}">{{ $person['holiday_work_days'] }}日</td>
                                    <td class="sum-col" rowspan="{{ count($rows) }}">{{ number_format(($person['normal_minutes'] ?? 0) / 60, 2) }}時間</td>
                                    <td class="sum-col" rowspan="{{ count($rows) }}">{{ number_format(($person['overtime_minutes'] ?? 0) / 60, 2) }}時間</td>
                                    <td class="sum-col" rowspan="{{ count($rows) }}">{{ number_format(($person['holiday_minutes'] ?? 0) / 60, 2) }}時間</td>
                                    <td class="sum-col" rowspan="{{ count($rows) }}">{{ number_format(($person['midnight_minutes'] ?? 0) / 60, 2) }}時間</td>
                                @endif
                                @foreach($date_list as $date)
                                    @php
                                        $cell = $person['daily'][$date] ?? null;
                                        $isAbsence = !empty($cell['absence']);
                                        $hasAttendance = !empty($cell['start']) || !empty($cell['end']);
                                        $isPastNoAttendance = !$isAbsence && !$hasAttendance && $date < $todayDate;
                                        $isFutureOrTodayNoAttendance = !$isAbsence && !$hasAttendance && $date >= $todayDate;
                                        $dowClass = '';
                                        $dow = (int)date('N', strtotime($date));
                                        if ($dow === 6) $dowClass = 'sat-col';
                                        if ($dow === 7) $dowClass = 'sun-col';
                                    @endphp
                                    @if($key === 'start' && $isAbsence)
                                        <td class="day-col abs-cell {{ $dowClass }}">休</td>
                                    @elseif($isPastNoAttendance)
                                        <td class="day-col {{ $dowClass }}">{{ $key === 'start' ? '休日' : '' }}</td>
                                    @elseif($isFutureOrTodayNoAttendance)
                                        <td class="day-col {{ $dowClass }}"></td>
                                    @else
                                        <td class="day-col {{ $dowClass }}">
                                            @if(in_array($key, ['start', 'end'], true))
                                                {{ $cell[$key] ?? '' }}
                                            @else
                                                {{ $cell[$key] ?? '0.00' }}
                                            @endif
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
@endsection
