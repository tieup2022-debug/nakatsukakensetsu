@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">勤怠管理</h1>
            <div class="text-muted small">
                出退勤の一括登録/個別編集と、欠勤者管理を行います。
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('setting.attendance.manage') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-muted">現場</label>
                    <select class="form-select" name="workplace_id" onchange="this.form.submit()">
                        <option value="">選択してください</option>
                        @foreach($workplace_list as $w)
                            <option value="{{ $w->id }}" {{ (string)$w->id === (string)$selected_workplace_id ? 'selected' : '' }}>
                                {{ $w->workplace_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">作業日</label>
                    <input
                        type="text"
                        class="form-control js-datepicker"
                        name="work_date"
                        value="{{ $work_date }}"
                        data-datepicker-submit
                        readonly
                        autocomplete="off"
                    >
                </div>
                <div class="col-md-3 text-md-end">
                    <button class="btn btn-outline-primary btn-sm" type="submit">更新</button>
                </div>
            </form>
        </div>
    </div>

    @php
        $canGo = !empty($selected_workplace_id) && !empty($work_date);
    @endphp

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">一括登録</h2>
                    <p class="text-muted small mb-3">割当済みスタッフの欠勤/出退勤をまとめて登録。</p>
                    @if($canGo)
                        <a
                            href="{{ route('setting.attendance.input', ['mode' => 'create', 'workplace_id' => $selected_workplace_id, 'work_date' => $work_date]) }}"
                            class="btn btn-primary w-100"
                        >
                            開く
                        </a>
                    @else
                        <button class="btn btn-primary w-100" type="button" disabled>現場/日付を選択</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">一覧/個別編集</h2>
                    <p class="text-muted small mb-3">登録状況を確認して、必要なスタッフのみ編集。</p>
                    @if($canGo)
                        <a
                            href="{{ route('setting.attendance.list', ['workplace_id' => $selected_workplace_id, 'work_date' => $work_date]) }}"
                            class="btn btn-outline-primary w-100"
                        >
                            開く
                        </a>
                    @else
                        <button class="btn btn-outline-primary w-100" type="button" disabled>現場/日付を選択</button>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">欠勤者管理</h2>
                    <p class="text-muted small mb-3">配置に入っていない社員の欠勤設定を行います。</p>
                    <a
                        href="{{ route('setting.attendance.absence.workdate', ['work_date' => $work_date]) }}"
                        class="btn btn-outline-secondary w-100"
                    >
                        開く
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">月次勤怠表</h2>
                    <p class="text-muted small mb-3">1か月分の勤怠状況を PDF で一覧出力します。</p>
                    <a
                        href="{{ route('setting.attendance.monthly.form', ['work_date' => $work_date]) }}"
                        class="btn btn-outline-primary w-100"
                    >
                        月次勤怠表を出力
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">個人別集計</h2>
                    <p class="text-muted small mb-3">月ごとの個人別勤怠（普通/時間外/休日/深夜）を確認します。</p>
                    <a
                        href="{{ route('setting.attendance.personal.summary', ['work_date' => $work_date]) }}"
                        class="btn btn-outline-primary w-100"
                    >
                        個人別集計を表示
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

