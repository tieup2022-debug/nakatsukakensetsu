<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.fonts')
    <style>
        /* A4縦・3名/ページ：左右はプリンタ非印刷域を避けるため少し広め */
        @page { margin: 2mm 6mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-size: 7.5pt;
            color: #111;
        }
        /* 印刷可能高さ（A4 297mm − @page 上下 4mm 目安）に合わせ、3ブロック間に余白を分配 */
        .pdf-page {
            page-break-after: always;
            min-height: 289mm;
            height: 289mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .pdf-page:last-child { page-break-after: auto; }
        /* 1〜2名のみのページは縦伸ばししない（大きな隙間を避ける） */
        .pdf-page--compact {
            height: auto;
            min-height: 0;
            justify-content: flex-start;
        }
        .pdf-page--compact .person-block + .person-block {
            margin-top: 2mm;
        }
        .person-block {
            flex: 0 0 auto;
            margin: 0;
            page-break-inside: avoid;
        }
        .hdr {
            display: table;
            width: 100%;
            margin-bottom: 0.3mm;
        }
        .hdr-left {
            display: table-cell;
            vertical-align: bottom;
            width: 58%;
            font-size: 10pt;
            font-weight: 700;
        }
        .hdr-right {
            display: table-cell;
            vertical-align: bottom;
            text-align: right;
            font-size: 9pt;
        }
        .staff-line { font-size: 8.5pt; font-weight: 700; margin: 0 0 0.8mm; }
        table.grid {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.grid-sum th, table.grid-sum td {
            border: 1px solid #222;
            padding: 2px 3px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.2;
        }
        table.grid-daily th, table.grid-daily td {
            border: 1px solid #222;
            padding: 1px 2px;
            text-align: center;
            vertical-align: middle;
            line-height: 1.18;
        }
        .sum-label {
            width: 16.66%;
            background: #eef1f5;
            font-weight: 700;
            font-size: 7pt;
        }
        .sum-val { font-size: 8pt; }
        .day-h { font-size: 6.2pt; padding: 1px 0 !important; }
        .day-h.sun { background: #fde2e4; }
        .row-lbl {
            width: 7%;
            min-width: 0;
            background: #f4f6f8;
            font-size: 6.2pt;
            font-weight: 700;
        }
        .day-cell { font-size: 6.8pt; padding: 1px 1px !important; }
        .mb-sum { margin-bottom: 0.6mm; }
        .mb-daily { margin-bottom: 0.6mm; }
    </style>
</head>
<body>
@foreach($pages as $pageIndex => $pagePeople)
    <div class="pdf-page {{ count($pagePeople) < 3 ? 'pdf-page--compact' : '' }}">
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

                {{-- 月間サマリー（休日時間はここで確認。日次グリッドは縦スペースのため3行のみ） --}}
                <table class="grid grid-sum mb-sum">
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
                    <table class="grid grid-daily mb-daily">
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
        @endforeach
    </div>
@endforeach
</body>
</html>
