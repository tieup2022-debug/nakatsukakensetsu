@extends('layouts.app')

@section('content')
    <style>
        .app-main > main { min-width: 0; }
        .app-main > main > .container-fluid { min-width: 0; max-width: 100%; }
        .monthly-wrap { width: 100%; max-width: 100%; overflow-x: auto; background: #fff; border: 1px solid #dbe3ea; border-radius: 8px; }
        /* table-layout: fixed + colgroup により、全テーブルで列幅を完全に揃え、縦罫線がずれないようにする */
        .monthly-table { border-collapse: collapse; table-layout: fixed; width: 100%; font-size: 12px; }
        .monthly-table th, .monthly-table td { border: 1px solid #8b8f96; padding: 2px 4px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .monthly-title { background: #2f8dde; color: #fff; font-weight: 700; text-align: center; }
        .monthly-head { background: #eef3f8; font-weight: 700; }
        /* 社員名は横スクロール時も見えるよう左端に固定し、長くても折り返して全て見えるようにする */
        .monthly-table .staff-col { position: sticky; left: 0; z-index: 3; background: #fff; font-weight: 700; white-space: normal; word-break: break-word; }
        .monthly-table th.staff-col { z-index: 4; background: #eef3f8; }
        /* 先頭の【…】部分は名前の上の行に小さめで表示する */
        .staff-col .staff-prefix { display: block; font-size: 10px; font-weight: 700; line-height: 1.2; }
        .staff-col .staff-name { display: block; line-height: 1.2; }
        /* 現場〜実働のラベル列も氏名列の右隣に固定する（氏名列幅 120px 分ずらす） */
        .monthly-table .label-col { position: sticky; left: 120px; z-index: 3; background: #f7f9fc; font-weight: 700; box-shadow: 2px 0 3px rgba(15, 23, 42, 0.12); }
        .monthly-table th.label-col { z-index: 4; background: #eef3f8; }
        .monthly-table td.day-col { padding: 0; }
        /* 現場名（長くなりがちな行）は列幅を変えずに全文を見せるため、セル内で折り返して収める */
        .monthly-table td.day-col.site-cell { overflow: visible; white-space: normal; }
        .monthly-table td.day-col.site-cell .cell-edit-link { white-space: normal; overflow-wrap: anywhere; word-break: break-all; line-height: 1.15; font-size: 10px; letter-spacing: -0.2px; padding: 2px 2px; }
        .cell-edit-link { display: block; padding: 2px 4px; min-height: 1em; color: inherit; text-decoration: none; cursor: pointer; }
        .cell-edit-link:hover { background: #fff3cd; text-decoration: none; color: inherit; }
        .sun { color: #d43f3a; }
        .sat { color: #2a74d4; }
        /* 未保存日の参考時刻: 保存済みと区別できるようグレー・斜体で表示 */
        .cell-edit-link.ref-time { color: #9aa0a6; font-style: italic; }
    </style>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠（月次表）</h1>
        </div>
        <div>
            <a
                class="btn btn-outline-secondary btn-sm"
                href="{{ route('top.attendance', array_filter(['workplace_id' => $filter_workplace_id ?? null, 'work_date' => $filter_work_date ?? null], fn ($v) => $v !== null && $v !== '')) }}"
            >勤怠へ戻る</a>
        </div>
    </div>

    <div class="text-muted small mb-2">
        各セルをクリックすると、その日の入力画面（現場・作業日）へ移動して時間を編集できます。<br>
        <span style="color: #9aa0a6; font-style: italic;">グレーの斜体時刻</span>は未保存の参考表示（初期時間）です。勤怠入力画面で保存すると確定します。
    </div>

    @php
        $weekdays = ['月', '火', '水', '木', '金', '土', '日'];
    @endphp

    @foreach(($attendance_table_list ?? []) as $pageIndex => $pageStaffList)
        <div class="monthly-wrap mb-3">
            <table class="monthly-table" style="min-width: {{ 176 + 64 * count($date_list ?? []) }}px;">
                <colgroup>
                    <col style="width: 120px;">
                    <col style="width: 56px;">
                    @foreach(($date_list ?? []) as $date)
                        <col style="width: 64px;">
                    @endforeach
                </colgroup>
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
                    @php
                        $staffName = $staffRow['staff_name'] ?? '';
                        // 先頭の【…】部分は名前の上の行に分けて表示する
                        $staffPrefix = '';
                        $staffMain = $staffName;
                        if (preg_match('/^(【[^】]*】)(.*)$/u', $staffName, $m)) {
                            $staffPrefix = $m[1];
                            $staffMain = $m[2];
                        }
                    @endphp
                    @foreach(['現場', '出勤', '退勤', '休憩', '実働'] as $rowIdx => $label)
                        <tr>
                            @if($rowIdx === 0)
                                <td class="staff-col" rowspan="5">
                                    @if($staffPrefix !== '')
                                        <span class="staff-prefix">{{ $staffPrefix }}</span>
                                    @endif
                                    <span class="staff-name">{{ $staffMain }}</span>
                                </td>
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
                                            $value = $cell['worked_time'] ?? '';
                                        }
                                    }

                                    $cellWorkplaceId = (int) ($cell['workplace_id'] ?? 0);
                                    $editParams = ['work_date' => $date];
                                    if ($cellWorkplaceId > 0) {
                                        $editParams['workplace_id'] = $cellWorkplaceId;
                                    }
                                    $editHref = route('top.attendance', $editParams);
                                    $editTitle = $staffName.'／'.((int) substr($date, 8, 2)).'日 の勤怠を編集';
                                    // 未保存日の参考時刻（時刻系の行のみ）はグレー斜体で表示する
                                    $isReference = !empty($cell['is_reference'])
                                        && in_array($label, ['出勤', '退勤', '休憩', '実働'], true);
                                    if ($isReference) {
                                        $editTitle .= '（未保存・参考時刻）';
                                    }
                                @endphp
                                <td class="day-col{{ $label === '現場' ? ' site-cell' : '' }}">
                                    <a href="{{ $editHref }}" class="cell-edit-link{{ $isReference ? ' ref-time' : '' }}" title="{{ $editTitle }}">{{ $value !== '' ? $value : ' ' }}</a>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </table>
        </div>
    @endforeach
@endsection
