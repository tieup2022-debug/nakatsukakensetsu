@extends('layouts.app')

@push('styles')
    <style>
        .genba3d-site + .genba3d-site {
            border-top: 1px solid #e2e8f0;
        }
        .genba3d-upcoming .badge {
            font-weight: 500;
            font-size: .85rem;
            color: #475569;
            background: #e2e8f0;
        }
    </style>
@endpush

@section('content')
    <div class="mb-4">
        <h1 class="h4 mb-1 fw-semibold">現場3D</h1>
        <div class="text-muted small">
            設計図から組み立てた3Dモデルと、施工手順・工程表を、工事ごとにまとめています。見たいものを押すと、その画面が開きます。
        </div>
    </div>

    @foreach ($projects as $key => $project)
        <section class="card shadow-sm border-0 mb-4" id="{{ $key }}" aria-labelledby="genba3d-project-{{ $key }}">
            <div class="card-header bg-white d-flex align-items-baseline gap-2 py-3">
                <h2 class="h5 mb-0 fw-semibold" id="genba3d-project-{{ $key }}">{{ $project['name'] }}</h2>
                <span class="small text-muted">{{ count($project['sites']) }}現場</span>
            </div>
            <div class="card-body py-0">
                @foreach ($project['sites'] as $slug => $site)
                    <div class="genba3d-site py-3">
                        <div class="fw-semibold">{{ $site['name'] }}</div>
                        <div class="small text-muted mb-2">{{ $site['summary'] ?? '' }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('genba3d.show', ['site' => $slug]) }}" class="btn btn-primary btn-sm">3Dモデル</a>
                            @if (! empty($site['schedule_file']))
                                <a href="{{ route('genba3d.schedule', ['site' => $slug]) }}" class="btn btn-outline-primary btn-sm">施工手順と工程表</a>
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
