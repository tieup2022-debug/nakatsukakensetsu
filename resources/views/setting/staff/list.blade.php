@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">スタッフ一覧</h1>
            <div class="text-muted small">並び順・編集・削除を行います。</div>
        </div>
        <div>
            <a class="btn btn-primary" href="{{ route('setting.staff.create') }}">追加</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-info mb-3">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('setting.staff.sort') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>並び順</th>
                                <th>社員名</th>
                                <th>種別</th>
                                <th style="width: 240px;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($staff_list as $s)
                            @php
                                $typeLabel = match ((string)$s->staff_type) {
                                    '1' => '担当',
                                    '2' => 'OP',
                                    '3' => '作業員',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td style="width: 120px;">
                                    <input
                                        type="number"
                                        name="sort_number[{{ $s->id }}]"
                                        class="form-control form-control-sm"
                                        value="{{ $s->sort_number ?? 0 }}"
                                        min="0"
                                    >
                                </td>
                                <td class="fw-medium">{{ $s->staff_name }}</td>
                                <td>{{ $typeLabel }}</td>
                                <td class="text-end">
                                    <a class="btn btn-outline-primary btn-sm me-2" href="{{ route('setting.staff.update', ['staff_id'=>$s->id]) }}">
                                        編集
                                    </a>
                                    <button type="submit" formaction="{{ route('setting.staff.delete') }}" formmethod="POST" class="btn btn-outline-danger btn-sm">
                                        削除
                                    </button>
                                    <input type="hidden" name="staff_id" value="{{ $s->id }}">
                                </td>
                            </tr>
                        @endforeach
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

