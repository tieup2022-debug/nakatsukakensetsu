@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">お知らせ更新履歴</h1>
            <div class="text-muted small">直近の更新履歴を確認します。</div>
        </div>
        <div>
            <a href="{{ route('top.setting') }}" class="btn btn-outline-secondary btn-sm me-2">戻る</a>
            <a href="{{ route('setting.news.update') }}" class="btn btn-outline-secondary btn-sm">編集へ</a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 180px;">日時</th>
                            <th style="width: 180px;">更新者</th>
                            <th>内容</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($history_list as $h)
                        <tr>
                            <td class="text-muted">
                                {{
                                    !empty($h->created_at)
                                        ? \Illuminate\Support\Carbon::parse((string) $h->created_at, 'UTC')->setTimezone('Asia/Tokyo')->format('Y/m/d H:i')
                                        : ''
                                }}
                            </td>
                            <td class="text-muted">{{ $h->user_name ?? '' }}</td>
                            <td style="white-space: pre-wrap;">{{ $h->news ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">データなし</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

