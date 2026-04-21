@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 no-print">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠月次一覧</h1>
            <div class="text-muted small">
                対象月: {{ $display_date ?? '' }}　出力日: {{ $today ?? '' }}
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.monthly.form', ['work_date' => $work_date ?? date('Y-m-d')]) }}">出力フォームへ戻る</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">勤怠管理へ</a>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">印刷</button>
            <form method="POST" action="{{ route('setting.attendance.monthly.download') }}" class="d-inline">
                @csrf
                <input type="hidden" name="work_date" value="{{ $work_date ?? date('Y-m-d') }}">
                <button type="submit" class="btn btn-primary btn-sm">PDFでダウンロード</button>
            </form>
        </div>
    </div>

    <style>
        .monthly-web-doc {
            font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic UI", "Meiryo", "Noto Sans JP", sans-serif;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem 1rem 1.5rem;
            overflow-x: auto;
        }
        .monthly-web-doc h2 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 0.75rem;
        }
        .monthly-web-doc .meta {
            font-size: 0.8125rem;
            color: #64748b;
            margin-bottom: 1rem;
        }
        .monthly-web-doc table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            min-width: 720px;
        }
        .monthly-web-doc th,
        .monthly-web-doc td {
            border: 1px solid #e5e7eb;
            padding: 4px 5px;
        }
        .monthly-web-doc th {
            background: #f8fafc;
            text-align: center;
            font-weight: 600;
        }
        .monthly-web-doc .staff-name {
            width: 7rem;
            white-space: nowrap;
        }
        .monthly-web-doc .day-cell {
            width: 1.65rem;
            text-align: center;
        }
        .monthly-web-doc .day-head {
            font-size: 0.6875rem;
        }
        .monthly-web-doc .page-gap {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px dashed #cbd5e1;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff !important;
            }
            .monthly-web-doc {
                border: none;
                padding: 0;
            }
        }
    </style>

    <div class="monthly-web-doc">
        <h2 class="d-none d-print-block">勤怠月次一覧</h2>
        <div class="meta d-print-block">
            対象月: {{ $display_date ?? '' }}　出力日: {{ $today ?? '' }}
        </div>

        @foreach($attendance_table_list as $pageIndex => $pageStaffList)
            @if($pageIndex > 0)
                <div class="page-gap"></div>
            @endif
            <table>
                <thead>
                    <tr>
                        <th class="staff-name">氏名</th>
                        @foreach($date_list as $date)
                            @php
                                $day = (int)substr($date, 8, 2);
                            @endphp
                            <th class="day-cell day-head">{{ $day }}</th>
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
        @endforeach
    </div>
@endsection
