@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">有給申請の承認</h1>
            <div class="text-muted small">承認待ちの申請です。いずれか1名が承認すると完了します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>申請者</th>
                            <th>期間</th>
                            <th>事由</th>
                            <th style="width: 120px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $r)
                        @php
                            $staff = app(\App\Services\StaffService::class)->GetStaff((int) $r->applicant_staff_id);
                            $name = $staff && !empty($staff->staff_name) ? $staff->staff_name : ('ID '.$r->applicant_staff_id);
                        @endphp
                        <tr>
                            <td class="fw-medium">{{ $name }}</td>
                            <td class="small">
                                {{ \Carbon\Carbon::parse($r->starts_at)->timezone(config('app.timezone'))->format('Y/m/d H:i') }}
                                〜
                                {{ \Carbon\Carbon::parse($r->ends_at)->timezone(config('app.timezone'))->format('Y/m/d H:i') }}
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?? '—', 48) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('paid-leave.approve', ['id' => $r->id]) }}" class="d-inline" onsubmit="return confirm('この申請を承認しますか？');">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">承認</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">承認待ちの申請はありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
