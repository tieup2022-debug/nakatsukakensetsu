@extends('layouts.app')

@push('styles')
    <style>
        /* 3Dモデルの枠。ヘッダー・タブ・フッターを除いた高さいっぱいに広げる */
        .genba3d-frame {
            display: block;
            width: 100%;
            height: calc(100vh - 270px);
            height: calc(100dvh - 270px);
            min-height: 520px;
            border: 0;
            background: #e9eef0;
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
        @media (max-width: 991.98px) {
            .genba3d-frame {
                height: calc(100vh - 230px);
                height: calc(100dvh - 230px);
                min-height: 460px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div class="min-w-0">
            <h1 class="h4 mb-1 fw-semibold">現場3D</h1>
            <div class="text-muted small">
                設計図（2次元）から組み立てた3Dモデルです。数量や出来形の確認には元の図面を使ってください。
            </div>
        </div>
        <a href="{{ route('genba3d.model', ['site' => $currentSlug]) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm flex-shrink-0">
            全画面で開く
        </a>
    </div>

    <ul class="nav nav-pills genba3d-tabs gap-2 mb-2" aria-label="現場を選ぶ">
        @foreach ($sites as $slug => $site)
            <li class="nav-item">
                <a
                    href="{{ route('genba3d.show', ['site' => $slug]) }}"
                    class="nav-link py-1 px-3 {{ $slug === $currentSlug ? 'active' : '' }}"
                    @if ($slug === $currentSlug) aria-current="page" @endif
                >{{ $site['name'] }}</a>
            </li>
        @endforeach
    </ul>

    <p class="small text-muted mb-2">{{ $current['summary'] }}</p>

    <div class="card shadow-sm border-0 overflow-hidden">
        <iframe
            class="genba3d-frame"
            src="{{ route('genba3d.model', ['site' => $currentSlug]) }}"
            title="{{ $current['name'] }} の3Dモデル"
            allow="fullscreen"
        ></iframe>
    </div>
@endsection
