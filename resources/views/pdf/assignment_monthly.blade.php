<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @include('pdf.partials.fonts')
    <style>
        body { font-size: 9px; }
        h1 { font-size: 14px; margin: 0 0 6px; }
        .meta { margin-bottom: 6px; }
        .meta div { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 2px 3px; }
        th { background: #f8fafc; text-align: center; }
        .workplace-title { font-weight: bold; text-align: left; }
        .small { font-size: 8px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <h1>配置一覧</h1>
    <div class="meta">
        <div>日付: {{ $display_date ?? '' }}</div>
        <div>出力日: {{ $today ?? '' }}</div>
    </div>

    @foreach($pdf_data_list as $pageIndex => $page)
        @if($pageIndex === 'absence_list')
            @continue
        @endif

        <table>
            <tbody>
            @foreach($page as $key => $workplace)
                @php
                    $name = $workplace['workplace_name'] ?? '';
                    if ($name === '') continue;
                @endphp
                <tr>
                    <td colspan="3" class="workplace-title small">{{ $name }}</td>
                </tr>
                <tr>
                    <th class="small" style="width: 20%;">技術担当</th>
                    <th class="small" style="width: 40%;">作業員</th>
                    <th class="small" style="width: 40%;">車両・重機</th>
                </tr>
                <tr>
                    <td class="small" valign="top">
                        @foreach($workplace['technitian_list'] ?? [] as $t)
                            @if($t) {{ $t }}<br>@endif
                        @endforeach
                    </td>
                    <td class="small" valign="top">
                        @foreach($workplace['worker_list'] ?? [] as $w)
                            @php
                                $type = $w['staff_type'] ?? '';
                                $staffName = $w['staff_name'] ?? '';
                            @endphp
                            @if($type || $staffName)
                                {{ $type ? $type . '：' : '' }}{{ $staffName }}<br>
                            @endif
                        @endforeach
                    </td>
                    <td class="small" valign="top">
                        @foreach($workplace['vehicle_list'] ?? [] as $v)
                            @if(!empty($v['vehicle_name'])) {{ $v['vehicle_name'] }}<br>@endif
                        @endforeach
                        @foreach($workplace['equipment_list'] ?? [] as $e)
                            @if(!empty($e['vehicle_name'])) {{ $e['vehicle_name'] }}<br>@endif
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach

    @if(!empty($pdf_data_list['absence_list'] ?? []))
        <div class="page-break"></div>
        <h2 class="small">欠勤者一覧</h2>
        <table>
            <tbody>
            @foreach($pdf_data_list['absence_list'] as $name)
                @if($name)
                    <tr>
                        <td class="small">{{ $name }}</td>
                    </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>

