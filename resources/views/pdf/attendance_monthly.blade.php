<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.fonts')
    <style>
        body { font-size: 9px; }
        h1 { font-size: 14px; margin: 0 0 8px; }
        .meta { margin-bottom: 8px; }
        .meta div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
        th, td { border: 1px solid #e5e7eb; padding: 2px 3px; }
        th { background: #f8fafc; text-align: center; }
        .staff-name { width: 90px; }
        .day-cell { width: 22px; text-align: center; }
        .small { font-size: 8px; }
        .page-break { page-break-after: always; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h1>勤怠月次一覧</h1>

    <div class="meta">
        <div>対象月: {{ $display_date ?? '' }}</div>
        <div>出力日: {{ $today ?? '' }}</div>
    </div>

    @foreach($attendance_table_list as $pageIndex => $pageStaffList)
        <table>
            <thead>
                <tr>
                    <th class="staff-name">氏名</th>
                    @foreach($date_list as $date)
                        @php
                            $day = (int)substr($date, 8, 2);
                        @endphp
                        <th class="day-cell small">{{ $day }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($pageStaffList as $staffRow)
                    @php
                        $staffName = $staffRow['staff_name'] ?? '';
                    @endphp
                    <tr>
                        <td class="staff-name">{{ $staffName }}</td>
                        @foreach($date_list as $date)
                            @php
                                $cell = $staffRow[$date] ?? null;
                                $mark = '';
                                if ($cell) {
                                    if (($cell['workplace_name'] ?? '') === '#absence') {
                                        $mark = '欠';
                                    } elseif (!empty($cell['workplace_name'])) {
                                        $mark = '○';
                                    } elseif (!empty($cell['absence'] ?? '')) {
                                        $mark = '休';
                                    }
                                }
                            @endphp
                            <td class="day-cell">{{ $mark }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>

