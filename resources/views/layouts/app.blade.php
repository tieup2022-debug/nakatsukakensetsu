<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Nakatsuka DX' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f3f4f6;
        }
        .app-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .app-main {
            flex: 1;
            display: flex;
            min-height: 0;
        }
        .sidebar {
            width: 240px;
            background: linear-gradient(180deg, #1d4ed8, #0f172a);
            color: #e5e7eb;
        }
        .sidebar a {
            color: inherit;
            text-decoration: none;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background-color: rgba(59,130,246,0.25);
        }
        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
            }
        }
        /* Flatpickr: タップしやすい大きめのカレンダー */
        .flatpickr-calendar {
            font-size: 1.125rem;
            width: auto;
            min-width: 22rem;
            padding: 0.35rem 0.25rem 0.5rem;
            box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.18);
        }
        .flatpickr-months {
            padding: 0.4rem 0 0.25rem;
        }
        .flatpickr-current-month {
            font-size: 1.1rem;
            padding: 0.35rem 0;
        }
        .flatpickr-weekdays {
            padding-top: 0.25rem;
        }
        span.flatpickr-weekday {
            font-size: 1rem;
            font-weight: 600;
        }
        .flatpickr-day {
            height: 3rem;
            line-height: 3rem;
            max-width: 3.1rem;
            margin: 0.08rem;
            border-radius: 0.35rem;
            font-size: 1.05rem;
        }
        .flatpickr-day.today {
            border-width: 2px;
        }
        input.js-datepicker[readonly] {
            background-color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
            <div class="container-fluid">
                <a href="{{ session()->has('login_user_id') ? route('top.assignment') : route('login') }}" class="navbar-brand fw-semibold text-primary text-decoration-none">Nakatsuka DX</a>
                <div class="d-flex align-items-center gap-3">
                    @if (session()->has('login_user_id'))
                        <span class="text-muted small">ログイン中</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm" type="submit">ログアウト</button>
                        </form>
                    @endif
                </div>
            </div>
        </header>

        <div class="app-main">
            @if (session()->has('login_user_id'))
                <nav class="sidebar d-none d-lg-flex flex-column p-3">
                    <div class="mb-4 small text-uppercase text-gray-400">メニュー</div>
                    <ul class="nav nav-pills flex-column gap-1">
                        <li class="nav-item">
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
                            <a href="{{ route('top.setting') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('top.setting') ? 'active' : 'text-white-50' }}">
                                <span class="me-2">⚙️</span> 設定トップ
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
                    </ul>
                </nav>
            @endif

            <main class="flex-grow-1 p-3 p-lg-4">
                <div class="container-fluid">
                    @if (session('status'))
                        <div class="alert alert-success mb-3">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>

        <footer class="bg-white border-top py-2 text-center small text-muted">
            &copy; {{ date('Y') }} Nakatsuka Construction
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ja.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof flatpickr === 'undefined') return;
            var ja = flatpickr.l10ns.ja;
            document.querySelectorAll('input.js-datepicker').forEach(function (el) {
                var opts = {
                    locale: ja,
                    dateFormat: 'Y-m-d',
                    allowInput: false,
                    disableMobile: true,
                };
                if (el.hasAttribute('data-datepicker-submit')) {
                    opts.onChange = function () {
                        var form = el.closest('form');
                        if (form) form.submit();
                    };
                }
                flatpickr(el, opts);
            });
        });
    </script>
</body>
</html>

