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

    <form id="board-search-form" method="get" action="{{ route('board.index') }}" class="card border-0 shadow-sm mb-3">
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
                        autocomplete="off"
                        aria-controls="board-search-results"
                    >
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">検索</button>
                </div>
                <div id="board-search-clear" class="col-auto{{ $keyword === '' ? ' d-none' : '' }}">
                    <button class="btn btn-outline-secondary" type="button">解除</button>
                </div>
            </div>
            <div id="board-search-feedback" class="small text-danger mt-2 d-none" role="alert"></div>
        </div>
    </form>

    <div id="board-search-results" aria-live="polite">
        @include('board.partials.thread-list')
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.getElementById('board-search-form');
            var input = document.getElementById('board-keyword');
            var results = document.getElementById('board-search-results');
            var clear = document.getElementById('board-search-clear');
            var feedback = document.getElementById('board-search-feedback');
            var timer = null;
            var request = null;
            var composing = false;

            if (!form || !input || !results || !clear || !feedback || !window.fetch) return;

            function search() {
                var keyword = input.value.trim();
                var url = new URL(form.action, window.location.origin);
                var controller = new AbortController();

                if (keyword !== '') {
                    url.searchParams.set('q', keyword);
                }

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
                        if (!response.ok) throw new Error('Search request failed');
                        return response.text();
                    })
                    .then(function (html) {
                        results.innerHTML = html;
                        clear.classList.toggle('d-none', keyword === '');
                        window.history.replaceState({}, '', url.toString());
                    })
                    .catch(function (error) {
                        if (error.name === 'AbortError') return;
                        feedback.textContent = '検索結果を更新できませんでした。検索ボタンを押して再度お試しください。';
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

            function scheduleSearch() {
                window.clearTimeout(timer);
                if (request) request.abort();
                clear.classList.toggle('d-none', input.value.trim() === '');
                timer = window.setTimeout(search, 300);
            }

            input.addEventListener('compositionstart', function () {
                composing = true;
                window.clearTimeout(timer);
                if (request) request.abort();
            });
            input.addEventListener('compositionend', function () {
                composing = false;
                scheduleSearch();
            });
            input.addEventListener('input', function () {
                if (!composing) scheduleSearch();
            });
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                window.clearTimeout(timer);
                search();
            });
            clear.querySelector('button').addEventListener('click', function () {
                input.value = '';
                input.focus();
                window.clearTimeout(timer);
                search();
            });
        });
    </script>
@endpush
