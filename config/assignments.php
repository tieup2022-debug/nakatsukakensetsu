<?php

return [
    'master_type' => [
        'staff' => env('MASTER_TYPE_STAFF', '1'),
        'vehicle' => env('MASTER_TYPE_VEHICLE', '2'),
        'equipment' => env('MASTER_TYPE_EQUIPMENT', '3'),
    ],

    // 総務など現場に出ないスタッフ向けの設定。
    // 属性「総務部」(soumu_staff_type) のスタッフは毎日「会社」現場
    // (company_workplace_name) に自動配置し、勤怠入力のみ行えるようにする。
    // 配置一覧・配置PDFには載せない（会社現場は配置の現場一覧から除外する）。
    'company' => [
        'workplace_name' => env('COMPANY_WORKPLACE_NAME', '会社'),
        'soumu_staff_type' => (int) env('SOUMU_STAFF_TYPE', 4),
    ],
];

