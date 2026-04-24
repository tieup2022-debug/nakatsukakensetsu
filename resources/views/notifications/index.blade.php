@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1 fw-semibold">通知</h1>
            <div class="text-muted small">システム内のお知らせです。</div>
        </div>
        @if($notifications->count() > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">すべて既読</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success mb-3">{{ session('status') }}</div>
    @endif

    <div class="list-group shadow-sm border-0">
        @forelse($notifications as $n)
            <div class="list-group-item list-group-item-action d-flex flex-column flex-md-row justify-content-between gap-2 {{ $n->read_at ? '' : 'bg-light' }}">
                <div>
                    <div class="fw-semibold">{{ $n->title }}</div>
                    <div class="small text-muted mt-1" style="white-space: pre-wrap;">{{ $n->body }}</div>
                    <div class="small text-muted mt-1">{{ \Carbon\Carbon::parse($n->created_at)->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</div>
                </div>
                <div class="flex-shrink-0">
                    @if(!$n->read_at)
                        <form method="POST" action="{{ route('notifications.read', ['id' => $n->id]) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">既読</button>
                        </form>
                    @else
                        <span class="badge text-bg-secondary">既読</span>
                    @endif
                </div>
            </div>
        @empty
            <div class="list-group-item text-muted text-center py-4">通知はありません。</div>
        @endforelse
    </div>
@endsection
