<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.fonts')
    <style>
        body { font-size: 12px; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        .meta { margin-bottom: 10px; }
        .meta div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; }
        th { background: #f8fafc; text-align: left; }
        .status { width: 80px; text-align: center; }
    </style>
</head>
<body>
    <h1>勤怠</h1>

    <div class="meta">
        <div>日付: {{ $display_date ?? $work_date ?? '' }}</div>
        <div>現場: {{ $workplace_name ?? '' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>社員</th>
                <th style="width: 120px;">出勤</th>
                <th style="width: 120px;">退勤</th>
                <th style="width: 120px;">休憩</th>
                <th class="status" style="width: 100px;">欠勤</th>
            </tr>
        </thead>
        <tbody>
        @forelse($attendance_data as $row)
            @php
                $start = $row->start_time ?? '';
                $end = $row->end_time ?? '';
                $breakTime = $row->break_time ?? '';
                $isAbsent = isset($row->absence_flg) && intval($row->absence_flg) === 1;
            @endphp
            <tr>
                <td>{{ $row->staff_name ?? '' }}</td>
                <td>{{ $start ? substr((string)$start, 0, 5) : '' }}</td>
                <td>{{ $end ? substr((string)$end, 0, 5) : '' }}</td>
                <td>{{ $breakTime ? substr((string)$breakTime, 0, 5) : '' }}</td>
                <td class="status">{{ $isAbsent ? '○' : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">データなし</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>

