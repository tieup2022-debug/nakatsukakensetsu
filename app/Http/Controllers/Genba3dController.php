<?php

namespace App\Http\Controllers;

use App\Services\Genba3dChatService;
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
            'upcoming' => array_filter($projects, fn (array $project): bool => $project['sites'] === []),
            'koeOpen' => $this->koeOpenCount(),
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

        return $this->document($site, 'schedule_file', in_array($part, self::PARTS, true) ? $part : null);
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
