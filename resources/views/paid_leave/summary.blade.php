@extends('layouts.app')

@section('content')
    @php
        // 年度内の月順（4月〜翌3月）
        $fiscalMonths = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        $totalApproved = 0;
        $totalPending = 0;
        foreach ($summary as $row) {
            $totalApproved += $row['approved'];
            $totalPending += $row['pending'];
        }
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">有給取得状況</h1>
            <div class="text-muted small">{{ $fiscal_year }}年度（{{ $fiscal_year }}年4月〜{{ $fiscal_year + 1 }}年3月）に休む日がある申請を社員別に集計しています。1申請 = 1日として数えます。</div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('paid-leave.summary', ['fiscal_year' => $fiscal_year - 1]) }}">← {{ $fiscal_year - 1 }}年度</a>
            @if ($fiscal_year < $current_fiscal_year)
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('paid-leave.summary', ['fiscal_year' => $fiscal_year + 1]) }}">{{ $fiscal_year + 1 }}年度 →</a>
            @endif
            <a class="btn btn-outline-primary btn-sm" href="{{ route('paid-leave.index') }}">申請一覧へ</a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <span class="text-muted small">年度合計 取得済み</span>
                    <span class="fs-5 fw-semibold ms-2">{{ $totalApproved }}日</span>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <span class="text-muted small">申請中</span>
                    <span class="fs-5 fw-semibold ms-2">{{ $totalPending }}日</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th class="text-nowrap">社員</th>
                            <th class="text-nowrap text-center">取得済み</th>
                            <th class="text-nowrap text-center">申請中</th>
                            @foreach ($fiscalMonths as $m)
                                <th class="text-center text-muted" style="min-width: 34px;">{{ $m }}月</th>
                            @endforeach
                            <th class="text-nowrap text-center">最終取得日</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($staff_list as $s)
                        @php
                            $row = $summary[(int) $s->id] ?? null;
                            $approved = $row['approved'] ?? 0;
                            $pending = $row['pending'] ?? 0;
                        @endphp
                        <tr>
                            <td class="fw-medium text-nowrap">{{ $s->staff_name }}</td>
                            <td class="text-center">
                                @if ($approved > 0)
                                    <span class="badge text-bg-success">{{ $approved }}日</span>
                                @else
                                    <span class="text-muted">0日</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($pending > 0)
                                    <span class="badge text-bg-warning text-dark">{{ $pending }}日</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @foreach ($fiscalMonths as $m)
                                @php $count = $row['monthly'][$m] ?? 0; @endphp
                                <td class="text-center {{ $count > 0 ? '' : 'text-muted' }}">{{ $count > 0 ? $count : '' }}</td>
                            @endforeach
                            <td class="text-center small text-nowrap">{{ $row['last_approved'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($fiscalMonths) + 4 }}" class="text-center text-muted py-4">社員が登録されていません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
