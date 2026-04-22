@extends('layouts.app')

@section('content')
    <style>
        .news-body-diff .news-diff-added { color: #e8590c; font-weight: 600; }
    </style>
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">配置一覧</h1>
            <div class="text-muted small">
                {{ $display_date ?: '—' }}
            </div>
        </div>
        <a
            class="btn btn-outline-dark btn-sm align-self-center"
            href="{{ route('setting.assignment.manage', ['workplace_id' => $workplace_id, 'work_date' => $work_date]) }}"
        >
            配置入力
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            {{-- フォームのネストは無効 HTML のためモバイルで所属が崩れ、現場 select の GET が正しく送られないことがある --}}
            <div class="d-flex flex-column flex-lg-row flex-lg-wrap align-items-stretch align-items-lg-end gap-2 gap-lg-3">
                <form id="assignment-filter-form" method="GET" action="{{ route('top.assignment') }}" class="row g-2 align-items-end flex-grow-1" style="min-width:0">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">現場</label>
                        <select
                            class="form-select"
                            name="workplace_id"
                            form="assignment-filter-form"
                            data-assignment-filter-submit
                        >
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
                            form="assignment-filter-form"
                            value="{{ $work_date }}"
                            data-datepicker-submit
                            readonly
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button class="btn btn-outline-primary btn-sm" type="submit" form="assignment-filter-form">更新</button>
                        <a
                            class="btn btn-outline-secondary btn-sm ms-2"
                            href="{{ route('top.assignment', ['workplace_id'=>$workplace_id,'work_date'=>$work_date,'output_preview'=>1]) }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            ブラウザ表示
                        </a>
                        <a
                            class="btn btn-outline-secondary btn-sm ms-2"
                            href="{{ route('top.assignment', ['workplace_id'=>$workplace_id,'work_date'=>$work_date,'output_pdf'=>1]) }}"
                        >
                            PDF出力
                        </a>
                    </div>
                </form>
                @if(!empty($previous_date))
                    <div class="d-flex justify-content-lg-end flex-shrink-0">
                        <form method="POST" action="{{ route('top.assignment.copy') }}" class="d-inline" onsubmit="return confirm('前日の配置をコピーしますか？（現在の配置は上書きされます）');">
                            @csrf
                            <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                            <input type="hidden" name="work_date" value="{{ $work_date }}">
                            <button type="submit" class="btn btn-outline-success btn-sm">
                                前日コピー
                            </button>
                        </form>
                    </div>
                @endif
            </div>
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
                            <div class="badge bg-primary-subtle text-primary p-2">技術者/OP/作業員・車両/重機</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="small text-muted mb-2">技術者</div>
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
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div class="min-w-0">
                            <h2 class="h6 mb-0 fw-semibold">お知らせ</h2>
                            @if($news && (($news->updated_at ?? null) || ($news->last_editor_name ?? null)))
                                <div class="small text-muted text-break mt-1">
                                    @if(!empty($news->last_editor_name))
                                        <span>最終更新者：{{ $news->last_editor_name }}</span>
                                    @endif
                                    @if(!empty($news->updated_at))
                                        @if(!empty($news->last_editor_name))
                                            <span class="mx-1">/</span>
                                        @endif
                                        <span>更新日時：{{ date('Y/m/d H:i', strtotime((string) $news->updated_at)) }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <span class="badge bg-primary-subtle text-primary flex-shrink-0">最新</span>
                    </div>
                    @if($news && isset($news->news))
                        <div class="small text-muted news-body-diff" style="white-space: pre-wrap;">
                            {!! $news_body_html !!}
                        </div>
                    @else
                        <div class="text-muted small">
                            お知らせはありません。
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-assignment-filter-submit]').forEach(function (sel) {
                sel.addEventListener('change', function () {
                    var f = sel.form || document.getElementById('assignment-filter-form');
                    if (!f) return;
                    window.setTimeout(function () {
                        f.submit();
                    }, 0);
                });
            });
        });
    </script>
@endsection

