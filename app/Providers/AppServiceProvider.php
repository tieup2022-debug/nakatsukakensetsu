<?php

namespace App\Providers;

use App\Services\InAppNotificationService;
use App\Services\LinkUserService;
use App\Services\PaidLeaveService;
use App\Services\UserService;
use App\Support\UserPermission;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        // ログイン総当たり対策: ログインID＋IP単位で 5回/分に制限する。
        // 事務所の共有IPでも、他人の失敗で巻き込まれてロックされないようにするため
        // IP だけでなくログインIDでもキーを分ける。
        RateLimiter::for('login', function (Request $request) {
            $loginId = Str::lower((string) $request->input('login_id'));

            return Limit::perMinute(5)->by($loginId.'|'.$request->ip());
        });

        View::composer('layouts.app', function ($view) {
            if (! session()->has('login_user_id')) {
            $view->with([
                'inAppUnreadCount' => 0,
                'canApprovePaidLeave' => false,
                'canAccessAssignmentSettings' => false,
            ]);

                return;
            }

            $uid = (int) session('login_user_id');
            $unread = app(InAppNotificationService::class)->unreadCount($uid);
            $staff = app(LinkUserService::class)->GetLinkedStaff($uid);
            $canApprove = $staff && app(PaidLeaveService::class)->isApproverStaffId((int) $staff->id);
            $loginUser = app(UserService::class)->GetUser($uid);
            $canAccessAssignmentSettings = $loginUser && UserPermission::isManager($loginUser->permission ?? null);

            $view->with([
                'inAppUnreadCount' => $unread,
                'canApprovePaidLeave' => (bool) $canApprove,
                'canAccessAssignmentSettings' => (bool) $canAccessAssignmentSettings,
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
