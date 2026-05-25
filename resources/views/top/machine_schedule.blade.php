@extends('layouts.app')

@php
    /** @var array $matrix */
    $dates = $matrix['dates'] ?? [];
    $machines = $matrix['machines'] ?? [];
    $cells = $matrix['cells'] ?? [];
    $workplaces = $matrix['workplaces'] ?? [];

    // 現場ID -> 名前
    $workplaceNameById = [];
    foreach ($workplaces as $w) {
        $workplaceNameById[$w['id']] = $w['name'];
    }

    // 現場ID -> 色（HSL を ID から決定論的に算出）
    $colorFor = function (int $id): string {
        $hue = ($id * 47) % 360;
        return "hsl({$hue}, 70%, 82%)";
    };

    $weekdays = ['日','月','火','水','木','金','土'];
@endphp

@section('content')
    <style>
        .ms-card { padding: 0; }
        .ms-toolbar { padding: .75rem 1rem; }
        .ms-grid-wrap {
            overflow: auto;
            max-height: calc(100vh - 280px);
            border-top: 1px solid #e5e7eb;
        }
        table.ms-grid {
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
            font-size: 12px;
        }
        table.ms-grid th, table.ms-grid td {
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 0;
            margin: 0;
            background: #fff;
        }
        table.ms-grid thead th {
            position: sticky;
            top: 0;
            z-index: 3;
            background: #f8fafc;
            text-align: center;
            font-weight: 500;
            color: #475569;
        }
        table.ms-grid thead th.ms-month-row {
            top: 0;
            height: 22px;
        }
        table.ms-grid thead th.ms-day-row {
            top: 22px;
            height: 32px;
        }
        table.ms-grid th.ms-machine-head,
        table.ms-grid td.ms-machine-cell {
            position: sticky;
            left: 0;
            z-index: 2;
            background: #ffffff;
            border-right: 2px solid #cbd5e1;
        }
        table.ms-grid thead th.ms-machine-head {
            z-index: 4;
            background: #f1f5f9;
        }
        table.ms-grid td.ms-machine-cell {
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 500;
            color: #1e293b;
            white-space: nowrap;
        }
        table.ms-grid td.ms-machine-cell .ms-type {
            display: inline-block;
            font-size: 10px;
            padding: 1px 5px;
            border-radius: 999px;
            margin-right: 4px;
            color: #475569;
            background: #e2e8f0;
        }
        table.ms-grid td.ms-machine-cell .ms-type.type-1 {
            background: #dbeafe;
            color: #1d4ed8;
        }
        table.ms-grid td.ms-machine-cell .ms-type.type-2 {
            background: #fef3c7;
            color: #b45309;
        }
        .ms-col-date {
            width: 36px;
            min-width: 36px;
            max-width: 36px;
        }
        .ms-col-machine {
            width: 200px;
            min-width: 200px;
            max-width: 200px;
        }
        table.ms-grid th.ms-sat, table.ms-grid td.ms-sat { background: #dbeafe; }
        table.ms-grid th.ms-sun, table.ms-grid td.ms-sun { background: #fce7f3; }

        /* ===== スマホ最適化（PC は触らない） ===== */
        @media (max-width: 768px) {
            .ms-toolbar { padding: .5rem .75rem; }
            .ms-toolbar form.row > .col-auto { width: 100%; }
            .ms-toolbar form.row > .col-auto > input,
            .ms-toolbar form.row > .col-auto > select {
                width: 100% !important;
            }
            .ms-toolbar .ms-legend { display: none !important; }

            /* グリッドはスマホで縦も広く使う */
            .ms-grid-wrap {
                max-height: calc(100vh - 200px);
            }

            /* 機械列: 200px → 140px */
            .ms-col-machine {
                width: 140px;
                min-width: 140px;
                max-width: 140px;
            }
            /* 日付列: タップ面確保のため 36px のまま */

            table.ms-grid { font-size: 11px; }
            table.ms-grid td.ms-machine-cell {
                padding: 3px 6px;
                font-size: 11px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            table.ms-grid td.ms-machine-cell .ms-type {
                font-size: 9px;
                padding: 0 4px;
                margin-right: 3px;
            }

            /* ヘッダ行をやや低く */
            table.ms-grid thead th.ms-month-row { height: 20px; font-size: 10px; }
            table.ms-grid thead th.ms-day-row   { height: 30px; font-size: 11px; }
            table.ms-grid thead th.ms-day-row,
            table.ms-grid thead th.ms-month-row {
                top: 0;
            }
            table.ms-grid thead th.ms-day-row { top: 20px; }

            /* バー（指タップ用に少しふっくら） */
            td.ms-cell-filled .ms-bar {
                height: 26px;
                line-height: 26px;
                margin: 3px 0;
                font-size: 10px;
            }

            /* ヘッダーのナビ・タイトル類を詰める */
            h1.h4 { font-size: 1.1rem; }
            .ms-quick-nav .btn { padding: .25rem .5rem; font-size: .8rem; }
        }

        /* さらに極小幅(iPhone SE 等)向け: 機械列をもう一段圧縮 */
        @media (max-width: 420px) {
            .ms-col-machine {
                width: 110px;
                min-width: 110px;
                max-width: 110px;
            }
            table.ms-grid td.ms-machine-cell { font-size: 10px; padding: 3px 4px; }
        }
        td.ms-cell-empty:hover { background: #fef9c3; cursor: pointer; }
        td.ms-cell-filled { cursor: pointer; position: relative; }
        td.ms-cell-filled .ms-bar {
            display: block;
            height: 24px;
            line-height: 24px;
            margin: 4px 0;
            font-size: 11px;
            color: #1e293b;
            text-align: center;
            overflow: hidden;
            white-space: nowrap;
        }
        td.ms-cell-filled .ms-bar.start { border-top-left-radius: 6px; border-bottom-left-radius: 6px; margin-left: 2px; }
        td.ms-cell-filled .ms-bar.end   { border-top-right-radius: 6px; border-bottom-right-radius: 6px; margin-right: 2px; }
        td.ms-cell-filled.is-conflict .ms-bar { outline: 2px solid #dc2626; }
        .ms-legend { font-size: 12px; color: #64748b; }
        .ms-legend .swatch { display:inline-block;width:14px;height:14px;border-radius:3px;background:#e5e7eb;margin-right:4px;vertical-align:-2px; }
        .ms-quick-nav .btn { white-space: nowrap; }
    </style>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">機械配置予定表</h1>
            <div class="text-muted small">
                {{ $start_date }} 〜 {{ $end_date }}（{{ count($dates) }}日間）
            </div>
        </div>
        <div class="ms-quick-nav d-flex flex-wrap align-items-center gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('top.machine.schedule', ['start_date'=>$prev_start,'range'=>$range_key,'vehicle_type'=>$vehicle_type_param]) }}">← 前期間</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('top.machine.schedule', ['range'=>$range_key,'vehicle_type'=>$vehicle_type_param]) }}">今日へ</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('top.machine.schedule', ['start_date'=>$next_start,'range'=>$range_key,'vehicle_type'=>$vehicle_type_param]) }}">次期間 →</a>
        </div>
    </div>

    <div class="card shadow-sm border-0 ms-card">
        <div class="card-body ms-toolbar">
            <form method="GET" action="{{ route('top.machine.schedule') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">開始日</label>
                    <input type="text" name="start_date" class="form-control form-control-sm js-datepicker" value="{{ $start_date }}" readonly autocomplete="off" style="width: 150px;">
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">表示期間</label>
                    <select name="range" class="form-select form-select-sm" style="width: 120px;">
                        @foreach($presets as $key => $p)
                            <option value="{{ $key }}" {{ $key === $range_key ? 'selected' : '' }}>{{ $p['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small text-muted mb-1">種別</label>
                    <select name="vehicle_type" class="form-select form-select-sm" style="width: 120px;">
                        <option value="all" {{ $vehicle_type_param === 'all' ? 'selected' : '' }}>車両＋重機</option>
                        <option value="1" {{ $vehicle_type_param === '1' ? 'selected' : '' }}>車両のみ</option>
                        <option value="2" {{ $vehicle_type_param === '2' ? 'selected' : '' }}>重機のみ</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-sm" type="submit">表示</button>
                </div>
                <div class="col text-end ms-legend">
                    セルをクリック → 期間配置／クリア。色は現場ごと。
                </div>
            </form>
        </div>

        <div class="ms-grid-wrap">
            @if(empty($machines) || empty($dates))
                <div class="p-4 text-muted small">表示対象がありません。マスタ（車両・重機・現場）を登録してください。</div>
            @else
                <table class="ms-grid">
                    <colgroup>
                        <col class="ms-col-machine">
                        @foreach($dates as $d)
                            <col class="ms-col-date">
                        @endforeach
                    </colgroup>
                    <thead>
                        {{-- 月行（連続する同月のヘッダーを結合表示） --}}
                        <tr>
                            <th class="ms-machine-head ms-month-row" rowspan="2">機械</th>
                            @php
                                $monthGroups = [];
                                $prevMonth = null;
                                foreach ($dates as $i => $d) {
                                    $m = substr($d['date'], 0, 7);
                                    if ($m !== $prevMonth) {
                                        $monthGroups[] = ['month' => $m, 'count' => 1];
                                        $prevMonth = $m;
                                    } else {
                                        $monthGroups[count($monthGroups)-1]['count']++;
                                    }
                                }
                            @endphp
                            @foreach($monthGroups as $g)
                                @php
                                    [$y, $mo] = explode('-', $g['month']);
                                @endphp
                                <th class="ms-month-row" colspan="{{ $g['count'] }}">{{ (int)$y }}年{{ (int)$mo }}月</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach($dates as $d)
                                @php
                                    $cls = 'ms-day-row';
                                    if ($d['is_sat']) $cls .= ' ms-sat';
                                    if ($d['is_sun']) $cls .= ' ms-sun';
                                @endphp
                                <th class="{{ $cls }}" title="{{ $d['date'] }}">
                                    <div style="font-size: 10px; color: #94a3b8;">{{ $d['wd'] }}</div>
                                    <div>{{ explode('/', $d['d'])[1] ?? $d['d'] }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($machines as $m)
                            @php
                                $row = $cells[$m['id']] ?? [];
                                $typeLabel = $m['vehicle_type'] === 2 ? '重' : '車';
                                $typeCls = $m['vehicle_type'] === 2 ? 'type-2' : 'type-1';
                            @endphp
                            <tr>
                                <td class="ms-machine-cell">
                                    <span class="ms-type {{ $typeCls }}">{{ $typeLabel }}</span>{{ $m['name'] }}
                                </td>
                                @foreach($dates as $d)
                                    @php
                                        $cell = $row[$d['date']] ?? null;
                                        $cls = '';
                                        if ($d['is_sat']) $cls .= ' ms-sat';
                                        if ($d['is_sun']) $cls .= ' ms-sun';
                                        if ($cell) {
                                            $cls .= ' ms-cell-filled';
                                            $color = $colorFor((int)$cell['workplace_id']);
                                            $barCls = '';
                                            if (!empty($cell['start'])) $barCls .= ' start';
                                            if (!empty($cell['end']))   $barCls .= ' end';
                                            $showName = !empty($cell['start']);
                                        } else {
                                            $cls .= ' ms-cell-empty';
                                        }
                                    @endphp
                                    <td
                                        class="{{ trim($cls) }}"
                                        data-machine-id="{{ $m['id'] }}"
                                        data-machine-name="{{ $m['name'] }}"
                                        data-master-type="{{ $m['master_type'] }}"
                                        data-date="{{ $d['date'] }}"
                                        @if($cell)
                                            data-workplace-id="{{ $cell['workplace_id'] }}"
                                            data-workplace-name="{{ $cell['workplace_name'] }}"
                                        @endif
                                    >
                                        @if($cell)
                                            <span class="ms-bar{{ $barCls }}" style="background: {{ $color }};">{{ $showName ? $cell['workplace_name'] : '' }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- セル編集モーダル --}}
    <div class="modal fade" id="msEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">機械配置の編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="msPlaceForm" method="POST" action="{{ route('top.machine.schedule.place') }}">
                    @csrf
                    <input type="hidden" name="master_id" id="msMasterId">
                    <input type="hidden" name="master_type" id="msMasterType">
                    {{-- 戻り先（現在の表示条件をそのまま保持） --}}
                    <input type="hidden" name="view_start_date" value="{{ $start_date }}">
                    <input type="hidden" name="view_range" value="{{ $range_key }}">
                    <input type="hidden" name="view_vehicle_type" value="{{ $vehicle_type_param }}">

                    <div class="modal-body">
                        <div class="mb-2">
                            <span class="text-muted small">対象機械：</span>
                            <strong id="msMachineName">-</strong>
                        </div>
                        <div id="msCurrentInfo" class="alert alert-info py-2 mb-3 small d-none"></div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-muted">開始日</label>
                                <input type="text" class="form-control form-control-sm js-datepicker" name="start_date" id="msStartDate" readonly autocomplete="off">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted">終了日</label>
                                <input type="text" class="form-control form-control-sm js-datepicker" name="end_date" id="msEndDate" readonly autocomplete="off">
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label small text-muted">現場</label>
                            <select class="form-select form-select-sm" name="workplace_id" id="msWorkplaceId" required>
                                <option value="">選択してください</option>
                                @foreach($workplaces as $w)
                                    <option value="{{ $w['id'] }}">{{ $w['name'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-check mt-3">
                            <input type="hidden" name="overwrite" value="0">
                            <input class="form-check-input" type="checkbox" value="1" id="msOverwrite" name="overwrite">
                            <label class="form-check-label small" for="msOverwrite">
                                他現場に配置済みの日も上書きする
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="button" class="btn btn-outline-danger btn-sm" id="msClearBtn">この期間をクリア</button>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">キャンセル</button>
                            <button type="submit" class="btn btn-primary btn-sm">この期間に配置</button>
                        </div>
                    </div>
                </form>

                {{-- クリア用フォーム（モーダル内のクリアボタンが POST する） --}}
                <form id="msClearForm" method="POST" action="{{ route('top.machine.schedule.clear') }}" class="d-none">
                    @csrf
                    <input type="hidden" name="master_id" id="msClearMasterId">
                    <input type="hidden" name="master_type" id="msClearMasterType">
                    <input type="hidden" name="start_date" id="msClearStartDate">
                    <input type="hidden" name="end_date" id="msClearEndDate">
                    <input type="hidden" name="view_start_date" value="{{ $start_date }}">
                    <input type="hidden" name="view_range" value="{{ $range_key }}">
                    <input type="hidden" name="view_vehicle_type" value="{{ $vehicle_type_param }}">
                </form>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('msEditModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        var $name = document.getElementById('msMachineName');
        var $masterId = document.getElementById('msMasterId');
        var $masterType = document.getElementById('msMasterType');
        var $start = document.getElementById('msStartDate');
        var $end = document.getElementById('msEndDate');
        var $wp = document.getElementById('msWorkplaceId');
        var $info = document.getElementById('msCurrentInfo');
        var $form = document.getElementById('msPlaceForm');
        var $clearBtn = document.getElementById('msClearBtn');
        var $clearForm = document.getElementById('msClearForm');

        function setDatePickerValue(input, value) {
            if (input && input._flatpickr && typeof input._flatpickr.setDate === 'function') {
                input._flatpickr.setDate(value, false);
            } else if (input) {
                input.value = value;
            }
        }

        document.querySelectorAll('.ms-grid td[data-machine-id]').forEach(function (cell) {
            cell.addEventListener('click', function () {
                var machineId = cell.getAttribute('data-machine-id');
                var machineName = cell.getAttribute('data-machine-name') || '';
                var masterType = cell.getAttribute('data-master-type');
                var date = cell.getAttribute('data-date');
                var wpId = cell.getAttribute('data-workplace-id') || '';
                var wpName = cell.getAttribute('data-workplace-name') || '';

                $name.textContent = machineName;
                $masterId.value = machineId;
                $masterType.value = masterType;
                setDatePickerValue($start, date);
                setDatePickerValue($end, date);
                $wp.value = wpId;

                if (wpId) {
                    $info.classList.remove('d-none');
                    $info.textContent = date + ' は「' + wpName + '」に配置済みです。期間を伸ばす場合は終了日を変更してください。';
                } else {
                    $info.classList.add('d-none');
                    $info.textContent = '';
                }

                modal.show();
            });
        });

        $clearBtn.addEventListener('click', function () {
            if (!$masterId.value) return;
            if (!confirm('指定期間（' + $start.value + ' 〜 ' + $end.value + '）の配置をクリアしますか？')) return;
            document.getElementById('msClearMasterId').value = $masterId.value;
            document.getElementById('msClearMasterType').value = $masterType.value;
            document.getElementById('msClearStartDate').value = $start.value;
            document.getElementById('msClearEndDate').value = $end.value;
            $clearForm.submit();
        });

        $form.addEventListener('submit', function (e) {
            if (!$wp.value) {
                e.preventDefault();
                alert('現場を選択してください');
                return;
            }
            if (!$start.value || !$end.value) {
                e.preventDefault();
                alert('開始日・終了日を選択してください');
                return;
            }
        });
    });
    </script>
@endsection
