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
*/

return [
    'sites' => [
        'asahi-funaageba' => [
            'name' => '朝日地区 船揚場',
            'summary' => '大沢朝日漁港（朝日地区）船揚場。張コンクリート打替えと滑り材の撤去・新設（P8.49〜P33.06、L=24.5m）。',
            'file' => 'asahi-funaageba.html',
            'schedule_file' => 'asahi-funaageba-kotei.html',
        ],
        'asahi-higashi-gogan' => [
            'name' => '朝日地区 東護岸',
            'summary' => '大沢朝日漁港（朝日地区）東護岸。機能保全工事の本体工・上部工（今回施工 SP 0.0〜24.98）。',
            'file' => 'asahi-higashi-gogan.html',
            'schedule_file' => 'asahi-higashi-gogan-kotei.html',
        ],
        'osawa-kaigan-gogan' => [
            'name' => '大沢漁港海岸 護岸工',
            'summary' => '大沢漁港海岸 護岸工（SP 0.00〜10.39、護岸工 L=10.39m／打止工 L=12.85m）。',
            'file' => 'osawa-kaigan-gogan.html',
            'schedule_file' => 'osawa-kaigan-gogan-kotei.html',
        ],
    ],
];
