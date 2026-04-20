@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">スタッフ管理</h1>
            <div class="text-muted small">スタッフの追加・編集・削除・並び順を管理します。</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">スタッフ登録</h2>
                    <p class="text-muted small mb-3">新しい社員を追加します。</p>
                    <a class="btn btn-primary" href="{{ route('setting.staff.create') }}">追加</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-2">スタッフ一覧</h2>
                    <p class="text-muted small mb-3">並び替え、編集、削除を行います。</p>
                    <a class="btn btn-primary" href="{{ route('setting.staff.list') }}">一覧</a>
                </div>
            </div>
        </div>
    </div>
@endsection

