@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">ユーザー一覧</h1>
            <div class="text-muted small">編集・削除を行います。</div>
        </div>
        <div>
            <a class="btn btn-primary btn-sm" href="{{ route('setting.user.create') }}">追加</a>
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
                            <th style="width: 120px;">ID</th>
                            <th>ユーザー名</th>
                            <th style="width: 180px;">ログインID</th>
                            <th style="width: 120px;">権限</th>
                            <th style="width: 220px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($user_list as $u)
                        <tr>
                            <td>{{ $u->id }}</td>
                            <td class="fw-medium">{{ $u->user_name }}</td>
                            <td class="text-muted">{{ $u->login_id }}</td>
                            <td>{{ $u->permission }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-primary btn-sm me-2" href="{{ route('setting.user.update', ['user_id'=>$u->id]) }}">編集</a>
                                <form method="POST" action="{{ route('setting.user.delete') }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <button class="btn btn-outline-danger btn-sm" onclick="return confirm('削除しますか？')" type="submit">削除</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    @if(empty($user_list))
                        <tr><td colspan="5" class="text-muted">データなし</td></tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

