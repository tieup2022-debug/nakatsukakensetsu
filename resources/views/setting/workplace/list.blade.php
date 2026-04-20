@extends('layouts.app')

@section('content')
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
                        <tr>
                            <td>{{ $w->id }}</td>
                            <td class="fw-medium">{{ $w->workplace_name }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm me-2" href="{{ route('setting.workplace.update', ['workplace_id'=>$w->id]) }}">
                                    編集
                                </a>
                                <form method="POST" action="{{ route('setting.workplace.delete') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="workplace_id" value="{{ $w->id }}">
                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('削除しますか？')">
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
@endsection

