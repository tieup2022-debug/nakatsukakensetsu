<?php

namespace App\Http\Controllers;

use App\Services\Genba3dChatService;
use App\Services\Genba3dTermService;
use App\Services\UserService;
use App\Support\Genba3dCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * 現場3D: 設計図から組み立てた3Dモデルと、施工手順・工程表を、ログイン済みのユーザーに見せる。
 * 画面の内容についての質問には、AI（Claude API）が答える。
 *
 * ページ本体は resources/genba3d/ に置いた1枚ものの HTML で、公開フォルダには置かない
 * （ログインなしで URL を直接開かれないようにするため）。一覧は config/genba3d.php。
 */
class Genba3dController extends Controller
{
    /** 現場3D の入口: 工事ごとに現場を並べ、3Dモデル・工程表・資料へ直接行ける一覧。 */
    public function index(): View
    {
        $projects = Genba3dCatalog::projects();

        return view('genba3d.index', [
            'title' => '現場3D',
            'projects' => array_filter($projects, fn (array $project): bool => $project['sites'] !== []),
            'terms' => app(Genba3dTermService::class)->terms(array_map('strval', array_keys($projects))),
            'upcoming' => array_filter($projects, fn (array $project): bool => $project['sites'] === []),
            'koeOpen' => $this->koeOpenCount(),
        ]);
    }

    /**
     * 工事ごとの工程表の種類のページ（契約・実施・ネットワーク工程表など、まだ中身の無いもの）。
     * 計画と変更は中身があるので、その画面へ送る。
     */
    public function phase(string $project, string $phase): View|\Illuminate\Http\RedirectResponse
    {
        $projects = Genba3dCatalog::activeProjects();
        abort_unless(isset($projects[$project]) && isset(Genba3dCatalog::PHASES[$phase]), 404);
        $url = Genba3dCatalog::phaseUrl($project, $projects[$project], $phase);
        if ($url !== route('genba3d.phase', ['project' => $project, 'phase' => $phase])) {
            return redirect($url);
        }

        return view('genba3d.phase', [
            'title' => '現場3D｜'.$projects[$project]['name'].'｜'.Genba3dCatalog::PHASES[$phase],
            'projectKey' => $project,
            'project' => ['key' => $project] + $projects[$project],
            'phase' => $phase,
            'term' => app(Genba3dTermService::class)->term($project),
        ]);
    }

    /** 現場の声の未対応・対応中の件数（表が無い・読めないときは 0）。 */
    private function koeOpenCount(): int
    {
        try {
            return array_sum(app(\App\Services\Genba3dFeedbackService::class)->openCounts());
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 3Dモデルのタブ。 */
    public function show(string $site): View
    {
        return $this->page($site, 'model');
    }

    /** 施工手順のタブ（施工手順・数量・確認事項）。 */
    public function steps(string $site): View
    {
        return $this->page($site, 'steps');
    }

    /** 工程表のタブ（条件・バーチャート・注意）。 */
    public function schedule(string $site): View
    {
        return $this->page($site, 'schedule');
    }

    /** 3Dモデル本体の HTML（画面内の iframe と「全画面で開く」の両方から使う）。 */
    public function model(string $site): Response
    {
        return $this->document($site, 'file');
    }

    /**
     * 施工手順と工程表の本体の HTML（1つのファイル）。
     * ?part=steps なら施工手順の側だけ、?part=gantt なら工程表の側だけを見せる。指定が無ければ全部。
     */
    public function schedulePage(Request $request, string $site): Response
    {
        $part = $request->query('part');
        $part = in_array($part, self::PARTS, true) ? $part : null;
        $response = $this->document($site, 'schedule_file', $part);
        // 工程表の側には、工事の工期の入力欄を出し、工期末と着手日の初期値に使う
        if ($part !== 'steps') {
            $response->setContent($this->withTerm((string) $response->getContent(), $site, $request));
        }

        return $response;
    }

    /** 工事の工期（開始日・工期末）を保存する。工期は工事ごとで、ログインした人なら誰でも入力できる（変更は記録に残す）。 */
    public function saveTerm(Request $request, string $site, Genba3dTermService $terms, UserService $users): JsonResponse
    {
        abort_unless(isset($this->sites()[$site]), 404);
        $project = Genba3dCatalog::projectOf($site);
        abort_unless($project !== null, 404);
        $user = $users->GetUser((int) $request->session()->get('login_user_id'));
        abort_unless($user, 401);
        if (! $terms->ready()) {
            return response()->json(['message' => '工期の保存はまだ準備中です（データベースの更新待ち）。'], 503);
        }

        $data = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'start.date_format' => '開始日の形が正しくありません。',
            'end.date_format' => '工期末の形が正しくありません。',
        ]);
        $start = $data['start'] ?? null;
        $end = $data['end'] ?? null;
        if ($start !== null && $end !== null && $end < $start) {
            return response()->json(['message' => '工期末は開始日より後の日にしてください。'], 422);
        }

        $changed = $terms->save($project['key'], $start, $end, $user);

        return response()->json([
            'message' => $changed ? '工期を保存しました。' : '工期は変わっていません。',
            'changed' => $changed,
            'term' => $terms->term($project['key']),
            'logs' => $terms->logs($project['key']),
        ]);
    }

