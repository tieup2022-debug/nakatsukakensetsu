@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">お問い合わせ一覧</h1>
            <div class="text-muted small">送信者・送信日時と内容の履歴です（直近200件）。</div>
        </div>
        <a href="{{ route('top.setting') }}" class="btn btn-outline-secondary btn-sm">設定トップへ</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 12rem;">送信日時</th>
                            <th style="width: 10rem;">送信者</th>
                            <th>内容</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td class="small text-nowrap">
                                {{ \Carbon\Carbon::parse($r->created_at)->timezone(config('app.timezone'))->format('Y/m/d H:i') }}
                            </td>
                            <td class="small">
                                <div class="fw-semibold">{{ $r->submitted_by_user_name }}</div>
                                <div class="text-muted">ユーザーID {{ $r->submitted_by_user_id }}</div>
                            </td>
                            <td class="small text-break" style="white-space: pre-wrap;">{{ $r->body }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">お問い合わせはまだありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
