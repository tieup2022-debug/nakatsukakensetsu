@extends('layouts.app')

@section('content')
    @php $deletion_stats = $deletion_stats ?? []; @endphp
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">
                {{ $mode === 'completed' ? '完了済みの現場' : '稼働中の現場' }}
            </h1>
            <div class="text-muted small">現場の編集・削除を行います。</div>
        </div>
        <div>
            <a class="btn btn-primary" href="{{ route('setting.workplace.create') }}">追加</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">ID</th>
                            <th>現場名</th>
                            <th style="width: 220px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($workplace_list as $w)
                        @php
                            $stats = $deletion_stats[(string) $w->id] ?? $deletion_stats[$w->id] ?? ['attendance' => 0, 'assignment' => 0];
                        @endphp
                        <tr>
                            <td>{{ $w->id }}</td>
                            <td class="fw-medium">{{ $w->workplace_name }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm me-2" href="{{ route('setting.workplace.update', ['workplace_id'=>$w->id]) }}">
                                    編集
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('setting.workplace.delete') }}"
                                    class="js-workplace-delete-form"
                                    style="display:inline;"
                                    data-workplace-name="{{ e($w->workplace_name) }}"
                                    data-attendance-count="{{ (int) ($stats['attendance'] ?? 0) }}"
                                    data-assignment-count="{{ (int) ($stats['assignment'] ?? 0) }}"
                                >
                                    @csrf
                                    <input type="hidden" name="workplace_id" value="{{ $w->id }}">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        削除
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-workplace-delete-form').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    var name = form.getAttribute('data-workplace-name') || '';
                    var att = form.getAttribute('data-attendance-count') || '0';
                    var asg = form.getAttribute('data-assignment-count') || '0';
                    var msg = '現場「' + name + '」を削除します。\n\n'
                        + '紐づく勤怠 ' + att + ' 件・配置 ' + asg + ' 件も一覧から見えなくなります。\n'
                        + '（データはDB上に論理削除として残ります。完全に消す場合はバックアップ運用と別途対応が必要です。）\n\n'
                        + '実行しますか？';
                    if (!window.confirm(msg)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
@endsection

