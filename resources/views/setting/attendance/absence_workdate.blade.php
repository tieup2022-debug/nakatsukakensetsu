@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">欠勤者管理</h1>
            <div class="text-muted small">作業日を選んで、欠勤設定を更新します。</div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">戻る</a>
        </div>
    </div>

    @if (!empty($needs_work_date))
        <div class="alert alert-warning mb-3">作業日を指定してください。</div>
    @endif

    @if (isset($save_outcome))
        @if ($save_outcome === 'success')
            <div class="alert alert-success mb-3">保存しました。</div>
        @elseif ($save_outcome === 'attendance_conflict')
            <div class="alert alert-warning mb-3">
                その作業日にすでに勤怠が登録されている社員は、欠勤に設定できません。該当社員の勤怠を削除するか、欠勤のチェックを外してから保存してください。
            </div>
        @else
            <div class="alert alert-danger mb-3">保存に失敗しました。しばらくしてから再度お試しください。</div>
        @endif
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('setting.attendance.absence.staff') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small text-muted">作業日</label>
                    <input
                        type="text"
                        class="form-control js-datepicker"
                        name="work_date"
                        value="{{ request()->query('work_date') ?: $default_date }}"
                        readonly
                        autocomplete="off"
                    >
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit">次へ</button>
                </div>
            </form>
        </div>
    </div>
@endsection