    /**
     * 工程表のページに、工事の工期を差し込む。
     * 入力欄のスクリプトと値（window.GENBA3D_TERM）を足し、ページに書いてある工期末・着手日の初期値を置き換える。
     */
    private function withTerm(string $html, string $site, Request $request): string
    {
        $project = Genba3dCatalog::projectOf($site);
        if ($project === null) {
            return $html;
        }
        $service = app(Genba3dTermService::class);
        $term = $service->term($project['key']);
        $user = app(UserService::class)->GetUser((int) $request->session()->get('login_user_id'));

        $html = self::applyTerm($html, $term['start'], $term['end']);
        $data = [
            'project' => $project['name'],
            'start' => $term['start'],
            'end' => $term['end'],
            'source' => $term['source'],
            'updatedBy' => $term['updatedBy'],
            'updatedAt' => $term['updatedAt'],
            'canEdit' => (bool) $user && $service->ready(),
            'logs' => $service->logs($project['key']),
            'saveUrl' => route('genba3d.term', ['site' => $site]),
            'csrf' => csrf_token(),
        ];
        $head = '<script>window.GENBA3D_TERM='.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT).';</script>';
        $pos = stripos($html, '<script');
        $html = $pos === false ? $head.$html : substr($html, 0, $pos).$head.substr($html, $pos);

        $src = asset('js/genba3d-term.js').'?v='.(@filemtime(public_path('js/genba3d-term.js')) ?: '1');
        $ui = '<script src="'.e($src).'" defer></script>';

