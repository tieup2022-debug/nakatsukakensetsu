@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">有給申請一覧（自分）</h1>
            <div class="text-muted small">直近の申請履歴です。</div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>期間</th>
                            <th>状態</th>
                            <th>事由</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $r)
                        <tr>
                            <td class="small">
                                {{ \Carbon\Carbon::parse($r->starts_at)->timezone(config('app.timezone'))->format('Y/m/d H:i') }}
                                〜
                                {{ \Carbon\Carbon::parse($r->ends_at)->timezone(config('app.timezone'))->format('Y/m/d H:i') }}
                            </td>
                            <td>
                                @if($r->status === 'approved')
                                    <span class="badge text-bg-success">承認済</span>
                                @else
                                    <span class="badge text-bg-warning text-dark">承認待ち</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?? '—', 40) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">申請はまだありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
