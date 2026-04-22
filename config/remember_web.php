<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web 「ログイン状態を保持する」用 Cookie
    |--------------------------------------------------------------------------
    |
    | セッション Cookie が失われた場合（スマホでタブを切る等）に、
    | 暗号化した長期 Cookie と DB の access_token_web で再ログインする。
    |
    */

    'cookie' => env('REMEMBER_WEB_COOKIE', 'nakatsuka_remember_web'),

    'lifetime_minutes' => (int) env('REMEMBER_WEB_LIFETIME', 60 * 24 * 30),

];
