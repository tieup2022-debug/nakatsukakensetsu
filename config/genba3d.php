<?php

/*
|--------------------------------------------------------------------------
| 現場3D（設計図から組み立てた3Dモデルと、施工手順・工程表）
|--------------------------------------------------------------------------
|
| メニューの「現場3D」に並べる現場の一覧。並び順がそのまま表示順になる。
| キー（slug）は URL に使うので、半角の英小文字・数字・ハイフンだけにする。
| file は3Dモデル、schedule_file は施工手順と工程表の HTML ファイル名で、
| どちらも resources/genba3d/ 配下に置く。schedule_file は無ければ省略できる
| （その現場では「施工手順と工程表」のタブが出ない）。
| 現場を追加するときは、HTML を置いてここに1件足す。
|
| pages は、その現場だけの追加資料（重機用足場のまとめなど）。キーは URL に使う
| 半角の英小文字・数字・ハイフン、label はタブの名前、note はタブを開いたときに
| 見出しの下に出す説明、file は resources/genba3d/ 配下の HTML ファイル名。
|
| projects は工事の一覧。メニューの「現場3D」と一覧ページ（/genba3d）は工事ごとに
| まとめて表示する。sites にその工事の現場のキーを並べる。sites が空の工事は、
| 一覧ページに「準備中」として名前だけ出る。新しい現場を作ったら、下の sites に
| 1件足し、その工事の sites にキーを書き足す。
|
| knowledge_file は「AIに質問」が答えの根拠にする資料（resources/genba3d/knowledge/
| 配下のテキスト）。3Dモデルと工程表の画面に載っている寸法・数量・手順・確認事項を、
| 根拠の図面名つきでまとめてある。ページの中身を直したら、こちらも合わせて直す。
| 無い現場では「AIに質問」のボタンが出ない。
|
*/

return [
    'projects' => [
        'r08-01' => ['name' => 'R08-01福島トンネル', 'sites' => ['fukushima-tunnel']],
        'r08-02' => ['name' => 'R08-02魚礁', 'sites' => []],
        'r08-03' => ['name' => 'R08-03桧倉', 'sites' => []],
        'r08-04' => ['name' => 'R08-04軌道施設', 'sites' => []],
        'r08-06' => ['name' => 'R08-06桧倉維持', 'sites' => []],
        'r08-08' => ['name' => 'R08-08滝ノ下', 'sites' => ['takinoshita-chisan']],
        'r08-11' => ['name' => 'R08-11吉岡', 'sites' => []],
        'r08-12' => ['name' => 'R08-12岩部線', 'sites' => []],
        'r08-14' => ['name' => 'R08-14豊浜', 'sites' => []],
        'r08-16' => ['name' => 'R08-16大沢', 'sites' => ['asahi-funaageba', 'asahi-higashi-gogan', 'osawa-kaigan-gogan']],
    ],

    'sites' => [
        'asahi-funaageba' => [
            'name' => '朝日地区 船揚場',
            'summary' => '大沢朝日漁港（朝日地区）船揚場。張コンクリート打替えと滑り材の撤去・新設（P8.49〜P33.06、L=24.5m）。',
            'file' => 'asahi-funaageba.html',
            'schedule_file' => 'asahi-funaageba-kotei.html',
            'knowledge_file' => 'asahi-funaageba.md',
        ],
        'asahi-higashi-gogan' => [
            'name' => '朝日地区 東護岸',
            'summary' => '大沢朝日漁港（朝日地区）東護岸。機能保全工事の本体工・上部工（今回施工 SP 0.0〜24.98）。',
            'file' => 'asahi-higashi-gogan.html',
            'schedule_file' => 'asahi-higashi-gogan-kotei.html',
            'pages' => [
                'ashiba' => [
                    'label' => '重機用足場',
                    'note' => '仮設工平面図と工事用道路詳細図（どちらも参考図）から、重機用足場の形・数量とクレーンの位置をまとめた資料です。寸法や数量は元の図面で確かめてください。',
                    'file' => 'asahi-higashi-gogan-ashiba.html',
                ],
            ],
            'knowledge_file' => 'asahi-higashi-gogan.md',
        ],
        'osawa-kaigan-gogan' => [
            'name' => '大沢漁港海岸 護岸工',
            'summary' => '大沢漁港海岸 護岸工（SP 0.00〜10.39、護岸工 L=10.39m／打止工 L=12.85m）。',
            'file' => 'osawa-kaigan-gogan.html',
            'schedule_file' => 'osawa-kaigan-gogan-kotei.html',
            'knowledge_file' => 'osawa-kaigan-gogan.md',
        ],
        'fukushima-tunnel' => [
            'name' => '福島トンネル補修工事',
            'summary' => '一般国道228号 福島トンネル（L=382m）。坑口のルーバー部のシート防水・塗装・金属パテ補修、起点側PS001のはく落対策、トンネル本体の漏水対策。',
            'file' => 'fukushima-tunnel.html',
            'schedule_file' => 'fukushima-tunnel-kotei.html',
            'knowledge_file' => 'fukushima-tunnel.md',
        ],
        'takinoshita-chisan' => [
            'name' => '滝ノ下覆道地先 緊急総合治山工事',
            'summary' => '国道228号 滝ノ下覆道の山側の斜面。法切工804m³、暗渠工（パイプ72m・線状排水材178m）、現場打吹付法枠工2,182.8m²、客土注入マット1,624.5m²。',
            'file' => 'takinoshita-chisan.html',
            'schedule_file' => 'takinoshita-chisan-kotei.html',
            'knowledge_file' => 'takinoshita-chisan.md',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AIに質問（Claude API）
    |--------------------------------------------------------------------------
    |
    | APIキー（.env の ANTHROPIC_API_KEY）が入っているときだけ、現場3Dの画面に
    | 「AIに質問」が出る。キーは .env にだけ置き、リポジトリには入れない。
    | 質問のたびに料金がかかるので、1日あたりの回数に上限を付けている。
    |
    */
    'chat' => [
        'enabled' => (bool) env('GENBA3D_CHAT_ENABLED', true),
        'model' => env('GENBA3D_CHAT_MODEL', 'claude-sonnet-5-5'),
        // 回答の長さの上限（トークン）
        'max_tokens' => max(200, (int) env('GENBA3D_CHAT_MAX_TOKENS', 1200)),
        // 1日あたりの質問回数の上限（1人あたり／全員の合計）
        'daily_limit_per_user' => max(1, (int) env('GENBA3D_CHAT_DAILY_LIMIT_PER_USER', 20)),
        'daily_limit_total' => max(1, (int) env('GENBA3D_CHAT_DAILY_LIMIT_TOTAL', 60)),
        // 1回の質問の文字数の上限と、AIに渡す直前のやり取りの件数
        'max_question_length' => 600,
        'max_history_messages' => 8,
    ],
];
