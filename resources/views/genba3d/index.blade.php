@extends('layouts.app')

@push('styles')
    <style>
        .genba3d-site + .genba3d-site {
            border-top: 1px solid #e2e8f0;
        }
        .genba3d-terms td, .genba3d-terms th { vertical-align: middle; white-space: nowrap; }
        .genba3d-terms .name { white-space: normal; min-width: 9em; }
        .genba3d-term-bar { position: relative; height: 8px; min-width: 120px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
        .genba3d-term-bar span { position: absolute; inset: 0 auto 0 0; background: #2563eb; border-radius: 999px; }
        .genba3d-term-bar.is-over span { background: #dc2626; }
        .genba3d-term-bar.is-late span { background: #d97706; }
        .genba3d-term-left.is-over { color: #b91c1c; font-weight: 600; }
        .genba3d-term-left.is-late { color: #b45309; font-weight: 600; }
        .genba3d-upcoming .badge {
            font-weight: 500;
            font-size: .85rem;
            color: #475569;
            background: #e2e8f0;
        }
    </style>
@endpush

@section('content')
    <div class="mb-4 d-flex flex-wrap align-items-start justify-content-between gap-2">
        <div>
            <h1 class="h4 mb-1 fw-semibold">現場3D</h1>
            <div class="text-muted small">
                設計図から組み立てた3Dモデルと、施工手順・工程表を、工事ごとにまとめています。見たいものを押すと、その画面が開きます。
            </div>
        </div>
        <a href="{{ route('genba3d.koe.index') }}" class="btn btn-outline-primary btn-sm flex-shrink-0">
            📌 現場の声
            @if (($koeOpen ?? 0) > 0)
                <span class="badge rounded-pill text-bg-danger">{{ $koeOpen }}</span>
            @endif
        </a>
    </div>

    @php
        $fmt = fn (?string $d): string => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y/n/j') : '—';
        // 工期を入力する画面（その工事で最初に工程表がある現場）
        $termLink = function (array $project): ?string {
            foreach ($project['sites'] as $slug => $site) {
                if (! empty($site['schedule_file'])) {
                    return route('genba3d.schedule', ['site' => $slug]);
                }
            }
            return null;
        };
    @endphp

    @if (! empty($projects))
        <section class="card shadow-sm border-0 mb-4" aria-labelledby="genba3d-terms-title">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0 fw-semibold" id="genba3d-terms-title">工期の一覧</h2>
                <div class="small text-muted">工期は各工事の「工程表」で入力します（誰でも入力でき、変更は工程表の「変更の記録」に残ります）。「設計図書の値」は図面・説明書から拾った値で、まだ契約の工期として保存されていません。</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 genba3d-terms">
                    <thead class="small text-muted">
                        <tr><th class="ps-3">工事</th><th>開始日</th><th>工期末</th><th>残り</th><th style="width:22%">経過</th><th class="pe-3"></th></tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Support\Genba3dCatalog::byYear($projects) as $yearKey => $yearGroup)
                        <tr class="table-light"><th colspan="6" class="ps-3 small text-muted fw-semibold">{{ $yearGroup['label'] }}</th></tr>
                        @foreach ($yearGroup['projects'] as $key => $project)
                            @php
                                $term = $terms[$key] ?? ['start' => null, 'end' => null, 'source' => null];
                                $pg = \App\Services\Genba3dTermService::progress($term);
                                $late = $pg['state'] === 'running' && $pg['left'] !== null && $pg['left'] <= 14;
                                $cls = $pg['state'] === 'over' ? 'is-over' : ($late ? 'is-late' : '');
                                $link = $termLink($project);
                            @endphp
                            <tr data-term="{{ $key }}">
                                <td class="ps-3 name"><a href="#{{ $key }}" class="text-decoration-none fw-semibold">{{ $project['name'] }}</a>
                                    @if (($term['source'] ?? null) === 'default')<span class="badge text-bg-light border ms-1 fw-normal">設計図書の値</span>@endif
                                    @if (($term['source'] ?? null) === 'saved' && ! empty($term['updatedBy']))
                                        <div class="small text-muted">更新：{{ $term['updatedBy'] }}（{{ $term['updatedAt'] }}）</div>
                                    @endif
                                </td>
                                <td class="small">{{ $fmt($term['start']) }}</td>
                                <td class="small">{{ $fmt($term['end']) }}</td>
                                <td class="small genba3d-term-left {{ $cls }}">
                                    @if ($pg['state'] === 'none') <span class="text-muted">未入力</span>
                                    @elseif ($pg['state'] === 'over') 工期末を{{ -$pg['left'] }}日過ぎ
                                    @elseif ($pg['state'] === 'before') 着手前（あと{{ $pg['left'] }}日で工期末）
                                    @else あと{{ $pg['left'] }}日
                                    @endif
                                </td>
                                <td>
                                    @if ($pg['percent'] !== null)
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="genba3d-term-bar flex-grow-1 {{ $cls }}" role="img" aria-label="工期の経過 {{ $pg['percent'] }}%"><span style="width: {{ $pg['percent'] }}%"></span></div>
                                            <span class="small text-muted">{{ $pg['percent'] }}%</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    @if ($link)<a href="{{ $link }}" class="btn btn-link btn-sm p-0">{{ ($term['source'] ?? null) === 'saved' ? '工程表' : '入力する' }}</a>@endif
                                </td>
                            </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @foreach (\App\Support\Genba3dCatalog::byYear($projects) as $yearKey => $yearGroup)
    <h2 class="h5 fw-semibold mt-4 mb-3 pb-1 border-bottom" id="year-{{ $yearKey }}">{{ $yearGroup['label'] }}</h2>
    @foreach ($yearGroup['projects'] as $key => $project)
        <section class="card shadow-sm border-0 mb-4" id="{{ $key }}" aria-labelledby="genba3d-project-{{ $key }}">
            <div class="card-header bg-white d-flex align-items-baseline gap-2 py-3">
                <h3 class="h5 mb-0 fw-semibold" id="genba3d-project-{{ $key }}">{{ $project['name'] }}</h3>
                <span class="small text-muted">{{ count($project['sites']) }}現場</span>
                @php $term = $terms[$key] ?? ['start' => null, 'end' => null]; @endphp
                @if ($term['start'] || $term['end'])
                    <span class="small text-muted ms-auto">工期 {{ $fmt($term['start']) }} 〜 {{ $fmt($term['end']) }}</span>
                @endif
            </div>
            <div class="px-3 pt-3">
                <div class="d-flex flex-wrap align-items-center gap-2 small" aria-label="工程表の種類">
                    <span class="text-muted">工程表：</span>
                    @foreach (\App\Support\Genba3dCatalog::PHASES as $phaseKey => $phaseLabel)
                        @php $phaseReady = $phaseKey === 'keikaku'; @endphp
                        <a href="{{ \App\Support\Genba3dCatalog::phaseUrl($key, $project, $phaseKey) }}" class="btn btn-sm {{ $phaseReady ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $phaseLabel }}@if (! $phaseReady)<span class="ms-1 opacity-75" style="font-size:.7rem">準備中</span>@endif</a>
                    @endforeach
                </div>
            </div>
            <div class="card-body py-0">
                @foreach ($project['sites'] as $slug => $site)
                    <div class="genba3d-site py-3">
                        <div class="fw-semibold">{{ $site['name'] }}</div>
                        <div class="small text-muted mb-2">{{ $site['summary'] ?? '' }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('genba3d.show', ['site' => $slug]) }}" class="btn btn-primary btn-sm">3Dモデル</a>
                            @if (! empty($site['schedule_file']))
                                <a href="{{ route('genba3d.steps', ['site' => $slug]) }}" class="btn btn-outline-primary btn-sm">施工手順</a>
                                <a href="{{ route('genba3d.schedule', ['site' => $slug]) }}" class="btn btn-outline-primary btn-sm">工程表</a>
                            @endif
                            @foreach ((array) ($site['pages'] ?? []) as $pageKey => $page)
                                <a href="{{ route('genba3d.page', ['site' => $slug, 'page' => $pageKey]) }}" class="btn btn-outline-primary btn-sm">{{ $page['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
    @endforeach

    @if (empty($projects))
        <div class="alert alert-secondary">まだ公開している現場がありません。</div>
    @endif

    @if (! empty($upcoming))
        <section class="genba3d-upcoming" aria-labelledby="genba3d-upcoming">
            <h2 class="h6 fw-semibold text-muted mb-2" id="genba3d-upcoming">準備中の工事</h2>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($upcoming as $project)
                    <span class="badge rounded-pill px-3 py-2">{{ $project['name'] }}</span>
                @endforeach
            </div>
            <p class="small text-muted mt-2 mb-0">3Dモデルができた工事から、上の一覧に加えていきます。</p>
        </section>
    @endif
@endsection
