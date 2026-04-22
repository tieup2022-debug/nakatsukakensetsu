@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">設定</h1>
            <div class="text-muted small">必要なマスタを管理します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">スタッフ管理</h2>
                    <p class="text-muted small mb-3">社員の登録・並び替え・編集・削除</p>
                    <a href="{{ route('setting.staff.manage') }}" class="btn btn-primary w-100">開く</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">勤怠管理</h2>
                    <p class="text-muted small mb-3">毎日の出退勤・欠勤者管理を行います。</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('setting.attendance.manage') }}" class="btn btn-primary">開く</a>
                        @if (!empty($can_edit_attendance_defaults))
                            <a href="{{ route('setting.attendance.defaults') }}" class="btn btn-outline-secondary btn-sm">勤怠の初期時間</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">配置入力</h2>
                    <p class="text-muted small mb-3">日々の配置（スタッフ/車両/重機）を管理します。</p>
                    <a href="{{ route('setting.assignment.manage') }}" class="btn btn-primary w-100">開く</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">現場管理</h2>
                    <p class="text-muted small mb-3">稼働中/完了の現場を登録・更新</p>
                    <a href="{{ route('setting.workplace.manage') }}" class="btn btn-primary w-100">開く</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">車両/重機</h2>
                    <p class="text-muted small mb-3">車両・重機マスタの登録・更新を行います。</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('setting.vehicle.manage') }}" class="btn btn-primary">車両管理</a>
                        <a href="{{ route('setting.equipment.manage') }}" class="btn btn-primary">重機管理</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">稼働率</h2>
                    <p class="text-muted small mb-3">年次の稼働率を確認します。</p>
                    <a href="{{ route('setting.utilizationrate.index') }}" class="btn btn-primary w-100">開く</a>
                </div>
            </div>
        </div>

        @if (!empty($can_manage_users_and_accounts))
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h2 class="h6 fw-semibold mb-2">ユーザー管理</h2>
                        <p class="text-muted small mb-3">ログインユーザーを管理します。</p>
                        <a href="{{ route('setting.user.manage') }}" class="btn btn-primary w-100">開く</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">お知らせ</h2>
                    <p class="text-muted small mb-3">お知らせの編集と履歴を管理します。</p>
                    <a href="{{ route('setting.news.update') }}" class="btn btn-primary w-100">開く</a>
                </div>
            </div>
        </div>

        @if (!empty($can_manage_users_and_accounts))
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h2 class="h6 fw-semibold mb-2">アカウント</h2>
                        <p class="text-muted small mb-3">ユーザー連携とパスワード変更。</p>
                        <div class="d-grid gap-2">
                            <a href="{{ route('setting.account.linkuser.update') }}" class="btn btn-outline-primary">ユーザー連携</a>
                            <a href="{{ route('setting.account.password.update') }}" class="btn btn-outline-primary">パスワード変更</a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

