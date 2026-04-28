@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">お問い合わせ一覧</h1>
            <div class="text-muted small">送信者・送信日時と内容の履歴です（直近200件）。</div>
        </div>
        <a href="{{ route('top.setting') }}" class="btn btn-outline-secondary btn-sm">設定トップへ</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 12rem;">送信日時</th>
                            <th style="width: 10rem;">送信者</th>
                            <th>内容</th>
                            <th style="width: 14rem;" class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td class="small text-nowrap">
                                {{ \App\Services\SystemInquiryService::formatStoredAt($r->created_at) }}
                            </td>
                            <td class="small">
                                <div class="fw-semibold">{{ $r->submitted_by_user_name }}</div>
                                <div class="text-muted">ユーザーID {{ $r->submitted_by_user_id }}</div>
                            </td>
                            <td class="small text-break" style="white-space: pre-wrap;">{{ $r->body }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap align-items-center justify-content-end gap-1">
                                    <form method="post" action="{{ route('setting.inquiry.status', $r->id) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <select
                                            name="status"
                                            class="form-select form-select-sm"
                                            style="width: auto; min-width: 6.5rem;"
                                            aria-label="対応状況"
                                            onchange="this.form.submit()"
                                        >
                                            @foreach ($inquiry_status_labels as $value => $label)
                                                <option value="{{ $value }}" @selected(\App\Services\SystemInquiryService::normalizeStatus(data_get($r, 'status')) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <form method="post" action="{{ route('setting.inquiry.destroy', $r->id) }}" class="d-inline" onsubmit="return confirm('このお問い合わせを削除しますか？');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">削除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">お問い合わせはまだありません。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
