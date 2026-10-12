<?php

namespace App\Http\Controllers;

use App\Services\Genba3dFeedbackService;
use App\Services\InAppNotificationService;
use App\Services\UserService;
use App\Support\Genba3dCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * 現場3D「現場の声」。
 *
 * 3Dモデル・工程表を見た人が「ここはこうしてほしい」「ここはこうだよ」を気軽に書き込み、
 * 管理者（権限1）が確認して返信・対応状況を付ける。3Dモデルではピンで場所を指せる。
 */
class Genba3dFeedbackController extends Controller
{
    public const MAX_BODY = 1000;

    public function __construct(
        private Genba3dFeedbackService $feedback,
        private UserService $users,
        private InAppNotificationService $notifications,
    ) {}

    /** 全現場の書き込みを並べた一覧（だれでも見られる。対応状況を変えられるのは管理者だけ）。 */
    public function index(Request $request): View
    {
        $user = $this->user($request);
        $sites = $this->sites();

        return view('genba3d.koe', [
            'title' => '現場3D｜現場の声',
            'projects' => array_filter(Genba3dCatalog::projects(), fn (array $p): bool => $p['sites'] !== []),
            'statuses' => Genba3dFeedbackService::statuses(),
            'kinds' => Genba3dFeedbackService::kinds(),
            'isMaster' => $this->isMaster($user),
            'ready' => $this->feedback->ready(),
            'listUrl' => route('genba3d.koe.list'),
            'siteFilter' => array_key_exists((string) $request->query('site'), $sites) ? (string) $request->query('site') : '',
        ]);
    }

    /** 書き込みの一覧（JSON）。site を付けるとその現場だけ。 */
    public function list(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $sites = $this->sites();
        $site = (string) $request->query('site', '');
        $status = (string) $request->query('status', '');
        $isMaster = $this->isMaster($user);

        if (! $this->feedback->ready()) {
            return response()->json(['items' => [], 'isMaster' => $isMaster]);
        }

        return response()->json([
            'items' => $this->feedback->list($sites, [
                'site' => array_key_exists($site, $sites) ? $site : '',
                'status' => array_key_exists($status, Genba3dFeedbackService::statuses()) ? $status : '',
            ], (int) $user->id, $isMaster),
            'isMaster' => $isMaster,
        ]);
    }

