@extends('layouts.app')

@section('content')
    <style>
        .drag-handle {
            cursor: grab;
            user-select: none;
            font-size: 1rem;
            color: #64748b;
        }
        tr.dragging {
            opacity: 0.55;
        }
    </style>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">スタッフ一覧</h1>
            <div class="text-muted small">行をドラッグ＆ドロップで並び替えて保存できます。</div>
        </div>
        <div>
            <a class="btn btn-primary" href="{{ route('setting.staff.create') }}">追加</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.staff.sort') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 44px;"></th>
                                <th>並び順</th>
                                <th>社員名</th>
                                <th>種別</th>
                                <th style="width: 240px;">操作</th>
                            </tr>
                        </thead>
                        <tbody id="staff-sort-body">
                        @foreach($staff_list as $s)
                            @php
                                $typeLabel = match ((string)$s->staff_type) {
                                    '1' => '技術者',
                                    '2' => 'OP',
                                    '3' => '作業員',
                                    '4' => '総務部',
                                    default => '—',
                                };
                            @endphp
                            <tr draggable="true" data-staff-row>
                                <td class="text-center text-muted" title="ドラッグして並び替え">
                                    <span class="drag-handle" aria-hidden="true">☰</span>
                                </td>
                                <td style="width: 120px;">
                                    <span class="badge text-bg-light border js-sort-label">{{ $loop->iteration }}</span>
                                    <input
                                        type="hidden"
                                        name="sort_number[{{ $s->id }}]"
                                        value="{{ $loop->iteration }}"
                                        class="js-sort-input"
                                    >
                                </td>
                                <td class="fw-medium">{{ $s->staff_name }}</td>
                                <td>{{ $typeLabel }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm me-2" href="{{ route('setting.staff.update', ['staff_id'=>$s->id]) }}">
                                        編集
                                    </a>
                                    <button
                                        type="submit"
                                        formaction="{{ route('setting.staff.delete') }}"
                                        formmethod="POST"
                                        class="btn btn-outline-danger btn-sm"
                                        name="staff_id"
                                        value="{{ $s->id }}"
                                    >
                                        削除
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary" type="submit">並び順を保存</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var tbody = document.getElementById('staff-sort-body');
            if (!tbody) return;

            var dragging = null;

            function syncSortNumbers() {
                var rows = tbody.querySelectorAll('tr[data-staff-row]');
                rows.forEach(function (row, idx) {
                    var order = idx + 1;
                    var input = row.querySelector('.js-sort-input');
                    var label = row.querySelector('.js-sort-label');
                    if (input) input.value = order;
                    if (label) label.textContent = order;
                });
            }

            tbody.querySelectorAll('tr[data-staff-row]').forEach(function (row) {
                row.addEventListener('dragstart', function () {
                    dragging = row;
                    row.classList.add('dragging');
                });

                row.addEventListener('dragend', function () {
                    row.classList.remove('dragging');
                    dragging = null;
                    syncSortNumbers();
                });

                row.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    if (!dragging || dragging === row) return;

                    var rect = row.getBoundingClientRect();
                    var isAfter = (e.clientY - rect.top) > (rect.height / 2);
                    tbody.insertBefore(dragging, isAfter ? row.nextSibling : row);
                });
            });

            syncSortNumbers();
        });
    </script>
@endsection

