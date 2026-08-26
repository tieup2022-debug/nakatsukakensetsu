<?php

namespace App\Http\Controllers;

use App\Services\BoardService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class BoardController extends Controller
{
    public function __construct(
        private BoardService $board,
        private UserService $users,
    ) {}

    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));

        return view('board.index', [
            'title' => '掲示板',
            'keyword' => $keyword,
            'threads' => $this->board->paginateThreads($keyword),
        ]);
    }

    public function create(Request $request)
    {
        $user = $this->currentUser($request);

        return view('board.create', [
            'title' => '掲示板・新規投稿',
            'currentUserName' => (string) $user->user_name,
            'attachmentLimit' => BoardService::ATTACHMENT_LIMIT,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->postRules());
        $user = $this->currentUser($request);

        $threadId = $this->board->createThread(
            (int) $user->id,
            (string) $user->user_name,
            $validated['title'],
            $validated['body'],
            $request->file('attachments', []),
        );

        return redirect()->route('board.show', ['thread' => $threadId])
            ->with('status', '掲示板に投稿しました。');
    }

    public function show(Request $request, int $thread)
    {
        $user = $this->currentUser($request);
        $post = $this->board->findThread($thread, (int) $user->id, true);
        abort_unless($post, 404);

        return view('board.show', [
            'title' => $post->title.'・掲示板',
            'thread' => $post,
            'canDeleteThread' => $this->canDelete($user, $post->author_user_id),
            'currentUser' => $user,
            'attachmentLimit' => BoardService::ATTACHMENT_LIMIT,
        ]);
    }

    public function reply(Request $request, int $thread)
    {
        abort_unless($this->board->findThread($thread), 404);
        $validated = $request->validate($this->replyRules());
        $user = $this->currentUser($request);

        $this->board->createReply(
            $thread,
            (int) $user->id,
            (string) $user->user_name,
            $validated['body'],
            $request->file('attachments', []),
        );

        return redirect()->to(route('board.show', ['thread' => $thread]).'#replies')
            ->with('status', '返信を投稿しました。');
    }

    public function like(Request $request, int $thread)
    {
        abort_unless($this->board->findThread($thread), 404);
        $user = $this->currentUser($request);
        $result = $this->board->toggleLike($thread, (int) $user->id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('board.show', ['thread' => $thread]);
    }

    public function attachment(Request $request, int $attachment)
    {
        $file = $this->board->findAttachment($attachment);
        abort_unless($file && Storage::disk($file->disk)->exists($file->path), 404);

        if ($request->boolean('inline') && str_starts_with((string) $file->mime_type, 'image/')) {
            return Storage::disk($file->disk)->response(
                $file->path,
                $file->original_name,
                ['Content-Type' => $file->mime_type, 'Content-Disposition' => 'inline']
            );
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroy(Request $request, int $thread)
    {
        $post = $this->board->findThread($thread);
        abort_unless($post, 404);
        abort_unless($this->canDelete($this->currentUser($request), $post->author_user_id), 403);

        $this->board->deleteThread($thread);

        return redirect()->route('board.index')->with('status', '投稿を削除しました。');
    }

    public function destroyReply(Request $request, int $thread, int $reply)
    {
        $post = $this->board->findReply($thread, $reply);
        abort_unless($post, 404);
        abort_unless($this->canDelete($this->currentUser($request), $post->author_user_id), 403);

        $this->board->deleteReply($thread, $reply);

        return redirect()->route('board.show', ['thread' => $thread])->with('status', '返信を削除しました。');
    }

    private function currentUser(Request $request): object
    {
        $user = $this->users->GetUser((int) $request->session()->get('login_user_id'));
        abort_unless($user, 401);

        return $user;
    }

    private function canDelete(object $user, mixed $authorUserId): bool
    {
        return (int) ($user->permission ?? 0) === 1
            || ($authorUserId !== null && (int) $authorUserId === (int) $user->id);
    }

    private function postRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:50000'],
            'attachments' => ['nullable', 'array', 'max:'.BoardService::ATTACHMENT_LIMIT],
            'attachments.*' => ['file', File::types([
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'xls', 'xlsx', 'doc', 'docx', 'csv', 'txt', 'zip',
            ])->max('20mb')],
        ];
    }

    private function replyRules(): array
    {
        $rules = $this->postRules();
        unset($rules['title']);

        return $rules;
    }
}
