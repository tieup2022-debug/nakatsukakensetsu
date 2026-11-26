{{-- 工事ごとの工程表の種類（契約・計画・変更・実施・ネットワーク工程表）。$project（key・name・sites）と $phase を渡す --}}
@php
    $phaseYear = \App\Support\Genba3dCatalog::year($project['key'] ?? '', $project['name'] ?? null);
@endphp
<nav class="small mb-1" aria-label="現在の位置">
    <a href="{{ route('genba3d.index') }}" class="text-decoration-none">現場3D</a>
    @if ($phaseYear !== null)
        <span class="text-muted mx-1" aria-hidden="true">›</span>
        <a href="{{ route('genba3d.index') }}#year-r{{ sprintf('%02d', $phaseYear) }}" class="text-decoration-none">令和{{ $phaseYear }}年度</a>
    @endif
    <span class="text-muted mx-1" aria-hidden="true">›</span>
    <span class="text-muted">{{ $project['name'] ?? '現場' }}</span>
    @if (! empty($phase))
        <span class="text-muted mx-1" aria-hidden="true">›</span>
        <span class="text-muted">{{ \App\Support\Genba3dCatalog::PHASES[$phase] ?? '' }}</span>
    @endif
</nav>
@push('styles')
    <style>
        .genba3d-phases { display: flex; flex-wrap: wrap; gap: 0; margin: .25rem 0 .9rem; border: 1px solid #cbd5e1; border-radius: 8px; overflow: hidden; width: fit-content; max-width: 100%; background: #fff; }
        .genba3d-phases a { padding: .4rem .95rem; font-size: .9rem; color: #334155; text-decoration: none; border-right: 1px solid #e2e8f0; white-space: nowrap; }
        .genba3d-phases a:last-child { border-right: 0; }
        .genba3d-phases a:hover { background: #f1f5f9; }
        .genba3d-phases a.active { background: #0f172a; color: #fff; font-weight: 600; }
        .genba3d-phases a .soon { font-size: .7rem; color: #94a3b8; margin-left: .3rem; }
        .genba3d-phases a.active .soon { color: #cbd5e1; }
        @media (max-width: 575.98px) { .genba3d-phases a { padding: .35rem .6rem; font-size: .82rem; } }
    </style>
@endpush
@if (! empty($project['key']))
    <div class="genba3d-phases" role="navigation" aria-label="工程表の種類">
        @foreach (\App\Support\Genba3dCatalog::PHASES as $pk => $label)
            @php $soon = ! in_array($pk, ['keikaku', 'henko'], true); @endphp
            <a href="{{ \App\Support\Genba3dCatalog::phaseUrl($project['key'], $project, $pk) }}" class="{{ ($phase ?? null) === $pk ? 'active' : '' }}" @if (($phase ?? null) === $pk) aria-current="page" @endif>{{ $label }}@if ($soon)<span class="soon">準備中</span>@endif</a>
        @endforeach
    </div>
@endif
