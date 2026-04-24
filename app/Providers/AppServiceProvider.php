<?php

namespace App\Providers;

use App\Services\InAppNotificationService;
use App\Services\LinkUserService;
use App\Services\PaidLeaveService;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.app', function ($view) {
            if (! session()->has('login_user_id')) {
                $view->with([
                    'inAppUnreadCount' => 0,
                    'canApprovePaidLeave' => false,
                ]);

                return;
            }

            $uid = (int) session('login_user_id');
            $unread = app(InAppNotificationService::class)->unreadCount($uid);
            $staff = app(LinkUserService::class)->GetLinkedStaff($uid);
            $canApprove = $staff && app(PaidLeaveService::class)->isApproverStaffId((int) $staff->id);

            $view->with([
                'inAppUnreadCount' => $unread,
                'canApprovePaidLeave' => (bool) $canApprove,
            ]);
        });

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
