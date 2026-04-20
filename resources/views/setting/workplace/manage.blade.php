@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">現場管理</h1>
            <div class="text-muted small">現場の登録・完了切り替えを行います。</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">現場を追加</h2>
                    <p class="text-muted small mb-3">稼働中の現場を登録します。</p>
                    <a class="btn btn-primary" href="{{ route('setting.workplace.create') }}">追加</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">稼働中の現場一覧</h2>
                    <p class="text-muted small mb-3">現在有効な現場を確認できます。</p>
                    <a class="btn btn-primary" href="{{ route('setting.workplace.list') }}">一覧</a>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">完了済みの現場一覧</h2>
                    <p class="text-muted small mb-3">過去の現場を閲覧できます。</p>
                    <a class="btn btn-outline-secondary" href="{{ route('setting.workplace.completed.list') }}">完了済み</a>
                </div>
            </div>
        </div>
    </div>
@endsection

