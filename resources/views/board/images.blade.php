@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/board.css') }}">
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">掲示板・画像検索</h1>
            <div class="text-muted small">過去に投稿された画像を、写っている内容や文字から探せます。</div>
        </div>
        <a href="{{ route('board.create') }}" class="btn btn-primary">＋ 新規投稿</a>
    </div>

    <nav class="nav nav-pills board-view-tabs mb-3" aria-label="掲示板の表示切替">
        <a href="{{ route('board.index') }}" class="nav-link">投稿一覧</a>
        <a href="{{ route('board.images') }}" class="nav-link active" aria-current="page">画像一覧</a>
    </nav>

    <form id="board-image-search-form" method="get" action="{{ route('board.images') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-5">
                    <label for="board-image-keyword" class="form-label small fw-semibold mb-1">画像・投稿内容</label>
                    <div class="input-group">
                        <input
                            id="board-image-keyword"
                            type="search"
                            name="q"
                            value="{{ $keyword }}"
                            class="form-control"
                            maxlength="100"
                            placeholder="例：赤い重機、工事看板、知内町"
                            autocomplete="off"
                            aria-controls="board-image-results"
                        >
                        <button class="btn btn-outline-primary" type="submit">検索</button>
                        <button
                            id="board-image-search-clear"
                            class="btn btn-outline-secondary{{ $keyword === '' && $author === '' && $fromDate === '' && $toDate === '' && $sort === 'newest' ? ' d-none' : '' }}"
                            type="button"
                        >解除</button>
                    </div>
                </div>
                <div class="col-12 col-sm-4 col-lg-3">
                    <label for="board-image-author" class="form-label small fw-semibold mb-1">担当者（投稿者）</label>
                    <select id="board-image-author" name="author" class="form-select">
                        <option value="">すべての担当者</option>
                        @foreach ($authors as $authorName)
                            <option value="{{ $authorName }}" @selected($author === $authorName)>{{ $authorName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-lg-2">
                    <label for="board-image-from" class="form-label small fw-semibold mb-1">開始日</label>
                    <input id="board-image-from" type="date" name="from" value="{{ $fromDate }}" class="form-control">
                </div>
                <div class="col-6 col-sm-4 col-lg-2">
                    <label for="board-image-to" class="form-label small fw-semibold mb-1">終了日</label>
                    <input id="board-image-to" type="date" name="to" value="{{ $toDate }}" class="form-control">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="board-image-sort" class="form-label small fw-semibold mb-1">並び順</label>
                    <select id="board-image-sort" name="sort" class="form-select">
                        <option value="newest" @selected($sort === 'newest')>新しい順</option>
                        <option value="oldest" @selected($sort === 'oldest')>古い順</option>
                        <option value="author" @selected($sort === 'author')>担当者別（名前順）</option>
                    </select>
                </div>
            </div>
            <div id="board-image-search-feedback" class="small text-danger mt-2 d-none" role="alert"></div>
        </div>
    </form>

    <div id="board-image-results" aria-live="polite">
        @include('board.partials.image-grid')
    </div>

    <div class="board-lightbox" data-board-lightbox hidden role="dialog" aria-modal="true" aria-label="添付画像の拡大表示">
        <button type="button" class="board-lightbox-close" data-board-lightbox-close aria-label="拡大表示を閉じる">×</button>
        <div class="board-lightbox-content" data-board-lightbox-content>
            <img src="" alt="" class="board-lightbox-image" data-board-lightbox-image>
            <div class="board-lightbox-caption" data-board-lightbox-caption></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/board.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('board-image-search-form');
            var keywordInput = document.getElementById('board-image-keyword');
            var authorInput = document.getElementById('board-image-author');
            var fromInput = document.getElementById('board-image-from');
            var toInput = document.getElementById('board-image-to');
            var sortInput = document.getElementById('board-image-sort');
            var results = document.getElementById('board-image-results');
            var clear = document.getElementById('board-image-search-clear');
            var feedback = document.getElementById('board-image-search-feedback');
            var timer = null;
            var request = null;
            var composing = false;

            if (!form || !keywordInput || !authorInput || !fromInput || !toInput || !results || !clear || !feedback || !window.fetch) return;

            function hasFilters() {
                return keywordInput.value.trim() !== '' || authorInput.value !== '' || fromInput.value !== '' || toInput.value !== '' || sortInput.value !== 'newest';
            }

            function formUrl() {
                var url = new URL(form.action, window.location.origin);
                var values = {
                    q: keywordInput.value.trim(),
                    author: authorInput.value,
                    from: fromInput.value,
                    to: toInput.value,
                    sort: sortInput.value
                };

                Object.keys(values).forEach(function (name) {
                    if (values[name] !== '') url.searchParams.set(name, values[name]);
                });

                return url;
            }

            function load(url) {
                var controller = new AbortController();
                if (request) request.abort();
                request = controller;
                results.setAttribute('aria-busy', 'true');
                results.classList.add('is-loading');
                feedback.classList.add('d-none');

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Image search request failed');
                        return response.text();
                    })
                    .then(function (html) {
                        results.innerHTML = html;
                        clear.classList.toggle('d-none', !hasFilters());
                        window.history.replaceState({}, '', url.toString());
                    })
                    .catch(function (error) {
                        if (error.name === 'AbortError') return;
                        feedback.textContent = '画像を更新できませんでした。検索ボタンを押して再度お試しください。';
                        feedback.classList.remove('d-none');
                    })
                    .finally(function () {
                        if (request === controller) {
                            request = null;
                            results.removeAttribute('aria-busy');
                            results.classList.remove('is-loading');
                        }
                    });
            }

            function schedule(delay) {
                window.clearTimeout(timer);
                if (request) request.abort();
                clear.classList.toggle('d-none', !hasFilters());
                timer = window.setTimeout(function () { load(formUrl()); }, delay);
            }

            keywordInput.addEventListener('compositionstart', function () {
                composing = true;
                window.clearTimeout(timer);
                if (request) request.abort();
            });
            keywordInput.addEventListener('compositionend', function () {
                composing = false;
                schedule(300);
            });
            keywordInput.addEventListener('input', function () {
                if (!composing) schedule(300);
            });
            [authorInput, fromInput, toInput, sortInput].forEach(function (input) {
                input.addEventListener('change', function () { schedule(0); });
            });
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                window.clearTimeout(timer);
                load(formUrl());
            });
            clear.addEventListener('click', function () {
                form.reset();
                keywordInput.value = '';
                authorInput.value = '';
                fromInput.value = '';
                toInput.value = '';
                sortInput.value = 'newest';
                keywordInput.focus();
                window.clearTimeout(timer);
                load(formUrl());
            });
            results.addEventListener('click', function (event) {
                var link = event.target.closest('.pagination a');
                if (!link) return;
                event.preventDefault();
                load(new URL(link.href));
                results.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    </script>
@endpush
