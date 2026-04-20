@extends('layouts.app')

@section('content')
    <div class="row g-4">
        <div class="col-12">
            <h1 class="h4 mb-3 fw-semibold">ダッシュボード</h1>
            <p class="text-muted">
                よく使う画面へすぐ移動できます。
            </p>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2 fw-semibold">勤怠</h2>
                    <p class="mb-3 small text-muted">日々の出退勤を確認・更新します。</p>
                    <a class="btn btn-primary w-100" href="{{ route('top.attendance') }}">開く</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2 fw-semibold">配置一覧</h2>
                    <p class="mb-3 small text-muted">社員・車両・重機の配置を更新します。</p>
                    <a class="btn btn-primary w-100" href="{{ route('top.assignment') }}">開く</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2 fw-semibold">操作メモ</h2>
                    <p class="mb-0 small text-muted">
                        まずは「勤怠」→「配置一覧」を日々更新する流れで進めてください。
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

