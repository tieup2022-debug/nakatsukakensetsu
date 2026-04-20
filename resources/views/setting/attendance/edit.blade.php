@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠管理（編集選択）</h1>
            <div class="text-muted small">モード: {{ $mode }} / 現場ID: {{ $selected_workplace_id }} / 日付: {{ $work_date }}</div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.manage') }}">戻る</a>
        </div>
    </div>

    @if ($mode === 'create')
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">一括登録へ</h2>
                <a
                    class="btn btn-primary"
                    href="{{ route('setting.attendance.input', ['mode' => 'create', 'workplace_id' => $selected_workplace_id, 'work_date' => $work_date]) }}"
                >
                    入力画面を開く
                </a>
            </div>
        </div>
    @elseif ($mode === 'update')
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">個別編集（スタッフ選択）</h2>

                <form method="GET" action="{{ route('setting.attendance.input') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="mode" value="update">
                    <input type="hidden" name="workplace_id" value="{{ $selected_workplace_id }}">
                    <input type="hidden" name="work_date" value="{{ $work_date }}">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">社員</label>
                        <select class="form-select" name="staff_id" onchange="this.form.submit()">
                            <option value="">選択してください</option>
                            @foreach($assigned_staff_list as $staff)
                                <option value="{{ $staff->staff_id }}">{{ $staff->staff_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.attendance.list', ['workplace_id'=>$selected_workplace_id,'work_date'=>$work_date]) }}">
                            一覧へ
                        </a>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-warning">不正なモードです。</div>
    @endif
@endsection

