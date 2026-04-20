@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">稼働率</h1>
            <div class="text-muted small">車両・重機の稼働率を年別で確認します。</div>
        </div>
        <div class="text-end">
            <a href="{{ route('top.setting') }}" class="btn btn-outline-secondary btn-sm">戻る</a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('setting.utilizationrate.index') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">年</label>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        @php
                            $years = $select_year_list ?: [$year];
                        @endphp
                        @foreach($years as $y)
                            <option value="{{ $y }}" {{ (string)$y === (string)$year ? 'selected' : '' }}>
                                {{ $y }}年
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8 text-md-end">
                    <button class="btn btn-primary" type="submit">表示</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            @php
                $list = $utilization_rate_list ?? [];
                $rows = is_array($list) ? $list : [];
            @endphp

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">名前</th>
                            <th style="width: 90px;">種別</th>
                            @for($m=1;$m<=12;$m++)
                                <th style="width: 64px;">{{ $m }}月</th>
                            @endfor
                            <th style="width: 76px;">合計</th>
                        </tr>
                    </thead>
                    <tbody>
                    @if(empty($rows))
                        <tr>
                            <td colspan="15" class="text-muted">データなし</td>
                        </tr>
                    @else
                        @foreach($rows as $vehicleId => $item)
                            <tr>
                                <td class="fw-medium">{{ $item['name'] ?? '' }}</td>
                                <td class="text-muted small">{{ $item['type'] ?? '' }}</td>
                                @for($m=1;$m<=12;$m++)
                                    <td>{{ isset($item[$m]) ? number_format((float)$item[$m], 2) : '' }}</td>
                                @endfor
                                <td>{{ isset($item['total']) ? number_format((float)$item['total'], 2) : '' }}</td>
                            </tr>
                        @endforeach
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

