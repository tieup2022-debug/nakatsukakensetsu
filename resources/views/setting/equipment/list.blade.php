@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">重機一覧</h1>
            <div class="text-muted small">並び順・編集・削除を行います。</div>
        </div>
        <div>
            <a class="btn btn-primary" href="{{ route('setting.equipment.create') }}">追加</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.equipment.sort') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 120px;">並び順</th>
                                <th>重機名</th>
                                <th style="width: 220px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($equipment_list as $e)
                            <tr>
                                <td>
                                    <input
                                        type="number"
                                        name="sort_number[{{ $e->id }}]"
                                        class="form-control form-control-sm"
                                        value="{{ $e->sort_number ?? 0 }}"
                                        min="0"
                                    >
                                </td>
                                <td class="fw-medium">{{ $e->vehicle_name }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm me-2" href="{{ route('setting.equipment.update', ['vehicle_id'=>$e->id]) }}">
                                        編集
                                    </a>
                                    <form method="POST" action="{{ route('setting.equipment.delete') }}" style="display:inline;">
                                        @csrf
                                        <input type="hidden" name="vehicle_id" value="{{ $e->id }}">
                                        <button class="btn btn-outline-danger btn-sm" onclick="return confirm('削除しますか？')" type="submit">
                                            削除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        @if(empty($equipment_list))
                            <tr><td colspan="3" class="text-muted">データなし</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary" type="submit">並び順を保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

