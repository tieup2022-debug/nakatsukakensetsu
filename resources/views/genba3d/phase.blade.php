@extends('layouts.app')

@section('content')
    @php
        $label = \App\Support\Genba3dCatalog::PHASES[$phase];
        $fmt = fn (?string $d): string => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y/n/j') : '未入力';
    @endphp
    <div class="mb-3">
        @include('genba3d.partials.phases', ['project' => $project, 'phase' => $phase])
        <h1 class="h4 mb-1 fw-semibold">{{ $project['name'] }}　{{ $label }}</h1>
        <div class="text-muted small">工期 {{ $fmt($term['start']) }} 〜 {{ $fmt($term['end']) }}</div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <span class="badge text-bg-secondary mb-2">準備中</span>
            <h2 class="h5 fw-semibold">{{ $label }}</h2>
            <p class="mb-3">{{ \App\Support\Genba3dCatalog::PHASE_NOTES[$phase] }}</p>
            <p class="small text-muted mb-2">いま見られるもの</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ \App\Support\Genba3dCatalog::phaseUrl($projectKey, $project, 'keikaku') }}" class="btn btn-outline-primary btn-sm">計画（施工手順・工程表）</a>
                <a href="{{ \App\Support\Genba3dCatalog::phaseUrl($projectKey, $project, 'henko') }}" class="btn btn-outline-primary btn-sm">変更（3Dモデル）</a>
            </div>
        </div>
    </div>
@endsection
