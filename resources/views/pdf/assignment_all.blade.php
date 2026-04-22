<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @if(!empty($web_preview))
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body, table, th, td, h1, h2, h3, div, span, strong, b {
                font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic UI", "Meiryo", "Noto Sans JP", sans-serif;
            }
        </style>
    @else
        @include('pdf.partials.fonts')
    @endif
    <style>
        * { box-sizing: border-box; }
        body {
            font-size: 9px;
            margin: 8px 10px;
            color: #111;
        }
        .doc-title {
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 10px 0;
            letter-spacing: 0.02em;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }
        .grid th,
        .grid td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .grid th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: center;
        }
        .wp-head {
            text-align: center;
            font-weight: 700;
        }
        .wp-sub {
            font-weight: 400;
            display: block;
            margin-top: 2px;
            min-height: 10px;
        }
        .cell-muted {
            color: #333;
        }
        .stack {
            line-height: 1.35;
        }
        .section-label {
            font-weight: 700;
            text-align: center;
            background: #f9fafb;
        }
        .page-break {
            page-break-after: always;
        }
        .company-footer {
            text-align: right;
            font-size: 11px;
            font-weight: 700;
            margin-top: 12px;
            padding-right: 6px;
        }
        .col-abs {
            width: 9%;
        }
        @if(!empty($web_preview))
        body {
            min-width: 960px;
        }
        .preview-toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #f8fafc;
            border-bottom: 1px solid #cbd5e1;
            padding: 8px 12px;
            margin: -8px -10px 12px -10px;
            font-size: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .preview-toolbar a {
            color: #1d4ed8;
            text-decoration: none;
        }
        .preview-toolbar a:hover {
            text-decoration: underline;
        }
        .preview-toolbar button {
            font: inherit;
            cursor: pointer;
            padding: 4px 10px;
            border: 1px solid #94a3b8;
            border-radius: 4px;
            background: #fff;
        }
        .preview-toolbar button:hover {
            background: #f1f5f9;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 4px;
            }
        }
        @endif
    </style>
</head>
<body>
@if(!empty($web_preview))
    <div class="preview-toolbar no-print">
        <a href="{{ $assignment_list_url ?? route('top.assignment') }}">← 配置一覧に戻る</a>
        <span style="color:#94a3b8">|</span>
        <button type="button" onclick="window.print()">印刷</button>
        <span style="color:#94a3b8">|</span>
        <a href="{{ $assignment_pdf_url ?? '#' }}">PDFでダウンロード</a>
    </div>
@endif
@php
    $pdf_data_list = is_array($pdf_data_list ?? null) ? $pdf_data_list : [];
    $absenceFlat = $pdf_data_list['absence_list'] ?? [];
    $pages = collect($pdf_data_list)->except(['absence_list']);
    $absenceText = collect($absenceFlat)->filter(fn ($n) => $n !== null && $n !== '')->implode("\n");
@endphp

@foreach($pages as $pageIdx => $page)
    @if(!$loop->first)
        <div class="page-break"></div>
    @endif

    <div class="doc-title">
        社員 ・ 作業員 ・ 重機 ・ 車両配置一覧 ( {{ $display_date ?? '' }} )
    </div>

    <table class="grid">
        <thead>
            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <th class="wp-head">
                        現場
                        <span class="wp-sub">{{ $wp['workplace_name'] ?? '' }}</span>
                    </th>
                @endfor
                <th class="col-abs">欠勤予定者</th>
            </tr>
        </thead>
        <tbody>
            @for($r = 0; $r < 3; $r++)
                <tr>
                    @for($i = 1; $i <= 8; $i++)
                        @php
                            $wp = $page['workplace'.$i] ?? [];
                            $tech = $wp['technitian_list'] ?? [];
                            $name = $tech[$r] ?? '';
                        @endphp
                        <td>
                            @if($name !== '')
                                技術者 {{ $name }}
                            @else
                                <span class="cell-muted">技術者</span>
                            @endif
                        </td>
                    @endfor
                    @if($r === 0)
                        <td rowspan="3" class="stack" style="white-space: pre-wrap;">{{ $absenceText }}</td>
                    @endif
                </tr>
            @endfor

            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <td class="stack">
                        @foreach($wp['worker_list'] ?? [] as $w)
                            @if(!empty($w['staff_name']))
                                {{ $w['staff_type'] }} {{ $w['staff_name'] }}<br>
                            @endif
                        @endforeach
                    </td>
                @endfor
                <td></td>
            </tr>

            <tr>
                @for($i = 1; $i <= 8; $i++)
                    <td class="section-label">重機</td>
                @endfor
                <td></td>
            </tr>
            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <td class="stack">
                        @foreach($wp['equipment_list'] ?? [] as $e)
                            @if(!empty($e['vehicle_name']))
                                {{ $e['vehicle_name'] }}<br>
                            @endif
                        @endforeach
                    </td>
                @endfor
                <td></td>
            </tr>

            <tr>
                @for($i = 1; $i <= 8; $i++)
                    <td class="section-label">車両</td>
                @endfor
                <td></td>
            </tr>
            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <td class="stack">
                        @foreach($wp['vehicle_list'] ?? [] as $v)
                            @if(!empty($v['vehicle_name']))
                                {{ $v['vehicle_name'] }}<br>
                            @endif
                        @endforeach
                    </td>
                @endfor
                <td></td>
            </tr>
        </tbody>
    </table>
@endforeach

<div class="page-break"></div>
<div class="company-footer">中塚建設株式会社</div>
</body>
</html>
