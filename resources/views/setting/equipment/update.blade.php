@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">重機編集</h1>
            <div class="text-muted small">重機名を更新します。</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.equipment.update.submit') }}">
                @csrf
                <input type="hidden" name="vehicle_id" value="{{ $equipment_data->id }}">

                <div class="mb-3">
                    <label class="form-label small text-muted">重機名</label>
                    <input type="text" name="vehicle_name" class="form-control" value="{{ $equipment_data->vehicle_name }}" required>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('setting.equipment.list') }}" class="btn btn-outline-secondary">戻る</a>
                    <button type="submit" class="btn btn-primary">更新</button>
                </div>
            </form>
        </div>
    </div>
@endsection

