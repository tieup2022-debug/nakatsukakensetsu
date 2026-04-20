@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">配置一覧</h1>
            <div class="text-muted small">
                {{ $display_date ?: '—' }}
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('top.assignment') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">現場</label>
                    <select class="form-select" name="workplace_id" onchange="this.form.submit()">
                        @foreach($workplace_list as $w)
                            <option value="{{ $w->id }}" {{ (string)$w->id === (string)$workplace_id ? 'selected' : '' }}>
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
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-outline-primary btn-sm" type="submit">更新</button>
                    <a
                        class="btn btn-outline-secondary btn-sm ms-2"
                        href="{{ route('top.assignment', ['workplace_id'=>$workplace_id,'work_date'=>$work_date,'output_pdf'=>1]) }}"
                    >
                        PDF出力
                    </a>
                    @if(!empty($previous_date))
                        <form method="POST" action="{{ route('top.assignment.copy') }}" class="d-inline ms-2" onsubmit="return confirm('前日の配置をコピーしますか？（現在の配置は上書きされます）');">
                            @csrf
                            <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                            <input type="hidden" name="work_date" value="{{ $work_date }}">
                            <button type="submit" class="btn btn-outline-success btn-sm">
                                前日コピー
                            </button>
                        </form>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('top.assignment.update') }}">
                        @csrf
                        <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                        <input type="hidden" name="work_date" value="{{ $work_date }}">

                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <div class="badge bg-primary-subtle text-primary p-2">技術担当/OP/作業員・車両/重機</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted mb-2">担当（技術担当者）</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                        @foreach($staff_list_first as $s)
                                            @php
                                                $checked = isset($s->assignment_flg) && intval($s->assignment_flg) === 1;
                                            @endphp
                                            <tr>
                                                <td style="width:48px;">
                                                    <input type="hidden" name="staff_list_first[{{ $s->staff_id }}]" value="0">
                                                    <input type="checkbox" class="form-check-input" name="staff_list_first[{{ $s->staff_id }}]" value="1" {{ $checked ? 'checked' : '' }}>
                                                </td>
                                                <td>{{ $s->staff_name }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="small text-muted mb-2">OP（オペレーター）</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                        @foreach($staff_list_second as $s)
                                            @php
                                                $checked = isset($s->assignment_flg) && intval($s->assignment_flg) === 1;
                                            @endphp
                                            <tr>
                                                <td style="width:48px;">
                                                    <input type="hidden" name="staff_list_second[{{ $s->staff_id }}]" value="0">
                                                    <input type="checkbox" class="form-check-input" name="staff_list_second[{{ $s->staff_id }}]" value="1" {{ $checked ? 'checked' : '' }}>
                                                </td>
                                                <td>{{ $s->staff_name }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="small text-muted mb-2">作業員（社員）</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <tbody>
                                    @foreach($staff_list_third as $s)
                                        @php
                                            $checked = isset($s->assignment_flg) && intval($s->assignment_flg) === 1;
                                        @endphp
                                        <tr>
                                            <td style="width:48px;">
                                                <input type="hidden" name="staff_list_third[{{ $s->staff_id }}]" value="0">
                                                <input type="checkbox" class="form-check-input" name="staff_list_third[{{ $s->staff_id }}]" value="1" {{ $checked ? 'checked' : '' }}>
                                            </td>
                                            <td>{{ $s->staff_name }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <div class="small text-muted mb-2">車両</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                        @foreach($vehicle_list as $v)
                                            @php $checked = isset($v->assignment_flg) && intval($v->assignment_flg) === 1; @endphp
                                            <tr>
                                                <td style="width:48px;">
                                                    <input type="hidden" name="vehicle_list[{{ $v->vehicle_id }}]" value="0">
                                                    <input type="checkbox" class="form-check-input" name="vehicle_list[{{ $v->vehicle_id }}]" value="1" {{ $checked ? 'checked' : '' }}>
                                                </td>
                                                <td>{{ $v->vehicle_name }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="small text-muted mb-2">重機</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <tbody>
                                        @foreach($equipment_list as $e)
                                            @php $checked = isset($e->assignment_flg) && intval($e->assignment_flg) === 1; @endphp
                                            <tr>
                                                <td style="width:48px;">
                                                    <input type="hidden" name="equipment_list[{{ $e->vehicle_id }}]" value="0">
                                                    <input type="checkbox" class="form-check-input" name="equipment_list[{{ $e->vehicle_id }}]" value="1" {{ $checked ? 'checked' : '' }}>
                                                </td>
                                                <td>{{ $e->vehicle_name }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button class="btn btn-primary" type="submit">保存</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="h6 mb-0 fw-semibold">お知らせ</h2>
                        <span class="badge bg-primary-subtle text-primary">最新</span>
                    </div>
                    @if($news && isset($news->news))
                        <div class="small text-muted" style="white-space: pre-wrap;">
                            {{ $news->news }}
                        </div>
                    @else
                        <div class="text-muted small">
                            お知らせはありません。
                        </div>
                    @endif

                    <div class="mt-3 small text-muted">
                        （必要なら「前日から複製」等も同じ画面内に追加できます）
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

