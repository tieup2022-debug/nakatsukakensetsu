@extends('layouts.app')

@push('styles')
    @include('genba3d.partials.koe-style')
    <style>
        .koe-index { max-width: 860px; }
        .koe-summary .btn { font-size: .85rem; }
    </style>
@endpush

@section('content')
    <div class="koe-index">
        <nav class="small mb-1" aria-label="現在の位置">
            <a href="{{ route('genba3d.index') }}" class="text-decoration-none">現場3D</a>
            <span class="text-muted mx-1" aria-hidden="true">›</span>
            <span class="text-muted">現場の声</span>
        </nav>
        <h1 class="h4 mb-1 fw-semibold">現場の声</h1>
        <p class="text-muted small mb-3">
            3Dモデルや工程表を見た人の「こうしてほしい」「ここはこうだよ」を、全現場まとめて並べています。
            書き込みは各現場の画面右下の「📌 現場の声」から。{{ $isMaster ? '対応状況はここでも変えられます。' : '' }}
        </p>

        @if (! $ready)
            <div class="alert alert-warning">現場の声はまだ準備中です（データベースの更新待ち）。</div>
        @endif

        <div class="row g-2 mb-3">
            <div class="col-sm-7">
                <label for="koeSite" class="form-label small mb-1">現場</label>
                <select id="koeSite" class="form-select form-select-sm">
                    <option value="">すべての現場</option>
                    @foreach ($projects as $project)
                        <optgroup label="{{ $project['name'] }}">
                            @foreach ($project['sites'] as $slug => $site)
                                <option value="{{ $slug }}" @selected($siteFilter === $slug)>{{ $site['name'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-5">
                <label for="koeStatus" class="form-label small mb-1">対応状況</label>
                <select id="koeStatus" class="form-select form-select-sm">
                    <option value="active">未対応・対応中</option>
                    <option value="">すべて</option>
                    @foreach ($statuses as $k => $label)
                        <option value="{{ $k }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="small text-muted mb-2" id="koeSummary"></div>
        <div id="koeList"
             data-list-url="{{ $listUrl }}"
             data-reply-url="{{ route('genba3d.koe.reply', ['id' => '__ID__']) }}"
             data-status-url="{{ route('genba3d.koe.status', ['id' => '__ID__']) }}"
             data-destroy-url="{{ route('genba3d.koe.destroy', ['id' => '__ID__']) }}"
             data-show-url="{{ route('genba3d.show', ['site' => '__SITE__']) }}?koe=__ID__"
             data-statuses='@json($statuses)'
        ><p class="koe-empty">読み込み中…</p></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/genba3d-koe.js') }}?v={{ @filemtime(public_path('js/genba3d-koe.js')) ?: '1' }}"></script>
    <script>
        (function () {
            var box = document.getElementById('koeList');
            var siteSel = document.getElementById('koeSite');
            var statusSel = document.getElementById('koeStatus');
            var summary = document.getElementById('koeSummary');
            var D = box.dataset;
            var statuses = JSON.parse(D.statuses || '{}');

            function load() {
                var url = D.listUrl + '?site=' + encodeURIComponent(siteSel.value)
                    + (statusSel.value && statusSel.value !== 'active' ? '&status=' + encodeURIComponent(statusSel.value) : '');
                window.Genba3dKoe.api(url).then(function (r) {
                    if (!r.ok) { box.textContent = r.message; return; }
                    var items = r.data.items || [];
                    if (statusSel.value === 'active') {
                        items = items.filter(function (it) { return it.status === 'open' || it.status === 'doing'; });
                    }
                    summary.textContent = items.length + '件';
                    window.Genba3dKoe.renderList(box, items, {
                        isMaster: !!r.data.isMaster,
                        statuses: statuses,
                        urls: { reply: D.replyUrl, status: D.statusUrl, destroy: D.destroyUrl },
                        showSite: true,
                        emptyText: '該当する書き込みはありません。',
                        onChanged: load,
                        pinHref: function (it) { return D.showUrl.replace('__SITE__', it.site).replace('__ID__', it.id); }
                    });
                });
            }
            siteSel.addEventListener('change', load);
            statusSel.addEventListener('change', load);
            load();
        })();
    </script>
@endpush
