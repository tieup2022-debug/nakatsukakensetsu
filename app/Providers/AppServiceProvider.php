<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Global helper functions used by legacy business logic.
        $dateHelper = app_path('Helpers/DateHelper.php');
        if (file_exists($dateHelper)) {
            require_once $dateHelper;
        }

        $logHelper = app_path('Helpers/LogHelper.php');
        if (file_exists($logHelper)) {
            require_once $logHelper;
        }
    }
}
