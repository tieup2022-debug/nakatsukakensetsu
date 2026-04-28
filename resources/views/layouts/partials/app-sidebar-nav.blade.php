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
        <a href="{{ route('setting.assignment.manage') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('setting.assignment.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">📋</span> 配置入力
        </a>
    </li>
    <li class="nav-item mt-2">
        <a href="{{ route('paid-leave.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('paid-leave.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">🗓️</span> 有給申請
        </a>
    </li>
    <li class="nav-item mt-4 pt-3 border-top border-secondary border-opacity-25">
        <a href="{{ route('inquiry.create') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('inquiry.*') ? 'active' : 'text-white-50' }}">
            <span class="me-2">✉️</span> お問い合わせ
        </a>
    </li>
</ul>
