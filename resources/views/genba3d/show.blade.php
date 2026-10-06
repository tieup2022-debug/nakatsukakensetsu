@extends('layouts.app')

@php
    $tab = $tab ?? 'model';
    $isSchedule = $tab === 'schedule';
    $hasSchedule = ! empty($current['schedule_file']);
    // 現場ごとの追加資料（重機用足場のまとめなど）
    $extraPages = (array) ($current['pages'] ?? []);
    $extra = $tab === 'page' ? ($extraPages[$pageKey ?? ''] ?? null) : null;
    // 工程表と追加資料は縦に長い文書なので、枠を中身の高さに合わせる
    $isDocument = $isSchedule || $extra !== null;
    if ($extra !== null) {
        $frameUrl = route('genba3d.page.raw', ['site' => $currentSlug, 'page' => $pageKey]);
        $frameTitle = $extra['label'];
    } else {
        $frameUrl = route($isSchedule ? 'genba3d.schedule.page' : 'genba3d.model', ['site' => $currentSlug]);
        $frameTitle = $isSchedule ? '施工手順と工程表' : '3Dモデル';
    }
@endphp

@push('styles')
    <style>
        /* 3Dモデルの枠。ヘッダー・タブ・フッターを除いた高さいっぱいに広げる */
        .genba3d-frame {
            display: block;
            width: 100%;
            height: calc(100vh - 335px);
            height: calc(100dvh - 335px);
            min-height: 520px;
            border: 0;
            background: #e9eef0;
        }
        /* 工程表と資料は縦に長い文書なので、中身の高さに合わせて枠を伸ばす（下のスクリプトで設定） */
        .genba3d-frame.is-document {
            height: 1400px;
        }
        .genba3d-tabs .nav-link {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #1e293b;
        }
        .genba3d-tabs .nav-link.active {
            border-color: #1d4ed8;
            background: #1d4ed8;
            color: #fff;
        }
        .genba3d-kinds .nav-link {
            color: #475569;
            border-radius: 0;
            border-bottom: 3px solid transparent;
        }
        .genba3d-kinds .nav-link.active {
            color: #1d4ed8;
            font-weight: 600;
            border-bottom-color: #1d4ed8;
            background: transparent;
        }
        @if (! empty($chatEnabled))
        /* AIに質問: 右下のボタンと、右から出るパネル */
        .genba3d-ask-btn {
            position: fixed;
            right: 16px;
            bottom: 16px;
            z-index: 1030;
            border-radius: 999px;
            padding: .6rem 1.1rem;
            font-weight: 600;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .28);
        }
        .genba3d-chat.offcanvas {
            --bs-offcanvas-width: min(420px, 100vw);
            box-shadow: -8px 0 24px rgba(15, 23, 42, .14);
        }
        .genba3d-chat .offcanvas-body {
            display: flex;
            flex-direction: column;
            padding: 0;
            min-height: 0;
        }
        .genba3d-chat-log {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 1rem;
            background: #f8fafc;
            overscroll-behavior: contain;
        }
        .genba3d-chat-msg {
            width: fit-content;
            max-width: 88%;
            margin-bottom: .75rem;
            padding: .55rem .8rem;
            border-radius: 14px;
            font-size: .95rem;
            line-height: 1.65;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }
        .genba3d-chat-msg.is-user {
            margin-left: auto;
            background: #1d4ed8;
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .genba3d-chat-msg.is-ai {
            margin-right: auto;
            background: #fff;
            color: #0f172a;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 4px;
        }
        .genba3d-chat-msg.is-error {
            margin-right: auto;
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .genba3d-chat-msg.is-wait {
            color: #64748b;
        }
        .genba3d-chat-examples .btn {
            text-align: left;
            white-space: normal;
        }
        .genba3d-chat-form {
            flex: 0 0 auto;
            padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom));
            border-top: 1px solid #e2e8f0;
            background: #fff;
        }
        .genba3d-chat-form textarea {
            resize: none;
        }
        @endif
        @media (max-width: 991.98px) {
            .genba3d-frame {
                height: calc(100vh - 295px);
                height: calc(100dvh - 295px);
                min-height: 460px;
            }
            .genba3d-frame.is-document {
                height: 1800px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div class="min-w-0">
            <nav class="small mb-1" aria-label="現在の位置">
                <a href="{{ route('genba3d.index') }}" class="text-decoration-none">現場3D</a>
                <span class="text-muted mx-1" aria-hidden="true">›</span>
                <span class="text-muted">{{ $project['name'] ?? '現場' }}</span>
            </nav>
            <h1 class="h4 mb-1 fw-semibold">{{ $project['name'] ?? '現場3D' }}</h1>
            <div class="text-muted small">
                @if ($extra !== null)
                    {{ $extra['note'] ?? '' }}
                @elseif ($isSchedule)
                    設計図と数量から組んだ施工手順と工程表の案です。日数は概算なので、標準歩掛や実績に置き換えて使ってください。
                @else
                    設計図（2次元）から組み立てた3Dモデルです。数量や出来形の確認には元の図面を使ってください。
                @endif
            </div>
        </div>
        <a href="{{ $frameUrl }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-shrink-0">
            全画面で開く
        </a>
    </div>

    <ul class="nav nav-pills genba3d-tabs gap-2 mb-2" aria-label="現場を選ぶ">
        @foreach ($sites as $slug => $site)
            @php
                // 工程表を見ているときは、切り替え先の現場でも工程表を開く（無い現場は3Dモデルへ）。
                $siteRoute = $isSchedule && ! empty($site['schedule_file']) ? 'genba3d.schedule' : 'genba3d.show';
            @endphp
            <li class="nav-item">
                <a
                    href="{{ route($siteRoute, ['site' => $slug]) }}"
                    class="nav-link py-1 px-3 {{ $slug === $currentSlug ? 'active' : '' }}"
                    @if ($slug === $currentSlug) aria-current="page" @endif
                >{{ $site['name'] }}</a>
            </li>
        @endforeach
    </ul>

    <p class="small text-muted mb-2">{{ $current['summary'] }}</p>

    <ul class="nav genba3d-kinds border-bottom mb-3" aria-label="表示する内容">
        <li class="nav-item">
            <a href="{{ route('genba3d.show', ['site' => $currentSlug]) }}" class="nav-link px-3 {{ $tab === 'model' ? 'active' : '' }}" @if ($tab === 'model') aria-current="page" @endif>3Dモデル</a>
        </li>
        @if ($hasSchedule)
            <li class="nav-item">
                <a href="{{ route('genba3d.schedule', ['site' => $currentSlug]) }}" class="nav-link px-3 {{ $isSchedule ? 'active' : '' }}" @if ($isSchedule) aria-current="page" @endif>施工手順と工程表</a>
            </li>
        @endif
        @foreach ($extraPages as $key => $page)
            <li class="nav-item">
                <a href="{{ route('genba3d.page', ['site' => $currentSlug, 'page' => $key]) }}" class="nav-link px-3 {{ $extra !== null && $key === $pageKey ? 'active' : '' }}" @if ($extra !== null && $key === $pageKey) aria-current="page" @endif>{{ $page['label'] }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card shadow-sm border-0 overflow-hidden">
        <iframe
            id="genba3dFrame"
            class="genba3d-frame {{ $isDocument ? 'is-document' : '' }}"
            src="{{ $frameUrl }}"
            title="{{ $current['name'] }} の{{ $frameTitle }}"
            allow="fullscreen"
        ></iframe>
    </div>

    @if (! empty($chatEnabled))
        <button
            type="button"
            class="btn btn-primary genba3d-ask-btn"
            data-bs-toggle="offcanvas"
            data-bs-target="#genba3dChat"
            aria-controls="genba3dChat"
        >💬 AIに質問</button>

        {{-- 3Dモデルや工程表を見ながら使えるよう、背景は暗くせず、下の画面も操作できるままにする --}}
        <div
            class="offcanvas offcanvas-end genba3d-chat"
            tabindex="-1"
            id="genba3dChat"
            aria-labelledby="genba3dChatLabel"
            data-bs-scroll="true"
            data-bs-backdrop="false"
            data-ask-url="{{ route('genba3d.ask', ['site' => $currentSlug]) }}"
            data-site="{{ $currentSlug }}"
        >
            <div class="offcanvas-header border-bottom">
                <div class="min-w-0">
                    <h2 class="offcanvas-title h6 mb-0 fw-semibold" id="genba3dChatLabel">AIに質問</h2>
                    <div class="small text-muted text-truncate">{{ $current['name'] }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
            </div>
            <div class="offcanvas-body">
                <div class="genba3d-chat-log" id="genba3dChatLog" aria-live="polite">
                    <div id="genba3dChatIntro">
                        <p class="small text-muted mb-2">
                            この現場の3Dモデルと工程表に載っている内容（寸法・数量・手順・図面の確認事項）から答えます。図面そのものは読んでいません。
                        </p>
                        <p class="small text-muted mb-3">
                            AIの回答は間違うことがあります。寸法や数量は、元の図面で確かめてから使ってください。
                        </p>
                        <div class="genba3d-chat-examples d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-example>図面どうしで食い違っている点を教えて</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-example>コンクリートの種類と数量は？</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-example>初期設定の工程で、打設はいつ頃？</button>
                        </div>
                    </div>
                </div>
                <form class="genba3d-chat-form" id="genba3dChatForm" autocomplete="off">
                    <label for="genba3dChatInput" class="visually-hidden">質問</label>
                    <div class="d-flex align-items-end gap-2">
                        <textarea
                            class="form-control"
                            id="genba3dChatInput"
                            rows="2"
                            maxlength="{{ (int) config('genba3d.chat.max_question_length', 600) }}"
                            placeholder="この現場について質問を入力"
                        ></textarea>
                        <button type="submit" class="btn btn-primary flex-shrink-0" id="genba3dChatSend">送信</button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="small text-muted" id="genba3dChatNote"></span>
                        <button type="button" class="btn btn-link btn-sm text-muted p-0" id="genba3dChatClear" hidden>会話を消す</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($isDocument)
    @push('scripts')
        <script>
            // 工程表・資料の枠を中身の高さに合わせる。日数を書き換えて行数が変わったときも追従する。
            (function () {
                var frame = document.getElementById('genba3dFrame');
                if (!frame) return;
                var observed = null;
                function fit() {
                    try {
                        var doc = frame.contentDocument;
                        // 読み込み前の空のページ（about:blank）では測らない
                        if (!doc || !doc.body || !doc.location || doc.location.href === 'about:blank') return;
                        var h = Math.max(doc.body.scrollHeight, doc.body.offsetHeight);
                        if (h > 0) frame.style.height = (h + 24) + 'px';
                        if (window.ResizeObserver && observed !== doc.body) {
                            new ResizeObserver(fit).observe(doc.body);
                            observed = doc.body;
                        }
                    } catch (e) { /* 読めないときは CSS の高さのまま */ }
                }
                // このスクリプトより先に枠の読み込みが終わっていることがあるので、load 待ちだけにしない
                frame.addEventListener('load', fit);
                fit();
                [300, 1000, 2500].forEach(function (ms) { window.setTimeout(fit, ms); });
                window.addEventListener('resize', fit);
            })();
        </script>
    @endpush
@endif

@if (! empty($chatEnabled))
    @push('scripts')
        <script>
            // AIに質問: 質問を送り、回答を吹き出しで並べる。会話はこのタブを閉じるまで、現場ごとに覚えておく。
            (function () {
                var panel = document.getElementById('genba3dChat');
                if (!panel) return;
                var log = document.getElementById('genba3dChatLog');
                var intro = document.getElementById('genba3dChatIntro');
                var form = document.getElementById('genba3dChatForm');
                var input = document.getElementById('genba3dChatInput');
                var send = document.getElementById('genba3dChatSend');
                var note = document.getElementById('genba3dChatNote');
                var clear = document.getElementById('genba3dChatClear');
                var url = panel.dataset.askUrl;
                var storeKey = 'genba3d-chat:' + panel.dataset.site;
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var history = [];   // {role: 'user' | 'assistant', content: string}
                var busy = false;

                function load() {
                    try {
                        var saved = JSON.parse(window.sessionStorage.getItem(storeKey) || '[]');
                        if (Array.isArray(saved)) {
                            history = saved.filter(function (m) {
                                return m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string';
                            });
                        }
                    } catch (e) { history = []; }
                }
                function save() {
                    try { window.sessionStorage.setItem(storeKey, JSON.stringify(history.slice(-20))); } catch (e) { /* 保存できなくても動かす */ }
                }
                function bubble(kind, text) {
                    var el = document.createElement('div');
                    el.className = 'genba3d-chat-msg ' + kind;
                    el.textContent = text;
                    log.appendChild(el);
                    log.scrollTop = log.scrollHeight;
                    return el;
                }
                function render() {
                    Array.prototype.slice.call(log.querySelectorAll('.genba3d-chat-msg')).forEach(function (el) { el.remove(); });
                    history.forEach(function (m) { bubble(m.role === 'user' ? 'is-user' : 'is-ai', m.content); });
                    intro.hidden = history.length > 0;
                    clear.hidden = history.length === 0;
                }
                function setBusy(on) {
                    busy = on;
                    send.disabled = on;
                    input.disabled = on;
                }

                function ask(text) {
                    text = (text || '').trim();
                    if (!text || busy) return;
                    history.push({ role: 'user', content: text });
                    intro.hidden = true;
                    clear.hidden = false;
                    bubble('is-user', text);
                    input.value = '';
                    var wait = bubble('is-ai is-wait', '考えています…');
                    setBusy(true);

                    window.fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': tokenMeta ? tokenMeta.getAttribute('content') : ''
                        },
                        body: JSON.stringify({ messages: history.slice(-9) })
                    }).then(function (res) {
                        return res.text().then(function (body) {
                            var data = null;
                            try { data = JSON.parse(body); } catch (e) { /* JSON でない＝ログイン画面などが返ってきた */ }
                            return { ok: res.ok, status: res.status, data: data };
                        });
                    }).then(function (r) {
                        wait.remove();
                        if (r.ok && r.data && typeof r.data.answer === 'string') {
                            var answer = r.data.answer + (r.data.truncated ? '\n（回答が長くなったため、途中で切れています）' : '');
                            history.push({ role: 'assistant', content: answer });
                            bubble('is-ai', answer);
                            if (typeof r.data.remaining === 'number') {
                                note.textContent = '今日はあと ' + r.data.remaining + ' 回';
                            }
                        } else {
                            // 答えが返らなかった質問は、次の送信に混ざらないよう履歴から外す
                            history.pop();
                            var message = r.data && r.data.message;
                            if (!message) {
                                // 419＝画面を開いたままで期限切れ。200 なのに JSON でない＝ログイン画面へ送られた。
                                message = (r.status === 419 || r.status === 401 || (r.ok && !r.data))
                                    ? 'ログインの有効期限が切れたようです。ページを読み込み直してから、もう一度お試しください。'
                                    : '回答を受け取れませんでした。少し時間をおいて、もう一度お試しください。';
                            }
                            bubble('is-error', message);
                            input.value = text;
                        }
                        save();
                    }).catch(function () {
                        wait.remove();
                        history.pop();
                        bubble('is-error', '通信できませんでした。電波の状態を確かめて、もう一度お試しください。');
                        input.value = text;
                        save();
                    }).then(function () {
                        setBusy(false);
                        input.focus();
                    });
                }

                form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
                // パソコンでは Enter で送信（変換の確定中と Shift+Enter は改行）。スマホでは Enter は改行のまま。
                input.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' || e.shiftKey || e.isComposing || e.keyCode === 229) return;
                    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) return;
                    e.preventDefault();
                    ask(input.value);
                });
                Array.prototype.slice.call(panel.querySelectorAll('[data-example]')).forEach(function (b) {
                    b.addEventListener('click', function () { ask(b.textContent); });
                });
                clear.addEventListener('click', function () {
                    if (busy) return;
                    history = [];
                    save();
                    render();
                    input.focus();
                });
                panel.addEventListener('shown.bs.offcanvas', function () {
                    log.scrollTop = log.scrollHeight;
                    input.focus();
                });

                load();
                render();
            })();
        </script>
    @endpush
@endif