    public function store(Request $request, string $site): JsonResponse
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]), 404);
        $user = $this->user($request);
        abort_unless($this->feedback->ready(), 503, '現場の声はまだ準備中です。');

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(Genba3dFeedbackService::kinds()))],
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
            'tab' => ['required', 'string', 'regex:/^[a-z0-9-]{1,40}$/'],
            'pin' => ['nullable', 'array'],
            'pin.p' => ['required_with:pin', 'array', 'size:3'],
            'pin.p.*' => ['numeric', 'between:-100000,100000'],
            'pin.cam' => ['nullable', 'array', 'size:3'],
            'pin.cam.*' => ['numeric', 'between:-100000,100000'],
            'pin.tgt' => ['nullable', 'array', 'size:3'],
            'pin.tgt.*' => ['numeric', 'between:-100000,100000'],
            'pin.part' => ['nullable', 'string', 'max:100'],
        ], [
            'body.required' => '内容を入力してください。',
            'body.max' => '内容は'.self::MAX_BODY.'文字以内で入力してください。',
        ]);

        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['message' => '内容を入力してください。'], 422);
        }
        $tab = $data['tab'];
        abort_unless(in_array($tab, ['model', 'schedule'], true) || isset($sites[$site]['pages'][$tab]), 422);

        $pin = null;
        if (! empty($data['pin']['p'])) {
            $num = fn (array $v): array => array_map(fn ($x): float => round((float) $x, 3), array_values($v));
            $pin = ['p' => $num($data['pin']['p'])];
            if (! empty($data['pin']['cam']) && ! empty($data['pin']['tgt'])) {
                $pin['cam'] = $num($data['pin']['cam']);
                $pin['tgt'] = $num($data['pin']['tgt']);
            }
            if (! empty($data['pin']['part'])) {
                $pin['part'] = mb_substr(trim($data['pin']['part']), 0, 100);
            }
        }

        $id = $this->feedback->create($site, $tab, $data['kind'], $body, $pin, $user);

        // 管理者へ知らせる（自分の書き込みは除く）
        $kind = Genba3dFeedbackService::kinds()[$data['kind']];
        foreach ($this->feedback->masterIds() as $masterId) {
            if ($masterId !== (int) $user->id) {
                $this->notifications->create(
                    $masterId,
                    '現場3D：現場の声（'.$sites[$site]['name'].'）',
                    $user->user_name.'さん【'.$kind.'】'.mb_strimwidth($body, 0, 120, '…'),
                    'genba3d_feedback',
                    $id,
                );
            }
        }

        return response()->json(['id' => $id, 'message' => '書き込みました。'], 201);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        $row = $this->found($id);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:'.self::MAX_BODY],
        ], ['body.required' => '返信を入力してください。']);
        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['message' => '返信を入力してください。'], 422);
        }

        $isMaster = $this->isMaster($user);
        $this->feedback->reply($row, $body, $user, $isMaster);

        // 書き込んだ人と、管理者へ知らせる（返信した本人は除く）
        $siteName = (string) ($this->sites()[$row->site]['name'] ?? $row->site);
        $to = $isMaster ? [] : $this->feedback->masterIds();
        $to[] = (int) $row->author_user_id;
        foreach (array_unique($to) as $uid) {
            if ($uid > 0 && $uid !== (int) $user->id) {
                $this->notifications->create(
                    $uid,
                    '現場3D：現場の声に返信（'.$siteName.'）',
                    $user->user_name.'さん：'.mb_strimwidth($body, 0, 120, '…'),
                    'genba3d_feedback',
                    (int) $row->id,
                );
            }
        }

        return response()->json(['message' => '返信しました。'], 201);
    }

    /** 対応状況の変更（管理者だけ）。 */
    public function status(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        abort_unless($this->isMaster($user), 403, '対応状況を変えられるのは管理者だけです。');
        $row = $this->found($id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Genba3dFeedbackService::statuses()))]]);

        if ($data['status'] !== $row->status) {
            $this->feedback->setStatus($row, $data['status']);
            if ((int) $row->author_user_id > 0 && (int) $row->author_user_id !== (int) $user->id) {
                $this->notifications->create(
                    (int) $row->author_user_id,
                    '現場3D：現場の声が「'.Genba3dFeedbackService::statuses()[$data['status']].'」になりました',
                    mb_strimwidth((string) $row->body, 0, 120, '…'),
                    'genba3d_feedback',
                    (int) $row->id,
                );
            }
        }

        return response()->json(['message' => '対応状況を変えました。']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        $row = $this->found($id);
        $own = (int) $row->author_user_id === (int) $user->id;
        abort_unless($this->isMaster($user) || ($own && $this->feedback->replyCount($id) === 0), 403, 'この書き込みは消せません。');

        $this->feedback->delete($row);

        return response()->json(['message' => '消しました。']);
    }

    private function found(int $id): object
    {
        abort_unless($this->feedback->ready(), 404);
        $row = $this->feedback->find($id);
        abort_unless($row !== null, 404);

        return $row;
    }

    private function user(Request $request): object
    {
        $user = $this->users->GetUser((int) $request->session()->get('login_user_id'));
        abort_unless($user, 401);

        return $user;
    }

    private function isMaster(object $user): bool
    {
        return (int) ($user->permission ?? 0) === 1;
    }

    /** @return array<string, array<string, mixed>> */
    private function sites(): array
    {
        return (array) config('genba3d.sites', []);
    }
}
