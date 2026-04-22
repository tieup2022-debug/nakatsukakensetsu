<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$basePath = dirname(__DIR__);
$defaultPublicPath = $basePath . '/public';
$stagePublicPath = $basePath . '/stage';

$app = Application::configure(basePath: $basePath)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'nakatsuka.auth' => \App\Http\Middleware\NakatsukaAuth::class,
        ]);
        $middleware->encryptCookies(except: [
            (string) env('REMEMBER_WEB_COOKIE', 'nakatsuka_remember_web'),
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\RestoreWebSessionFromRemember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Xserver staging では公開フォルダが basePath/public ではなく basePath/stage になっているため、
// PDF出力(DomPDF)などで public_path() が呼ばれたときにパス解決できるよう明示する。
if (!is_dir($defaultPublicPath) && is_dir($stagePublicPath)) {
    $app->usePublicPath($stagePublicPath);
}

return $app;
