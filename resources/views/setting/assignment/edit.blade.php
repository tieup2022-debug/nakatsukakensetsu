@extends('layouts.app')

@section('content')
    @php
        $manageUrl = route('setting.assignment.manage', ['workplace_id'=>$workplace_id,'work_date'=>$work_date]);
    @endphp
    <style>
        .assignment-edit-float {
            position: fixed;
            z-index: 1020;
            top: 4.5rem;
            right: 0.75rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-end;
            max-width: min(22rem, calc(100vw - 1.5rem));
            padding: 0.5rem 0.65rem;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 0.35rem 1.25rem rgba(15, 23, 42, 0.14);
        }
        @media (min-width: 992px) {
            .assignment-edit-float {
                right: 1.25rem;
            }
        }
        .assignment-edit-title {
            padding-right: min(13rem, 38vw);
        }
        #assignment-edit-form tr.assignment-edit-select-row {
            cursor: pointer;
        }
    </style>
    <div class="assignment-edit-float" role="toolbar" aria-label="配置の保存・戻る">
        <a class="btn btn-outline-secondary btn-sm" href="{{ $manageUrl }}">管理画面へ</a>
        <button type="submit" form="assignment-edit-form" class="btn btn-primary btn-sm px-3">保存</button>
    </div>

    <div class="mb-3 assignment-edit-title">
        <h1 class="h4 mb-1 fw-semibold">配置入力（編集）</h1>
        <div class="text-muted small">
            現場ID: {{ $workplace_id }} / 日付: {{ $work_date }}
        </div>
        <p class="text-muted small mt-2 mb-0">変更後は<strong>保存</strong>を押してください。画面右上に常に表示されるボタンでも保存できます。</p>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">
            {{ session('status') }}
        </div>
    @endif

    @php
        $staffFirst = $assignment_data['staff_list_first'] ?? [];
        $staffSecond = $assignment_data['staff_list_second'] ?? [];
        $staffThird = $assignment_data['staff_list_third'] ?? [];
        $vehicleList = $assignment_data['vehicle_list'] ?? [];
        $equipmentList = $assignment_data['equipment_list'] ?? [];
    @endphp

    @if (!empty($previous_date))
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">前日コピー</div>
                        <div class="text-muted small">
                            {{ $previous_date }} の配置をこの日付へ複製します。
                        </div>
                    </div>
                    <form method="POST" action="{{ route('setting.assignment.copy') }}" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
                        <input type="hidden" name="work_date" value="{{ $work_date }}">
                        <input type="hidden" name="previous_date" value="{{ $previous_date }}">
                        <button class="btn btn-primary" type="submit">コピーする</button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <form id="assignment-edit-form" method="POST" action="{{ route('setting.assignment.update') }}" class="pb-5">
        @csrf
        <input type="hidden" name="workplace_id" value="{{ $workplace_id }}">
        <input type="hidden" name="work_date" value="{{ $work_date }}">

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">技術者</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>社員</th>
                                <th style="width: 120px;">配置</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($staffFirst as $s)
                            @php $checked = isset($s->assignment_flg) && intval($s->assignment_flg) === 1; @endphp
                            <tr class="assignment-edit-select-row">
                                <td class="fw-medium">{{ $s->staff_name ?? '' }}</td>
                                <td>
                                    <input type="hidden" name="staff_list[{{ $s->staff_id }}]" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="staff_list[{{ $s->staff_id }}]"
                                        value="1"
                                        {{ $checked ? 'checked' : '' }}
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">データなし</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">OP</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>社員</th>
                                <th style="width: 120px;">配置</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($staffSecond as $s)
                            @php $checked = isset($s->assignment_flg) && intval($s->assignment_flg) === 1; @endphp
                            <tr class="assignment-edit-select-row">
                                <td class="fw-medium">{{ $s->staff_name ?? '' }}</td>
                                <td>
                                    <input type="hidden" name="staff_list[{{ $s->staff_id }}]" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="staff_list[{{ $s->staff_id }}]"
                                        value="1"
                                        {{ $checked ? 'checked' : '' }}
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">データなし</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">作業員</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>社員</th>
                                <th style="width: 120px;">配置</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($staffThird as $s)
                            @php $checked = isset($s->assignment_flg) && intval($s->assignment_flg) === 1; @endphp
                            <tr class="assignment-edit-select-row">
                                <td class="fw-medium">{{ $s->staff_name ?? '' }}</td>
                                <td>
                                    <input type="hidden" name="staff_list[{{ $s->staff_id }}]" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="staff_list[{{ $s->staff_id }}]"
                                        value="1"
                                        {{ $checked ? 'checked' : '' }}
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">データなし</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">車両</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>車両</th>
                                <th style="width: 120px;">配置</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($vehicleList as $v)
                            @php $checked = isset($v->assignment_flg) && intval($v->assignment_flg) === 1; @endphp
                            <tr class="assignment-edit-select-row">
                                <td class="fw-medium">{{ $v->vehicle_name ?? '' }}</td>
                                <td>
                                    <input type="hidden" name="vehicle_list[{{ $v->vehicle_id }}]" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="vehicle_list[{{ $v->vehicle_id }}]"
                                        value="1"
                                        {{ $checked ? 'checked' : '' }}
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">データなし</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 fw-semibold mb-2">重機</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>重機</th>
                                <th style="width: 120px;">配置</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($equipmentList as $e)
                            @php $checked = isset($e->assignment_flg) && intval($e->assignment_flg) === 1; @endphp
                            <tr class="assignment-edit-select-row">
                                <td class="fw-medium">{{ $e->vehicle_name ?? '' }}</td>
                                <td>
                                    <input type="hidden" name="equipment_list[{{ $e->vehicle_id }}]" value="0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        name="equipment_list[{{ $e->vehicle_id }}]"
                                        value="1"
                                        {{ $checked ? 'checked' : '' }}
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-muted">データなし</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3 mb-5">
            <a class="btn btn-outline-secondary" href="{{ $manageUrl }}">管理画面へ</a>
            <button class="btn btn-primary" type="submit">保存</button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('assignment-edit-form');
            if (!form) return;
            form.addEventListener('click', function (e) {
                var tr = e.target.closest('tr.assignment-edit-select-row');
                if (!tr || !form.contains(tr)) return;
                if (e.target.closest('input[type="checkbox"]')) return;
                var cb = tr.querySelector('input[type="checkbox"]');
                if (cb) {
                    cb.checked = !cb.checked;
                }
            });
        });
    </script>
@endsection