        return str_contains($html, '</body>') ? (string) preg_replace('~</body>~i', $ui.'</body>', $html, 1) : $html.$ui;
    }

    /**
     * ページに書いてある工期末（DEADLINE と、その日付を書いた見出し・注意の文）と、着手日の初期値を、
     * 入力された工期に置き換える。設計図書の説明として書いた日付（「設計図書の工期（…）」など）はそのまま。
     */
    public static function applyTerm(string $html, ?string $start, ?string $end): string
    {
        if ($end !== null && preg_match('/DEADLINE="(\d{4})-(\d\d)-(\d\d)"/', $html, $m)) {
            [, $y, $mo, $d] = $m;
            [$ny, $nmo, $nd] = explode('-', $end);
            $wa = fn (string $yy, string $mm, string $dd): string => '令和'.((int) $yy - 2018).'年'.(int) $mm.'月'.(int) $dd.'日';
            $old = $wa($y, $mo, $d);
            $new = $wa($ny, $nmo, $nd);
            $html = str_replace($m[0], 'DEADLINE="'.$end.'"', $html);
            if ($old !== $new) {
                $html = (string) preg_replace('/(工期末|工事完了期限)([（ ])'.preg_quote($old, '/').'(と仮定|（仮定）|（設計図書の工期）)?/u', '$1$2'.$new, $html);
                $html = str_replace('（'.(int) $mo.'/'.(int) $d.'）までの余裕', '（'.(int) $nmo.'/'.(int) $nd.'）までの余裕', $html);
            }
        }
        if ($start !== null) {
            $html = (string) preg_replace('/(const DEF=\{start:")\d{4}-\d\d-\d\d"/', '${1}'.$start.'"', $html, 1);
            $html = (string) preg_replace('/(id="start" value=")\d{4}-\d\d-\d\d"/', '${1}'.$start.'"', $html, 1);
            $html = (string) preg_replace("/(\\$\('#start'\)\.value \|\| ')\d{4}-\d\d-\d\d'/", '${1}'.$start."'", $html, 1);
        }

        return $html;
    }

    /** 現場ごとの追加資料（重機用足場のまとめなど）のタブ。 */
    public function extra(string $site, string $page): View
    {
        return $this->page($site, 'page', $page);
    }

    /** 追加資料の本体の HTML。 */
    public function extraPage(string $site, string $page): Response
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]['pages'][$page]['file']), 404);

        return $this->serve((string) $sites[$site]['pages'][$page]['file']);
    }

    /**
     * 「AIに質問」: 画面から送られた質問に、その現場の回答用資料をもとに答える。
     *
     * 質問のたびに API の料金がかかるので、1人あたり・全体の両方で1日の回数に上限を付ける。
     */
    public function ask(Request $request, string $site, Genba3dChatService $chat): JsonResponse
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]), 404);

        if (! $chat->availableFor($sites[$site])) {
            return response()->json(['message' => 'この現場では、AIへの質問はまだ使えません。'], 503);
        }

        $messages = $this->chatMessages($request);
        if ($messages === null) {
            $max = (int) config('genba3d.chat.max_question_length', 600);

            return response()->json(['message' => "質問を{$max}文字以内で入力してください。"], 422);
        }

        $userId = (int) session('login_user_id');
        $day = now()->format('Y-m-d');
        $userKey = "genba3d-chat:user:{$userId}:{$day}";
        $totalKey = "genba3d-chat:total:{$day}";
        $userLimit = (int) config('genba3d.chat.daily_limit_per_user', 20);
        if (RateLimiter::tooManyAttempts($userKey, $userLimit)
            || RateLimiter::tooManyAttempts($totalKey, (int) config('genba3d.chat.daily_limit_total', 60))) {
            return response()->json(['message' => '本日の質問回数の上限に達しました。明日また使えます。'], 429);
        }

        // 回答が返るまで数十秒かかることがあるので、途中で打ち切られないようにする。
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

        try {
            $result = $chat->ask($sites[$site], $messages, $userId);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'AIから回答を受け取れませんでした。少し時間をおいて、もう一度お試しください。'], 502);
        }

        // 回数は、回答を受け取れたときだけ数える（翌日まで保持）。
        RateLimiter::hit($userKey, 86400);
        RateLimiter::hit($totalKey, 86400);

        return response()->json([
            'answer' => $result['answer'],
            'truncated' => $result['truncated'],
            'remaining' => max(0, $userLimit - (int) RateLimiter::attempts($userKey)),
        ]);
    }

    /**
     * 画面から届いたやり取りを、API に渡せる形に整える。
     *
     * 最後が今回の質問（user）。その前は直前のやり取りで、user と assistant が交互に並ぶようにする。
     * 質問が空・長すぎるときは null。
     *
     * @return list<array{role: string, content: string}>|null
     */
    private function chatMessages(Request $request): ?array
    {
        $raw = $request->input('messages');
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $clean = [];
        foreach ($raw as $m) {
            $role = is_array($m) ? ($m['role'] ?? null) : null;
            $content = is_array($m) && is_string($m['content'] ?? null) ? trim($m['content']) : '';
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            // 過去の回答は長いことがあるので、渡す分だけ切り詰める。
            $clean[] = ['role' => $role, 'content' => mb_substr($content, 0, 2000)];
        }

        $last = end($clean);
        if ($last === false || $last['role'] !== 'user'
            || mb_strlen($last['content']) > (int) config('genba3d.chat.max_question_length', 600)) {
            return null;
        }

        // 新しい方から、役割が交互になるものだけを拾う（同じ役割が続いたら古い方を捨てる）。
        $kept = [];
        $want = 'user';
        foreach (array_reverse($clean) as $m) {
            if ($m['role'] !== $want) {
                continue;
            }
            $kept[] = $m;
            $want = $want === 'user' ? 'assistant' : 'user';
            if (count($kept) >= (int) config('genba3d.chat.max_history_messages', 8) + 1) {
                break;
            }
        }
        $kept = array_reverse($kept);
        // 先頭は user で始める。
        if ($kept[0]['role'] !== 'user') {
            array_shift($kept);
        }

        return $kept;
    }

    /** 現場とタブを切り替える枠と、本体を埋め込んだ画面。 */
    private function page(string $site, string $tab, ?string $pageKey = null): View
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]), 404);
        abort_if(in_array($tab, ['schedule', 'steps'], true) && empty($sites[$site]['schedule_file']), 404);
        abort_if($tab === 'page' && ! isset($sites[$site]['pages'][$pageKey]['file']), 404);

        // 現場を切り替えるボタンには、同じ工事の現場だけを並べる。
        $project = Genba3dCatalog::projectOf($site);

        return view('genba3d.show', [
            'title' => '現場3D｜'.$sites[$site]['name'],
            'project' => $project,
            'sites' => $project['sites'] ?? $sites,
            'currentSlug' => $site,
            'current' => $sites[$site],
            'tab' => $tab,
            'pageKey' => $tab === 'page' ? $pageKey : null,
            'chatEnabled' => app(Genba3dChatService::class)->availableFor($sites[$site]),
        ]);
    }

    private function document(string $site, string $key, ?string $part = null): Response
    {
        $sites = $this->sites();
        abort_unless(isset($sites[$site]) && ! empty($sites[$site][$key]), 404);

        return $this->serve((string) $sites[$site][$key], $part);
    }

    /** resources/genba3d/ のページを、画面内の枠（iframe）に入れられる形で返す。 */
    private function serve(string $file, ?string $part = null): Response
    {
        // ファイル名は設定からだけ決める（URL の文字列をパスに使わない）。
        $path = resource_path('genba3d/'.basename($file));
        abort_unless(is_file($path), 404);

        $html = (string) file_get_contents($path);
        // アプリ本体が明るい配色なので、端末の設定に関わらず明るい配色に固定する。
        $html = (string) preg_replace('/<html(?=[\s>])/i', '<html lang="ja" data-theme="light"', $html, 1);
        if ($part !== null) {
            $html = $this->onlyPart($html, $part);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** 施工手順と工程表のファイルを2つのタブに分けるときの名前。 */
    private const PARTS = ['steps', 'gantt'];

    /**
     * 施工手順と工程表のファイルのうち、片方の見出し（h2）のまとまりだけを残して見せる。
     *
     * ファイルごとに見出しの並びが少しずつ違うので、見出しの言葉で分ける。
     * 「施工手順」「数量」「確認」「気づいた点」を含む見出しは施工手順の側、それ以外（条件・見込み・工程表・
     * バーチャート・注意）は工程表の側。工程表の計算のスクリプトはそのまま動かし、表示だけを切り替える。
     */
    private function onlyPart(string $html, string $part): string
    {
        $script = '<script data-genba3d-part>(function(){var part='.json_encode($part).';'
            .'function stepsSide(t){return /施工手順|数量|確認|気づいた/.test(t);}'
            .'function run(){var hs=[].slice.call(document.querySelectorAll("h2"));'
            .'hs.forEach(function(h){var b=h;while(b.parentElement&&b.parentElement!==document.body&&b.parentElement.querySelectorAll("h2").length===1){b=b.parentElement;}'
            .'var keep=(part==="steps")===stepsSide(h.textContent||"");if(!keep){b.style.display="none";}});'
            // 工程表の側の注記（日数は概算…）は施工手順の側では出さない
            .'if(part==="steps"){[].slice.call(document.querySelectorAll(".wrap > p.flag")).forEach(function(p){if(/日数/.test(p.textContent||""))p.style.display="none";});}'
            .'}'
            .'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",run);}else{run();}})();</script>';

        return str_contains($html, '</body>')
            ? (string) preg_replace('~</body>~i', $script.'</body>', $html, 1)
            : $html.$script;
    }

    /**
     * @return array<string, array{name: string, summary: string, file: string, schedule_file?: string}>
     */
    private function sites(): array
    {
        return (array) config('genba3d.sites', []);
    }
}
