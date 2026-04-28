@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">有給申請</h1>
            <div class="text-muted small">有給対象者を選択して期間を登録します。下部で全員の申請状況を確認できます。</div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('paid-leave.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted">有給対象者</label>
                    <select name="applicant_staff_id" class="form-select @error('applicant_staff_id') is-invalid @enderror" required>
                        <option value="">選択してください</option>
                        @foreach ($staff_list as $s)
                            <option value="{{ $s->id }}" @selected((string) old('applicant_staff_id') === (string) $s->id)>{{ $s->staff_name }}</option>
                        @endforeach
                    </select>
                    @error('applicant_staff_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted">開始日</label>
                    <input type="text" name="starts_at" class="form-control js-datepicker @error('starts_at') is-invalid @enderror" value="{{ old('starts_at') }}" readonly required>
                    @error('starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label small text-muted">終了日</label>
                    <input type="text" name="ends_at" class="form-control js-datepicker @error('ends_at') is-invalid @enderror" value="{{ old('ends_at') }}" readonly required>
                    @error('ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small text-muted">備考（任意）</label>
                    <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" maxlength="2000" placeholder="任意">
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-lg-2 col-md-12">
                    <button type="submit" class="btn btn-primary w-100">申請</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>有給対象者</th>
                            <th>期間</th>
                            <th>申請者</th>
                            <th>状態</th>
                            <th>備考</th>
                            @if (!empty($can_approve_paid_leave))
                                <th style="width: 100px;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $r)
                        <tr>
                            <td class="fw-medium">{{ $r->target_staff_name }}</td>
                            <td class="small">
                                {{ \Carbon\Carbon::parse($r->starts_at)->timezone(config('app.timezone'))->format('Y/m/d') }}
                                〜
                                {{ \Carbon\Carbon::parse($r->ends_at)->timezone(config('app.timezone'))->format('Y/m/d') }}
                            </td>
                            <td class="small">{{ $r->requester_user_name }}</td>
                            <td>
                                @if($r->status === 'approved')
                                    <span class="badge text-bg-success">承認済</span>
                                    @if(!empty($r->approver_staff_name))
                                        <div class="small text-muted mt-1">承認: {{ $r->approver_staff_name }}</div>
                                    @endif
                                @else
                                    <span class="badge text-bg-warning text-dark">申請中</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?? '—', 40) }}</td>
                            @if (!empty($can_approve_paid_leave))
                                <td class="text-end">
                                    @if($r->status === 'pending')
                                        <form method="POST" action="{{ route('paid-leave.approve', ['id' => $r->id]) }}" class="d-inline" onsubmit="return confirm('この申請を承認しますか？');">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">承認</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ !empty($can_approve_paid_leave) ? 6 : 5 }}" class="text-center text-muted py-4">申請はまだありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
