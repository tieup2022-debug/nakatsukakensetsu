@php
    $hasFilters = $keyword !== '' || $author !== '' || $fromDate !== '' || $toDate !== '';
@endphp

<p class="small text-muted mb-2">
    @if ($hasFilters)
        条件に一致する画像：{{ number_format($images->total()) }}件
    @else
        画像：全{{ number_format($images->total()) }}件
    @endif
</p>

@if ($images->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">該当する画像はありません。</div>
    </div>
@else
    <div class="board-image-grid">
        @foreach ($images as $image)
            @php
                $postedAt = \App\Support\DatetimeDisplay::formatStoredAt($image->posted_at);
                $threadUrl = route('board.show', ['thread' => $image->thread_id]).($image->reply_id ? '#replies' : '');
                $description = trim((string) ($image->ai_description ?? ''));
                $caption = $image->thread_title.'｜'.$image->post_author_name.'｜'.$postedAt;
            @endphp
            <article class="card border-0 shadow-sm board-gallery-card">
                <a
                    href="{{ route('board.attachments.show', ['attachment' => $image->id, 'inline' => 1]) }}"
                    class="board-gallery-preview"
                    title="{{ $image->original_name }}"
                    aria-label="画像を拡大：{{ $caption }}"
                    data-board-image-preview
                    data-image-name="{{ $image->original_name }}"
                    data-image-caption="{{ $caption }}"
                >
                    <img
                        src="{{ route('board.attachments.show', ['attachment' => $image->id, 'inline' => 1]) }}"
                        alt="{{ $description !== '' ? $description : $image->original_name }}"
                        class="board-gallery-image"
                        loading="lazy"
                    >
                </a>
                <div class="card-body p-3">
                    <a href="{{ $threadUrl }}" class="fw-semibold text-decoration-none board-gallery-title">
                        {{ $image->thread_title }}
                    </a>
                    <div class="d-flex flex-wrap gap-2 small text-muted mt-2">
                        <span>{{ $image->post_author_name }}</span>
                        <span>{{ $postedAt }}</span>
                        @if ($image->reply_id)
                            <span class="badge text-bg-light border">返信画像</span>
                        @endif
                    </div>
                    @if ($description !== '')
                        <p class="small text-muted board-gallery-description mt-2 mb-0">{{ \Illuminate\Support\Str::limit($description, 90) }}</p>
                    @endif
                    <a href="{{ $threadUrl }}" class="btn btn-sm btn-outline-primary mt-3">元の投稿を見る</a>
                </div>
            </article>
        @endforeach
    </div>
@endif

@if ($images->hasPages())
    <div class="mt-4 d-flex justify-content-center">
        {{ $images->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
