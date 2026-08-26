@extends('layouts.app')

@php
    $linkBody = static function (string $body): string {
        $escaped = e($body);
        return preg_replace_callback(
            '~https?://[^\s<]+~u',
            static fn (array $match): string => '<a href="'.$match[0].'" target="_blank" rel="noopener noreferrer">'.$match[0].'</a>',
            $escaped
        ) ?? $escaped;
    };
    $isImage = static fn (object $attachment): bool => in_array((string) $attachment->mime_type, [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    ], true);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/board.css') }}">
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <a href="{{ route('board.index') }}" class="btn btn-outline-secondary btn-sm">← 一覧へ戻る</a>
        @if ($canDeleteThread)
            <form method="post" action="{{ route('board.destroy', ['thread' => $thread->id]) }}" onsubmit="return confirm('この投稿とすべての返信・添付ファイルを削除します。よろしいですか？')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit">投稿を削除</button>
            </form>
        @endif
    </div>

    <article class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-lg-4">
            <h1 class="h4 fw-semibold mb-3 board-thread-title">{{ $thread->title }}</h1>
            <div class="d-flex flex-wrap gap-x-3 gap-1 small text-muted border-bottom pb-3 mb-3">
                <span>投稿者：<strong class="text-body">{{ $thread->author_name }}</strong></span>
                <span>{{ \App\Support\DatetimeDisplay::formatStoredAt($thread->created_at) }}</span>
                <span>閲覧 {{ number_format($thread->view_count) }}回</span>
            </div>

            @if ($thread->attachments->isNotEmpty())
                <div class="board-attachments mb-3">
                    @foreach ($thread->attachments as $attachment)
                        @if ($isImage($attachment))
                            <a href="{{ route('board.attachments.show', ['attachment' => $attachment->id]) }}" class="board-image-link" title="{{ $attachment->original_name }}">
                                <img
                                    src="{{ route('board.attachments.show', ['attachment' => $attachment->id, 'inline' => 1]) }}"
                                    alt="{{ $attachment->original_name }}"
                                    class="board-image"
                                    loading="lazy"
                                >
                            </a>
                        @else
                            <a href="{{ route('board.attachments.show', ['attachment' => $attachment->id]) }}" class="board-file-link">
                                📎 {{ $attachment->original_name }}
                                <span class="text-muted">({{ number_format($attachment->size / 1024, 0) }}KB)</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="board-message">{!! $linkBody($thread->body) !!}</div>

            <div class="d-flex flex-wrap align-items-center gap-2 border-top mt-4 pt-3">
                <form method="post" action="{{ route('board.like', ['thread' => $thread->id]) }}">
                    @csrf
                    <button class="btn {{ $thread->liked_by_viewer ? 'btn-primary' : 'btn-outline-primary' }}" type="submit">
                        👍 {{ $thread->liked_by_viewer ? 'いいね済み' : 'いいね' }}
                    </button>
                </form>
                <span class="text-muted small">{{ number_format($thread->like_count) }}件のいいね</span>
            </div>
        </div>
    </article>

    <section id="replies" class="mb-4">
        <h2 class="h5 fw-semibold mb-3">返信（{{ number_format($thread->replies->count()) }}件）</h2>
        <div class="d-grid gap-3">
            @foreach ($thread->replies as $reply)
                <article class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between gap-2 border-bottom pb-2 mb-3">
                            <div class="small text-muted">
                                <strong class="text-body">{{ $reply->author_name }}</strong>
                                <span class="ms-2">{{ \App\Support\DatetimeDisplay::formatStoredAt($reply->created_at) }}</span>
                                @if ((int) ($reply->legacy_like_count ?? 0) > 0)
                                    <span class="ms-2">👍 {{ number_format($reply->legacy_like_count) }}</span>
                                @endif
                            </div>
                            @if ((int) ($currentUser->permission ?? 0) === 1 || (int) $reply->author_user_id === (int) $currentUser->id)
                                <form method="post" action="{{ route('board.replies.destroy', ['thread' => $thread->id, 'reply' => $reply->id]) }}" onsubmit="return confirm('この返信を削除します。よろしいですか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">削除</button>
                                </form>
                            @endif
                        </div>

                        @if ($reply->attachments->isNotEmpty())
                            <div class="board-attachments mb-3">
                                @foreach ($reply->attachments as $attachment)
                                    @if ($isImage($attachment))
                                        <a href="{{ route('board.attachments.show', ['attachment' => $attachment->id]) }}" class="board-image-link" title="{{ $attachment->original_name }}">
                                            <img src="{{ route('board.attachments.show', ['attachment' => $attachment->id, 'inline' => 1]) }}" alt="{{ $attachment->original_name }}" class="board-image" loading="lazy">
                                        </a>
                                    @else
                                        <a href="{{ route('board.attachments.show', ['attachment' => $attachment->id]) }}" class="board-file-link">📎 {{ $attachment->original_name }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <div class="board-message">{!! $linkBody($reply->body) !!}</div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-body p-3 p-lg-4">
            <h2 class="h5 fw-semibold mb-3">この投稿に返信する</h2>
            <form method="post" action="{{ route('board.replies.store', ['thread' => $thread->id]) }}" enctype="multipart/form-data" data-board-form>
                @csrf
                <div class="mb-3">
                    <label for="reply-body" class="form-label fw-semibold">メッセージ</label>
                    <textarea id="reply-body" name="body" rows="8" maxlength="50000" required class="form-control board-body-input @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="reply-attachments" class="form-label fw-semibold">添付ファイル</label>
                    <input
                        id="reply-attachments"
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
                <button class="btn btn-primary" type="submit">返信を投稿</button>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/board.js') }}"></script>
@endpush
