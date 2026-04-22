@extends('layouts.app')

@section('content')
    <style>
        /* カード内の縦スペースをテキストエリアに割り当てる */
        .news-update-card .card-body {
            display: flex;
            flex-direction: column;
            min-height: calc(100dvh - 13rem);
        }
        .news-update-form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }
        .news-update-textarea-wrap {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .news-update-textarea-wrap textarea {
            flex: 1 1 auto;
            width: 100%;
            min-height: max(22rem, calc(100dvh - 19rem));
            resize: vertical;
        }
        @media (max-width: 576px) {
            .news-update-card .card-body {
                min-height: calc(100dvh - 11rem);
            }
        }
    </style>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">お知らせ管理</h1>
            <div class="text-muted small">表示中のお知らせを更新します。</div>
        </div>
        <div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('setting.news.history') }}">履歴を見る</a>
        </div>
    </div>

    @if (!is_null($result))
        <div class="alert {{ $result ? 'alert-success' : 'alert-danger' }} mb-3">
            {{ $result ? '更新しました' : '更新に失敗しました' }}
        </div>
    @endif

    <div class="card shadow-sm border-0 news-update-card">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.news.update') }}" class="news-update-form">
                @csrf
                <div class="news-update-textarea-wrap mb-0">
                    <label class="form-label small text-muted mb-1" for="news-update-textarea">お知らせ内容</label>
                    <textarea
                        id="news-update-textarea"
                        name="news"
                        class="form-control"
                        rows="6"
                        spellcheck="false"
                    >{{ $news_data->news ?? '' }}</textarea>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3 flex-shrink-0">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

