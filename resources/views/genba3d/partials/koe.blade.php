{{-- 現場の声: 右下のボタン、右から出るパネル（書き込み欄と一覧）、ピン立て中の案内 --}}
@php
    $koeTab = $tab === 'page' ? $pageKey : $tab;
    $koeCanPin = $tab === 'model';
@endphp

@push('styles')
    @include('genba3d.partials.koe-style')
    <style>
        .genba3d-koe-btn {
            position: fixed;
            right: 16px;
            bottom: {{ ! empty($chatEnabled) ? '72px' : '16px' }};
            z-index: 1030;
            border-radius: 999px;
            padding: .6rem 1.1rem;
            font-weight: 600;
            box-shadow: 0 6px 18px rgba(15, 23, 42, .28);
            background: #fff;
            color: #1d4ed8;
            border: 2px solid #1d4ed8;
        }
        .genba3d-koe-btn:hover { background: #eff6ff; color: #1d4ed8; }
        .genba3d-koe-btn .badge { vertical-align: 2px; }
        .genba3d-koe.offcanvas { --bs-offcanvas-width: min(440px, 100vw); box-shadow: -8px 0 24px rgba(15, 23, 42, .14); }
        .genba3d-koe .offcanvas-body { padding: 0; display: flex; flex-direction: column; min-height: 0; }
        .genba3d-koe-form { padding: .8rem 1rem; border-bottom: 1px solid #e2e8f0; background: #fff; }
        .genba3d-koe-kinds .btn { font-size: .85rem; }
        .genba3d-koe-list { flex: 1 1 auto; overflow-y: auto; padding: .8rem 1rem; background: #f8fafc; overscroll-behavior: contain; }
        .genba3d-koe-filters .btn { font-size: .8rem; padding: .15rem .6rem; }
        .genba3d-koe-banner {
            position: fixed;
            left: 50%;
            top: 12px;
            transform: translateX(-50%);
            z-index: 1060;
            width: min(560px, calc(100vw - 24px));
            background: #fffbeb;
            border: 2px solid #f59e0b;
            border-radius: 12px;
            padding: .6rem .8rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .2);
            font-size: .92rem;
        }
    </style>
@endpush

<button type="button" class="btn genba3d-koe-btn" data-bs-toggle="offcanvas" data-bs-target="#genba3dKoe" aria-controls="genba3dKoe">
    📌 現場の声 <span class="badge rounded-pill text-bg-danger" id="genba3dKoeCount" hidden></span>
</button>

<div class="genba3d-koe-banner" id="genba3dKoeBanner" role="status" hidden>
    <div class="d-flex align-items-center gap-2">
        <span class="flex-grow-1" id="genba3dKoeBannerText"></span>
        <button type="button" class="btn btn-outline-secondary btn-sm flex-shrink-0" id="genba3dKoeBannerCancel">やめる</button>
    </div>
</div>

{{-- 3Dモデルを触りながら書けるよう、背景は暗くせず、下の画面も操作できるままにする --}}
<div
    class="offcanvas offcanvas-end genba3d-koe"
    tabindex="-1"
    id="genba3dKoe"
    aria-labelledby="genba3dKoeLabel"
    data-bs-scroll="true"
    data-bs-backdrop="false"
    data-site="{{ $currentSlug }}"
    data-tab="{{ $koeTab }}"
    data-can-pin="{{ $koeCanPin ? '1' : '0' }}"
    data-list-url="{{ route('genba3d.koe.list', ['site' => $currentSlug]) }}"
    data-store-url="{{ route('genba3d.koe.store', ['site' => $currentSlug]) }}"
    data-reply-url="{{ route('genba3d.koe.reply', ['id' => '__ID__']) }}"
    data-status-url="{{ route('genba3d.koe.status', ['id' => '__ID__']) }}"
    data-destroy-url="{{ route('genba3d.koe.destroy', ['id' => '__ID__']) }}"
    data-model-url="{{ route('genba3d.show', ['site' => $currentSlug]) }}?koe=__ID__"
    data-statuses='@json(\App\Services\Genba3dFeedbackService::statuses())'
>
    <div class="offcanvas-header border-bottom">
        <div class="min-w-0">
            <h2 class="offcanvas-title h6 mb-0 fw-semibold" id="genba3dKoeLabel">現場の声</h2>
            <div class="small text-muted text-truncate">{{ $current['name'] }}　<a href="{{ route('genba3d.koe.index') }}">全現場の一覧</a></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="閉じる"></button>
    </div>
    <div class="offcanvas-body">
        <form class="genba3d-koe-form" id="genba3dKoeForm" autocomplete="off">
            <div class="btn-group genba3d-koe-kinds w-100 mb-2" role="group" aria-label="書き込みの種類">
                @foreach (\App\Services\Genba3dFeedbackService::kinds() as $k => $label)
                    <input type="radio" class="btn-check" name="koe-kind" id="koe-kind-{{ $k }}" value="{{ $k }}" @checked($loop->first)>
                    <label class="btn btn-outline-primary" for="koe-kind-{{ $k }}">{{ $label }}</label>
                @endforeach
            </div>
            <label for="genba3dKoeInput" class="visually-hidden">内容</label>
            <textarea
                class="form-control"
                id="genba3dKoeInput"
                rows="3"
                maxlength="{{ \App\Http\Controllers\Genba3dFeedbackController::MAX_BODY }}"
                placeholder="例）この足場の位置は実際には1m海側／ここの寸法も表示してほしい"
            ></textarea>
            @if ($koeCanPin)
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <button type="button" class="btn btn-outline-warning btn-sm text-dark" id="genba3dKoePinBtn">📍 3Dにピンを立てる</button>
                    <span class="small text-success" id="genba3dKoePinNote"></span>
                    <button type="button" class="btn btn-link btn-sm p-0" id="genba3dKoePinClear" hidden>外す</button>
                </div>
            @endif
            <div class="d-flex align-items-center gap-2 mt-2">
                <span class="koe-error flex-grow-1" id="genba3dKoeError" role="alert"></span>
                <button type="submit" class="btn btn-primary btn-sm px-3" id="genba3dKoeSend">書き込む</button>
            </div>
        </form>
        <div class="genba3d-koe-list">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="btn-group genba3d-koe-filters" role="group" aria-label="表示する書き込み">
                    <button type="button" class="btn btn-outline-secondary" data-koe-filter="active">未対応・対応中</button>
                    <button type="button" class="btn btn-outline-secondary" data-koe-filter="closed">対応済</button>
                    <button type="button" class="btn btn-outline-secondary" data-koe-filter="mine">自分</button>
                    <button type="button" class="btn btn-outline-secondary" data-koe-filter="all">すべて</button>
                </div>
                @if ($koeCanPin)
                    <div class="form-check form-check-inline small mb-0 ms-auto">
                        <input class="form-check-input" type="checkbox" id="genba3dKoeShowDone">
                        <label class="form-check-label" for="genba3dKoeShowDone">対応済のピンも出す</label>
                    </div>
                @endif
            </div>
            <div id="genba3dKoeList" aria-live="polite"><p class="koe-empty">読み込み中…</p></div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/genba3d-koe.js') }}?v={{ @filemtime(public_path('js/genba3d-koe.js')) ?: '1' }}"></script>
@endpush
