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
        'r07-20' => ['name' => 'R07-20重内', 'sites' => ['omonai-koren', 'omonai-omonai', 'omonai-shinma', 'omonai-haraguchi', 'omonai-hatsukami', 'omonai-toranosawa']],
        'r08-01' => ['name' => 'R08-01福島トンネル', 'sites' => ['fukushima-tunnel']],
        'r08-02' => ['name' => 'R08-02魚礁', 'sites' => []],
        'r08-03' => ['name' => 'R08-03桧倉', 'sites' => []],
        'r08-04' => ['name' => 'R08-04軌道施設', 'sites' => []],
        'r08-06' => ['name' => 'R08-06桧倉維持', 'sites' => []],
        'r08-08' => ['name' => 'R08-08滝ノ下', 'sites' => ['takinoshita-chisan']],
        'r08-11' => ['name' => 'R08-11吉岡', 'sites' => ['yoshioka-ganpeki', 'yoshioka-funaage', 'yoshioka-sokkou']],
        'r08-12' => ['name' => 'R08-12岩部線', 'sites' => []],
        'r08-14' => ['name' => 'R08-14豊浜', 'sites' => ['toyohama-kyukeisha', 'toyohama-fukushimagawa']],
        'r08-16' => ['name' => 'R08-16大沢', 'sites' => ['asahi-funaageba', 'asahi-higashi-gogan', 'osawa-kaigan-gogan']],
    ],

    'sites' => [
        'omonai-koren' => [
            'name' => '幸連橋',
            'summary' => '国道228号 知内町の幸連橋（L=49.0m、鋼2径間連続鈑桁）。G2の当て板補修と塗装塗替57m²、橋面防水98m²と舗装打換え、排水管8か所・床版水抜きパイプ10か所。',
            'file' => 'omonai-koren.html',
            'schedule_file' => 'omonai-kotei.html',
            'knowledge_file' => 'omonai-koren.md',
        ],
        'omonai-omonai' => [
            'name' => '重内橋',
            'summary' => '国道228号の重内橋（L=22.9m、鋼単純鈑桁）。桁端の当て板補修4か所（A1G1・A1G3・A1G4・A2G3）と沓座コンクリートの打ち直し。',
            'file' => 'omonai-omonai.html',
            'schedule_file' => 'omonai-kotei.html',
            'knowledge_file' => 'omonai-omonai.md',
        ],
        'omonai-shinma' => [
            'name' => '神馬橋（右歩道）',
            'summary' => '国道228号の神馬橋（右歩道、L=11.06m）。ベントで桁を仮受けして、A2橋台の断面修復とケイ酸塩系含浸防水材。',
            'file' => 'omonai-shinma.html',
            'schedule_file' => 'omonai-kotei.html',
            'knowledge_file' => 'omonai-shinma.md',
        ],
        'omonai-haraguchi' => [
            'name' => '原口大橋',
            'summary' => '国道228号 松前町の原口大橋（L=163.4m、3径間連続ローゼアーチ）。床版・地覆の断面修復21か所と落下物防止柵のボルト交換2,120本。吊足場580m²。',
            'file' => 'omonai-haraguchi.html',
            'schedule_file' => 'omonai-kotei.html',
            'knowledge_file' => 'omonai-haraguchi.md',
        ],
        'omonai-hatsukami' => [
            'name' => '初神大橋',
            'summary' => '国道228号の初神大橋（L=190.0m、鋼鈑桁＋鋼トラス）。P1・P2橋脚の断面修復0.5m³、犠牲陽極材41個、ひびわれ注入。枠組足場1,420m²。',
            'file' => 'omonai-hatsukami.html',
            'schedule_file' => 'omonai-kotei.html',
            'knowledge_file' => 'omonai-hatsukami.md',
        ],
        'omonai-toranosawa' => [
            'name' => '寅の沢橋',
            'summary' => '国道228号 上ノ国町の寅の沢橋（L=48.7m、鋼2径間連続鈑桁）。水平補剛材の当て板補修60か所と床版水抜きのフレキシブルチューブ31か所。吊足場450m²。',
            'file' => 'omonai-toranosawa.html',
            'schedule_file' => 'omonai-kotei.html',
            'knowledge_file' => 'omonai-toranosawa.md',
        ],
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
        'yoshioka-ganpeki' => [
            'name' => '吉岡漁港 -4.5m岸壁',
            'summary' => '吉岡漁港 -4.5m岸壁（A部40.2m・B部69.8m）。上部コンクリートと舗装の打替え、防舷材30基・車止め36本・縁金物110mの更新、係船柱11基の流用。標識灯・浮標灯の取替え。',
            'file' => 'yoshioka-ganpeki.html',
            'schedule_file' => 'yoshioka-kotei.html',
            'knowledge_file' => 'yoshioka-ganpeki.md',
        ],
        'yoshioka-funaage' => [
            'name' => '吉岡漁港 第2船揚場',
            'summary' => '吉岡漁港 第2船揚場（L=43.8m）。斜路の舗装コンクリートと止壁47mの打替え、張ブロック78個の据直し（うち8個は新規）、滑り材211mの再設置。',
            'file' => 'yoshioka-funaage.html',
            'schedule_file' => 'yoshioka-kotei.html',
            'knowledge_file' => 'yoshioka-funaage.md',
        ],
        'yoshioka-sokkou' => [
            'name' => '吉岡漁港 -3.0m岸壁の側溝',
            'summary' => '吉岡漁港 -3.0m岸壁の背後の側溝。自由勾配側溝（B300-H900）16.4mへの入替えと、舗装の復旧。',
            'file' => 'yoshioka-sokkou.html',
            'schedule_file' => 'yoshioka-kotei.html',
            'knowledge_file' => 'yoshioka-sokkou.md',
        ],
        'toyohama-kyukeisha' => [
            'name' => '福島豊浜 急傾斜地（土留柵工）',
            'summary' => '福島町豊浜の急傾斜地。H形鋼杭の土留柵（2工区2段目、3工区2・3段目、4工区1・2段目）、土留横材54m、崩土防止横材57m、山腹水路59m、鋼製階段2基。相取工法（ジブクレーン）。',
            'file' => 'toyohama-kyukeisha.html',
            'schedule_file' => 'toyohama-kotei.html',
            'knowledge_file' => 'toyohama-kyukeisha.md',
        ],
        'toyohama-fukushimagawa' => [
            'name' => '福島川 管理用通路（転落防止柵）',
            'summary' => '福島川右岸の管理用通路（SP393.80〜645.44）。転落防止柵 h=1.1m 167m・基礎ブロック87個、手摺 h=0.2m 77m・h=0.6m 6m、通路の土工。上流の河道掘削500m³と伐木は工程表に。',
            'file' => 'toyohama-fukushimagawa.html',
            'schedule_file' => 'toyohama-kotei.html',
            'knowledge_file' => 'toyohama-fukushimagawa.md',
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
