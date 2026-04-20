@extends('layouts.app')

@section('content')
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

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.news.update') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label small text-muted">お知らせ内容</label>
                    <textarea name="news" class="form-control" rows="8">{{ $news_data->news ?? '' }}</textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

