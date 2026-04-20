@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">月次勤怠表出力</h1>
            <div class="text-muted small">
                指定した日付が属する月の勤怠表を PDF で出力します。
            </div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">勤怠管理へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.attendance.monthly.download') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small text-muted">基準日</label>
                    <input
                        type="text"
                        name="work_date"
                        class="form-control js-datepicker"
                        value="{{ $work_date }}"
                        readonly
                        autocomplete="off"
                    >
                    <div class="form-text">この日付が属する月を対象にします。</div>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit">PDF出力</button>
                </div>
            </form>
        </div>
    </div>
@endsection

