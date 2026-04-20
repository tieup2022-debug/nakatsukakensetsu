@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">車両管理</h1>
            <div class="text-muted small">車両の登録・並び順・編集・削除を行います。</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">車両を追加</h2>
                    <p class="text-muted small mb-3">新しい車両を登録します。</p>
                    <a class="btn btn-primary w-100" href="{{ route('setting.vehicle.create') }}">追加</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">車両一覧</h2>
                    <p class="text-muted small mb-3">並び替え・編集・削除。</p>
                    <a class="btn btn-primary w-100" href="{{ route('setting.vehicle.list') }}">一覧</a>
                </div>
            </div>
        </div>
    </div>
@endsection

