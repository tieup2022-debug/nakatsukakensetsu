@extends('layouts.app')

@section('content')
    @php
        // 年度内の月順（4月〜翌3月）
        $fiscalMonths = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
        $totalApproved = 0.0;
        $totalPending = 0.0;
        foreach ($summary as $row) {
            $totalApproved += $row['approved'];
            $totalPending += $row['pending'];
        }
        // 「5」「5.5」のように小数が不要なときは省いて表示する
        $fmtDays = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
    @endphp

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">有給取得状況</h1>
            <div class="text-muted small">
                {{ $fiscal_year }}年度（{{ $fiscal_year }}年4月〜{{ $fiscal_year + 1 }}年3月）の有給取得・申請を、0.5日単位で社員別に集計しています。<br>
                残数 = 繰越 + 当年度付与 − 取得済み。
                @if (!empty($can_edit_grants))
                    繰越・当年度付与を入力して「保存」を押してください。
                @else
                    繰越・当年度付与の入力は管理者のみ行えます。
                @endif
            </div>
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
                    <span class="fs-5 fw-semibold ms-2">{{ $fmtDays($totalApproved) }}日</span>
                </div>
            </div>
        </div>
        <div class="col-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <span class="text-muted small">申請中</span>
                    <span class="fs-5 fw-semibold ms-2">{{ $fmtDays($totalPending) }}日</span>
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
                            <th class="text-nowrap text-center">繰越</th>
                            <th class="text-nowrap text-center">当年度付与</th>
                            @if (!empty($can_edit_grants))
                                <th></th>
                            @endif
                            <th class="text-nowrap text-center">合計</th>
                            <th class="text-nowrap text-center">取得済み</th>
                            <th class="text-nowrap text-center">申請中</th>
                            <th class="text-nowrap text-center">残数</th>
                            @foreach ($fiscalMonths as $m)
                                <th class="text-center text-muted" style="min-width: 34px;">{{ $m }}月</th>
                            @endforeach
                            <th class="text-nowrap text-center">最終取得日</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($staff_list as $s)
                        @php
                            $sid = (int) $s->id;
                            $row = $summary[$sid] ?? null;
                            $approved = $row['approved'] ?? 0;
                            $pending = $row['pending'] ?? 0;
                            $grant = $grants[$sid] ?? null;
                            $carryover = (float) ($grant->carryover_days ?? 0);
                            $granted = (float) ($grant->granted_days ?? 0);
                            $hasGrant = $grant !== null;
                            $total = $carryover + $granted;
                            $remaining = $total - $approved;
                            $formId = 'grant-form-'.$sid;
                        @endphp
                        <tr>
                            <td class="fw-medium text-nowrap">{{ $s->staff_name }}</td>
                            @if (!empty($can_edit_grants))
                                <td class="text-center" style="min-width: 76px;">
                                    <input type="number" name="carryover_days" form="{{ $formId }}" class="form-control form-control-sm text-end" value="{{ $fmtDays($carryover) }}" min="0" max="999" step="0.5" inputmode="decimal" required>
                                </td>
                                <td class="text-center" style="min-width: 76px;">
                                    <input type="number" name="granted_days" form="{{ $formId }}" class="form-control form-control-sm text-end" value="{{ $fmtDays($granted) }}" min="0" max="999" step="0.5" inputmode="decimal" required>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('paid-leave.grants.update') }}" id="{{ $formId }}">
                                        @csrf
                                        <input type="hidden" name="staff_id" value="{{ $sid }}">
                                        <input type="hidden" name="fiscal_year" value="{{ $fiscal_year }}">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">保存</button>
                                    </form>
                                </td>
                            @else
                                <td class="text-center">{{ $hasGrant ? $fmtDays($carryover) : '—' }}</td>
                                <td class="text-center">{{ $hasGrant ? $fmtDays($granted) : '—' }}</td>
                            @endif
                            <td class="text-center fw-medium">{{ $hasGrant ? $fmtDays($total).'日' : '—' }}</td>
                            <td class="text-center">
                                @if ($approved > 0)
                                    <span class="badge text-bg-success">{{ $fmtDays($approved) }}日</span>
                                @else
                                    <span class="text-muted">0日</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($pending > 0)
                                    <span class="badge text-bg-warning text-dark">{{ $fmtDays($pending) }}日</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($hasGrant)
                                    <span class="fw-semibold {{ $remaining < 0 ? 'text-danger' : '' }}">{{ $fmtDays($remaining) }}日</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @foreach ($fiscalMonths as $m)
                                @php $count = $row['monthly'][$m] ?? 0; @endphp
                                <td class="text-center {{ $count > 0 ? '' : 'text-muted' }}">{{ $count > 0 ? $fmtDays($count) : '' }}</td>
                            @endforeach
                            <td class="text-center small text-nowrap">{{ $row['last_approved'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($fiscalMonths) + (!empty($can_edit_grants) ? 9 : 8) }}" class="text-center text-muted py-4">社員が登録されていません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
