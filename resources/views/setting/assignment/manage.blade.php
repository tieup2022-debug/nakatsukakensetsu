@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">配置入力</h1>
            <div class="text-muted small">現場別に配置（スタッフ/車両/重機）を一括で管理します。</div>
        </div>
        <div class="text-md-end">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('top.setting') }}">設定トップへ</a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('setting.assignment.manage') }}" class="row g-2 align-items-end">
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
        $canEdit = !empty($selected_workplace_id) && !empty($work_date);
    @endphp

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h2 class="h6 fw-semibold mb-2">操作</h2>
            <div class="row g-2">
                <div class="col-md-6">
                    @if($canEdit)
                        <a
                            href="{{ route('setting.assignment.edit', ['workplace_id'=>$selected_workplace_id,'work_date'=>$work_date]) }}"
                            class="btn btn-primary w-100"
                        >編集画面へ</a>
                    @else
                        <button class="btn btn-primary w-100" type="button" disabled>現場/日付を選択</button>
                    @endif
                </div>
                <div class="col-md-6">
                    <a href="{{ route('setting.assignment.monthly.form', ['work_date' => $work_date]) }}" class="btn btn-outline-primary w-100">
                        配置一覧PDF出力
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <a href="{{ route('top.setting') }}" class="btn btn-outline-secondary btn-sm">設定トップへ戻る</a>
    </div>
@endsection

