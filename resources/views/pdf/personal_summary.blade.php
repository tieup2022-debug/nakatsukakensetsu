<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.fonts')
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 6mm 7mm;
            font-size: 6.5px;
            color: #111;
        }
        .pdf-page { page-break-after: always; }
        .pdf-page:last-child { page-break-after: auto; }
        .person-block {
            margin-bottom: 5mm;
            page-break-inside: avoid;
        }
        .person-block:last-child { margin-bottom: 0; }
        .hdr {
            display: table;
            width: 100%;
            margin-bottom: 2px;
        }
        .hdr-left { display: table-cell; vertical-align: bottom; width: 55%; font-size: 9px; font-weight: 700; }
        .hdr-right { display: table-cell; vertical-align: bottom; text-align: right; font-size: 8px; }
        .staff-line { font-size: 7px; margin: 2px 0 4px; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid th, table.grid td {
            border: 1px solid #333;
            padding: 1px 1px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.15;
        }
        .sum-label { width: 14%; background: #f3f4f6; font-weight: 700; font-size: 5.5px; }
        .sum-val { font-size: 6px; }
        .day-h { font-size: 5px; padding: 0 !important; }
        .day-h.sun { background: #fde2e4; }
        .row-lbl { width: 11%; background: #f9fafb; font-size: 5.5px; font-weight: 700; }
        .day-cell { font-size: 5px; padding: 0 1px !important; }
        .section-gap { height: 3px; }
    </style>
</head>
<body>
@foreach($pages as $pageIndex => $pagePeople)
    <div class="pdf-page">
        @foreach($pagePeople as $person)
            @php
                $firstHalf = array_slice($date_list, 0, 15);
                $secondHalf = array_slice($date_list, 15);
            @endphp
            <div class="person-block">
                <div class="hdr">
                    <div class="hdr-left">出勤表（{{ $period_label }}）</div>
                    <div class="hdr-right">{{ $company_name }}</div>
                </div>
                <div class="staff-line">
                    @php $sid = (string) ($person['staff_id'] ?? ''); @endphp
                    {{ strlen($sid) <= 4 ? str_pad($sid, 4, '0', STR_PAD_LEFT) : $sid }}
                    &nbsp;&nbsp;{{ $person['staff_name'] ?? '' }}
                </div>

                {{-- 月間サマリー --}}
                <table class="grid" style="margin-bottom: 3px;">
                    <tr>
                        <th class="sum-label">平日出勤</th>
                        <th class="sum-label">休日出勤</th>
                        <th class="sum-label">普通時間</th>
                        <th class="sum-label">時間外</th>
                        <th class="sum-label">休日時間</th>
                        <th class="sum-label">(深夜)</th>
                    </tr>
                    <tr>
                        <td class="sum-val">{{ (int) ($person['weekday_work_days'] ?? 0) }}日</td>
                        <td class="sum-val">{{ (int) ($person['holiday_work_days'] ?? 0) }}日</td>
                        <td class="sum-val">{{ number_format((($person['normal_minutes'] ?? 0) / 60), 2, '.', '') }}時間</td>
                        <td class="sum-val">{{ number_format((($person['overtime_minutes'] ?? 0) / 60), 2, '.', '') }}時間</td>
                        <td class="sum-val">{{ number_format((($person['holiday_minutes'] ?? 0) / 60), 2, '.', '') }}時間</td>
                        <td class="sum-val">{{ number_format((($person['midnight_minutes'] ?? 0) / 60), 2, '.', '') }}時間</td>
                    </tr>
                </table>

                @foreach([$firstHalf, $secondHalf] as $halfDates)
                    @if(count($halfDates) === 0)
                    @else
                    <table class="grid" style="margin-bottom: 2px;">
                        <tr>
                            <th class="row-lbl"></th>
                            @foreach($halfDates as $d)
                                @php
                                    $dow = (int) date('N', strtotime($d));
                                    $isSun = $dow === 7;
                                    $md = (int) substr($d, 5, 2) . '/' . (int) substr($d, 8, 2);
                                @endphp
                                <th class="day-h {{ $isSun ? 'sun' : '' }}">{{ $md }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="row-lbl">普通</td>
                            @foreach($halfDates as $d)
                                @php $c = $person['daily'][$d] ?? []; @endphp
                                <td class="day-cell">{{ $c['normal'] ?? '0.00' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="row-lbl">時間外</td>
                            @foreach($halfDates as $d)
                                @php $c = $person['daily'][$d] ?? []; @endphp
                                <td class="day-cell">{{ $c['overtime'] ?? '0.00' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="row-lbl">休日</td>
                            @foreach($halfDates as $d)
                                @php $c = $person['daily'][$d] ?? []; @endphp
                                <td class="day-cell">{{ $c['holiday'] ?? '0.00' }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="row-lbl">(深夜)</td>
                            @foreach($halfDates as $d)
                                @php $c = $person['daily'][$d] ?? []; @endphp
                                <td class="day-cell">{{ $c['midnight'] ?? '0.00' }}</td>
                            @endforeach
                        </tr>
                    </table>
                    @endif
                @endforeach
            </div>
            @if(!$loop->last)
                <div class="section-gap"></div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
