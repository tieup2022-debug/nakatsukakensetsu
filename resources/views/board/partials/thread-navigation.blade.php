<nav class="d-flex justify-content-between gap-2 my-3" aria-label="前後の記事">
    @if ($adjacentThreads['previous'])
        <a class="btn btn-outline-primary text-start" style="min-width: 0; max-width: 48%" href="{{ route('board.show', ['thread' => $adjacentThreads['previous']->id]) }}" rel="prev">
            <span class="d-block">← 前の記事</span>
            <span class="d-block small text-truncate">{{ $adjacentThreads['previous']->title }}</span>
        </a>
    @else
        <span class="btn btn-outline-secondary disabled align-self-start" aria-disabled="true">← 前の記事</span>
    @endif
    @if ($adjacentThreads['next'])
        <a class="btn btn-outline-primary text-end" style="min-width: 0; max-width: 48%" href="{{ route('board.show', ['thread' => $adjacentThreads['next']->id]) }}" rel="next">
            <span class="d-block">次の記事 →</span>
            <span class="d-block small text-truncate">{{ $adjacentThreads['next']->title }}</span>
        </a>
    @else
        <span class="btn btn-outline-secondary disabled align-self-start" aria-disabled="true">次の記事 →</span>
    @endif
</nav>
