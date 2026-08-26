@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/board.css') }}">
@endpush

@section('content')
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">掲示板・新規投稿</h1>
            <div class="text-muted small">投稿者：{{ $currentUserName }}</div>
        </div>
        <a href="{{ route('board.index') }}" class="btn btn-outline-secondary btn-sm">一覧へ戻る</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-lg-4">
            <form method="post" action="{{ route('board.store') }}" enctype="multipart/form-data" data-board-form>
                @csrf

                <div class="d-flex flex-wrap align-items-end gap-2 p-3 rounded bg-light border mb-3">
                    <div>
                        <label for="report-day-label" class="form-label small fw-semibold mb-1">日報テンプレート</label>
                        <select id="report-day-label" class="form-select form-select-sm" data-report-day>
                            <option value="明日の作業">明日の作業</option>
                            <option value="月曜日の作業">月曜日の作業</option>
                            <option value="土曜日の作業">土曜日の作業</option>
                            <option value="日曜日の作業">日曜日の作業</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-insert-daily-report>日報を挿入</button>
                </div>

                <div class="mb-3">
                    <label for="board-title" class="form-label fw-semibold">タイトル <span class="text-danger">*</span></label>
                    <input
                        id="board-title"
                        name="title"
                        value="{{ old('title') }}"
                        class="form-control @error('title') is-invalid @enderror"
                        maxlength="255"
                        required
                        autofocus
                    >
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="board-body" class="form-label fw-semibold">メッセージ <span class="text-danger">*</span></label>
                    <textarea
                        id="board-body"
                        name="body"
                        rows="14"
                        class="form-control board-body-input @error('body') is-invalid @enderror"
                        maxlength="50000"
                        required
                    >{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">URLは詳細画面で自動的にリンクになります。50,000文字以内。</div>
                </div>

                <div class="mb-4">
                    <label for="board-attachments" class="form-label fw-semibold">添付ファイル</label>
                    <input
                        id="board-attachments"
                        type="file"
                        name="attachments[]"
                        class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                        multiple
                        data-file-input
                        data-file-limit="{{ $attachmentLimit }}"
                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.xls,.xlsx,.doc,.docx,.csv,.txt,.zip"
                    >
                    @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('attachments.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">最大{{ $attachmentLimit }}ファイル、1ファイル20MBまで。</div>
                    <div class="small text-muted mt-2" data-file-summary></div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">投稿する</button>
                    <button class="btn btn-outline-secondary" type="reset">入力をクリア</button>
                    <a href="{{ route('board.index') }}" class="btn btn-link text-secondary">中止</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/board.js') }}"></script>
@endpush
