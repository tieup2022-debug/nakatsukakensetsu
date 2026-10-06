@extends('layouts.app')

@php
    $tab = $tab ?? 'model';
    $isSchedule = $tab === 'schedule';
    $hasSchedule = ! empty($current['schedule_file']);
    $frameRoute = $isSchedule ? 'genba3d.schedule.page' : 'genba3d.model';
@endphp

@push('styles')
    <style>
        /* 3Dモデルの枠。ヘッダー・タブ・フッターを除いた高さいっぱいに広げる */
        .genba3d-frame {
            display: block;
            width: 100%;
            height: calc(100vh - 310px);
            height: calc(100dvh - 310px);
            min-height: 520px;
            border: 0;
            background: #e9eef0;
        }
        /* 工程表は縦に長い文書なので、中身の高さに合わせて枠を伸ばす（下のスクリプトで設定） */
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
        @media (max-width: 991.98px) {
            .genba3d-frame {
                height: calc(100vh - 270px);
                height: calc(100dvh - 270px);
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
            <h1 class="h4 mb-1 fw-semibold">現場3D</h1>
            <div class="text-muted small">
                @if ($isSchedule)
                    設計図と数量から組んだ施工手順と工程表の案です。日数は概算なので、標準歩掛や実績に置き換えて使ってください。
                @else
                    設計図（2次元）から組み立てた3Dモデルです。数量や出来形の確認には元の図面を使ってください。
                @endif
            </div>
        </div>
        <a href="{{ route($frameRoute, ['site' => $currentSlug]) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-shrink-0">
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
            <a href="{{ route('genba3d.show', ['site' => $currentSlug]) }}" class="nav-link px-3 {{ $isSchedule ? '' : 'active' }}" @if (! $isSchedule) aria-current="page" @endif>3Dモデル</a>
        </li>
        @if ($hasSchedule)
            <li class="nav-item">
                <a href="{{ route('genba3d.schedule', ['site' => $currentSlug]) }}" class="nav-link px-3 {{ $isSchedule ? 'active' : '' }}" @if ($isSchedule) aria-current="page" @endif>施工手順と工程表</a>
            </li>
        @endif
    </ul>

    <div class="card shadow-sm border-0 overflow-hidden">
        <iframe
            id="genba3dFrame"
            class="genba3d-frame {{ $isSchedule ? 'is-document' : '' }}"
            src="{{ route($frameRoute, ['site' => $currentSlug]) }}"
            title="{{ $current['name'] }} の{{ $isSchedule ? '施工手順と工程表' : '3Dモデル' }}"
            allow="fullscreen"
        ></iframe>
    </div>
@endsection

@if ($isSchedule)
    @push('scripts')
        <script>
            // 工程表の枠を中身の高さに合わせる。日数を書き換えて行数が変わったときも追従する。
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
