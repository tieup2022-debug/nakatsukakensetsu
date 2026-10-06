@php
    $dismiss = !empty($dismissOffcanvas);
@endphp
@if (! $dismiss)
    <div class="mb-4 small text-uppercase text-gray-400">メニュー</div>
@endif
<ul class="nav nav-pills flex-column gap-1">
    <li class="nav-item">
        {{-- data-bs-dismiss を付けない: モバイルで dismiss が先に効き href 遷移が阻害されることがある --}}
        <a href="{{ route('top.attendance') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('top.attendance') ? 'active' : 'text-white-50' }}">
            <span class="me-2">🕒</span> 勤怠
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('top.assignment') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('top.assignment') ? 'active' : 'text-white-50' }}">
            <span class="me-2">📋</span> 配置一覧
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('top.machine.schedule') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('top.machine.schedule') ? 'active' : 'text-white-50' }}">
            <span class="me-2">🛠️</span> 機械予定表
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('board.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('board.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">📣</span> 掲示板
        </a>
    </li>
    <li class="nav-item mt-3">
        <div class="small text-uppercase text-gray-400 mb-1">設定</div>
    </li>
    <li class="nav-item">
        <a href="{{ route('top.setting') }}" class="nav-link d-flex align-items-start {{ request()->routeIs('top.setting') ? 'active' : 'text-white-50' }}">
            <span class="me-2 flex-shrink-0">⚙️</span>
            <span class="lh-sm text-start">設定<br>トップ</span>
        </a>
    </li>
    <li class="nav-item">
        <a href="{{ route('setting.attendance.manage') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('setting.attendance.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">🕒</span> 勤怠管理
        </a>
    </li>
    <li class="nav-item">
        @if (!empty($canAccessAssignmentSettings))
            <a href="{{ route('setting.assignment.manage') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('setting.assignment.*') ? 'active' : 'text-white-50' }}">
                <span class="me-2">📋</span> 配置入力
            </a>
        @endif
    </li>
    <li class="nav-item mt-2">
        <a href="{{ route('paid-leave.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('paid-leave.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">🗓️</span> 有給申請
        </a>
    </li>
    {{-- 感謝ポイントは準備中のため一旦非表示（再表示時はこのコメントを外す）
    <li class="nav-item">
        <a href="{{ route('gratitude-points.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('gratitude-points.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">💐</span> 感謝ポイント
        </a>
    </li>
    --}}
    @php
        // 現場3D: 押すと「一覧」と工事の名前が開く。PC用とスマホ用で2回読み込まれるので id を分ける。
        $genba3dProjects = \App\Support\Genba3dCatalog::activeProjects();
        $genba3dOpen = request()->routeIs('genba3d.*');
        $genba3dCurrent = (string) request()->route('site');
        $genba3dListId = $dismiss ? 'genba3dNavMobile' : 'genba3dNavDesktop';
    @endphp
    @if (! empty($genba3dProjects))
        <li class="nav-item mt-3">
            <button
                type="button"
                class="nav-link d-flex align-items-center w-100 text-start {{ $genba3dOpen ? 'text-white' : 'text-white-50' }}"
                data-bs-toggle="collapse"
                data-bs-target="#{{ $genba3dListId }}"
                aria-expanded="{{ $genba3dOpen ? 'true' : 'false' }}"
                aria-controls="{{ $genba3dListId }}"
            >
                <span class="me-2">🏗️</span> 現場3D
                <span class="ms-auto small" aria-hidden="true">▾</span>
            </button>
            <ul id="{{ $genba3dListId }}" class="collapse {{ $genba3dOpen ? 'show' : '' }} list-unstyled ms-3 ps-2 mt-1 mb-0 border-start border-secondary border-opacity-50">
                <li class="nav-item">
                    <a href="{{ route('genba3d.index') }}" class="nav-link py-1 small {{ request()->routeIs('genba3d.index') ? 'active' : 'text-white-50' }}">
                        一覧
                    </a>
                </li>
                {{-- 工事ごとに1行。押すと、その工事の最初の現場が開く（現場は画面の上のボタンで切り替える） --}}
                @foreach ($genba3dProjects as $genba3dProject)
                    <li class="nav-item">
                        <a href="{{ route('genba3d.show', ['site' => array_key_first($genba3dProject['sites'])]) }}" class="nav-link py-1 small {{ isset($genba3dProject['sites'][$genba3dCurrent]) ? 'active' : 'text-white-50' }}">
                            {{ $genba3dProject['name'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </li>
    @endif
    <li class="nav-item mt-4 pt-3 border-top border-secondary border-opacity-25">
        <a href="{{ route('inquiry.create') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('inquiry.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">✉️</span> お問い合わせ
        </a>
    </li>
</ul>
