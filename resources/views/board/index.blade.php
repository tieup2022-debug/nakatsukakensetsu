@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/board.css') }}">
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">掲示板</h1>
            <div class="text-muted small">社内連絡、工事日報、資料共有に利用できます。</div>
        </div>
        <a href="{{ route('board.create') }}" class="btn btn-primary">＋ 新規投稿</a>
    </div>

    <form method="get" action="{{ route('board.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md">
                    <label for="board-keyword" class="visually-hidden">キーワード</label>
                    <input
                        id="board-keyword"
                        type="search"
                        name="q"
                        value="{{ $keyword }}"
                        class="form-control"
                        maxlength="100"
                        placeholder="タイトル・本文・投稿者を検索"
                    >
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">検索</button>
                </div>
                @if ($keyword !== '')
                    <div class="col-auto">
                        <a href="{{ route('board.index') }}" class="btn btn-outline-secondary">解除</a>
                    </div>
                @endif
            </div>
        </div>
    </form>

    @if ($keyword !== '')
        <p class="small text-muted mb-2">「{{ $keyword }}」の検索結果：{{ number_format($threads->total()) }}件</p>
    @else
        <p class="small text-muted mb-2">全{{ number_format($threads->total()) }}件</p>
    @endif

    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0 board-list-table">
                <thead class="table-light">
                    <tr>
                        <th>タイトル</th>
                        <th class="text-nowrap">投稿者</th>
                        <th class="text-nowrap text-end">反応</th>
                        <th class="text-nowrap">最終更新</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($threads as $thread)
                        <tr>
                            <td>
                                <a href="{{ route('board.show', ['thread' => $thread->id]) }}" class="fw-semibold text-decoration-none stretched-link-scope">
                                    {{ $thread->title }}
                                </a>
                                @if ((int) $thread->reply_count > 0)
                                    <span class="badge rounded-pill text-bg-light border ms-1">返信 {{ number_format($thread->reply_count) }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $thread->author_name }}</td>
                            <td class="text-nowrap text-end small text-muted">
                                <span title="閲覧数">👁 {{ number_format($thread->view_count) }}</span>
                                <span class="ms-2" title="いいね">👍 {{ number_format($thread->like_count) }}</span>
                            </td>
                            <td class="text-nowrap small text-muted">
                                {{ \App\Support\DatetimeDisplay::formatStoredAt($thread->last_activity_at) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-5">該当する投稿はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-md-none list-group list-group-flush">
            @forelse ($threads as $thread)
                <a href="{{ route('board.show', ['thread' => $thread->id]) }}" class="list-group-item list-group-item-action py-3">
                    <div class="fw-semibold mb-1">{{ $thread->title }}</div>
                    <div class="d-flex flex-wrap gap-2 small text-muted">
                        <span>{{ $thread->author_name }}</span>
                        <span>👁 {{ number_format($thread->view_count) }}</span>
                        <span>👍 {{ number_format($thread->like_count) }}</span>
                        @if ((int) $thread->reply_count > 0)
                            <span>返信 {{ number_format($thread->reply_count) }}</span>
                        @endif
                    </div>
                    <div class="small text-muted mt-1">{{ \App\Support\DatetimeDisplay::formatStoredAt($thread->last_activity_at) }}</div>
                </a>
            @empty
                <div class="list-group-item text-center text-muted py-5">該当する投稿はありません。</div>
            @endforelse
        </div>
    </div>

    @if ($threads->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $threads->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endsection
