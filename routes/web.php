<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InAppNotificationController;
use App\Http\Controllers\MonthlyAssignmentController;
use App\Http\Controllers\MonthlyAttendanceController;
use App\Http\Controllers\PaidLeaveApprovalController;
use App\Http\Controllers\PaidLeaveController;
use App\Http\Controllers\SettingAccountController;
use App\Http\Controllers\SettingAssignmentController;
use App\Http\Controllers\SettingAttendanceController;
use App\Http\Controllers\SettingEquipmentController;
use App\Http\Controllers\SettingNewsController;
use App\Http\Controllers\SettingStaffController;
use App\Http\Controllers\SettingUserController;
use App\Http\Controllers\SettingUtilizationRateController;
use App\Http\Controllers\SettingVehicleController;
use App\Http\Controllers\SettingWorkplaceController;
use App\Http\Controllers\SystemInquiryController;
use App\Http\Controllers\TopAssignmentController;
use App\Http\Controllers\TopAttendanceController;
use App\Http\Controllers\TopSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/dashboard', function () {
    if (! session()->has('login_user_id')) {
        return redirect()->route('login');
    }

    return redirect()->route('top.assignment');
})->name('dashboard');

Route::get('/top/attendance', [TopAttendanceController::class, 'index'])->name('top.attendance');
Route::post('/top/attendance/update', [TopAttendanceController::class, 'update'])->name('top.attendance.update');
Route::get('/top/assignment', [TopAssignmentController::class, 'index'])->name('top.assignment');
Route::post('/top/assignment/update', [TopAssignmentController::class, 'update'])->name('top.assignment.update');
Route::post('/top/assignment/copy', [TopAssignmentController::class, 'copy'])->name('top.assignment.copy');

Route::get('/top/setting', [TopSettingController::class, 'index'])->middleware('nakatsuka.auth')->name('top.setting');

// staff
Route::get('/setting/staff/manage', [SettingStaffController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.staff.manage');
Route::get('/setting/staff/create', [SettingStaffController::class, 'showCreate'])->middleware('nakatsuka.auth')->name('setting.staff.create');
Route::post('/setting/staff/create', [SettingStaffController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.staff.create.submit');
Route::get('/setting/staff/list', [SettingStaffController::class, 'list'])->middleware('nakatsuka.auth')->name('setting.staff.list');
Route::post('/setting/staff/sort', [SettingStaffController::class, 'sort'])->middleware('nakatsuka.auth')->name('setting.staff.sort');
Route::get('/setting/staff/update', [SettingStaffController::class, 'showUpdate'])->middleware('nakatsuka.auth')->name('setting.staff.update');
Route::post('/setting/staff/update', [SettingStaffController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.staff.update.submit');
Route::post('/setting/staff/delete', [SettingStaffController::class, 'delete'])->middleware('nakatsuka.auth')->name('setting.staff.delete');

// workplace
Route::get('/setting/workplace/manage', [SettingWorkplaceController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.workplace.manage');
Route::get('/setting/workplace/create', [SettingWorkplaceController::class, 'showCreate'])->middleware('nakatsuka.auth')->name('setting.workplace.create');
Route::post('/setting/workplace/create', [SettingWorkplaceController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.workplace.create.submit');
Route::get('/setting/workplace/list', [SettingWorkplaceController::class, 'list'])->middleware('nakatsuka.auth')->name('setting.workplace.list');
Route::get('/setting/workplace/completed/list', [SettingWorkplaceController::class, 'listCompleted'])->middleware('nakatsuka.auth')->name('setting.workplace.completed.list');
Route::get('/setting/workplace/update', [SettingWorkplaceController::class, 'showUpdate'])->middleware('nakatsuka.auth')->name('setting.workplace.update');
Route::post('/setting/workplace/update', [SettingWorkplaceController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.workplace.update.submit');
Route::post('/setting/workplace/delete', [SettingWorkplaceController::class, 'delete'])->middleware('nakatsuka.auth')->name('setting.workplace.delete');

// vehicle
Route::get('/setting/vehicle/manage', [SettingVehicleController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.vehicle.manage');
Route::get('/setting/vehicle/create', [SettingVehicleController::class, 'showCreate'])->middleware('nakatsuka.auth')->name('setting.vehicle.create');
Route::post('/setting/vehicle/create', [SettingVehicleController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.vehicle.create.submit');
Route::get('/setting/vehicle/list', [SettingVehicleController::class, 'list'])->middleware('nakatsuka.auth')->name('setting.vehicle.list');
Route::post('/setting/vehicle/sort', [SettingVehicleController::class, 'sort'])->middleware('nakatsuka.auth')->name('setting.vehicle.sort');
Route::get('/setting/vehicle/update', [SettingVehicleController::class, 'showUpdate'])->middleware('nakatsuka.auth')->name('setting.vehicle.update');
Route::post('/setting/vehicle/update', [SettingVehicleController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.vehicle.update.submit');
Route::post('/setting/vehicle/delete', [SettingVehicleController::class, 'delete'])->middleware('nakatsuka.auth')->name('setting.vehicle.delete');

// equipment
Route::get('/setting/equipment/manage', [SettingEquipmentController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.equipment.manage');
Route::get('/setting/equipment/create', [SettingEquipmentController::class, 'showCreate'])->middleware('nakatsuka.auth')->name('setting.equipment.create');
Route::post('/setting/equipment/create', [SettingEquipmentController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.equipment.create.submit');
Route::get('/setting/equipment/list', [SettingEquipmentController::class, 'list'])->middleware('nakatsuka.auth')->name('setting.equipment.list');
Route::post('/setting/equipment/sort', [SettingEquipmentController::class, 'sort'])->middleware('nakatsuka.auth')->name('setting.equipment.sort');
Route::get('/setting/equipment/update', [SettingEquipmentController::class, 'showUpdate'])->middleware('nakatsuka.auth')->name('setting.equipment.update');
Route::post('/setting/equipment/update', [SettingEquipmentController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.equipment.update.submit');
Route::post('/setting/equipment/delete', [SettingEquipmentController::class, 'delete'])->middleware('nakatsuka.auth')->name('setting.equipment.delete');

// attendance settings
Route::get('/setting/attendance/manage', [SettingAttendanceController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.attendance.manage');
Route::get('/setting/attendance/defaults', [SettingAttendanceController::class, 'attendanceDefaults'])->middleware('nakatsuka.auth')->name('setting.attendance.defaults');
Route::post('/setting/attendance/defaults', [SettingAttendanceController::class, 'attendanceDefaultsSubmit'])->middleware('nakatsuka.auth')->name('setting.attendance.defaults.submit');
Route::get('/setting/attendance/edit', [SettingAttendanceController::class, 'edit'])->middleware('nakatsuka.auth')->name('setting.attendance.edit');
Route::get('/setting/attendance/input', [SettingAttendanceController::class, 'input'])->middleware('nakatsuka.auth')->name('setting.attendance.input');
Route::post('/setting/attendance/create', [SettingAttendanceController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.attendance.create');
Route::get('/setting/attendance/list', [SettingAttendanceController::class, 'list'])->middleware('nakatsuka.auth')->name('setting.attendance.list');
Route::post('/setting/attendance/delete', [SettingAttendanceController::class, 'delete'])->middleware('nakatsuka.auth')->name('setting.attendance.delete');

// absence management
Route::get('/setting/attendance/absence/workdate', [SettingAttendanceController::class, 'absenceWorkdate'])->middleware('nakatsuka.auth')->name('setting.attendance.absence.workdate');
Route::get('/setting/attendance/absence/staff', [SettingAttendanceController::class, 'absenceInputStaff'])->middleware('nakatsuka.auth')->name('setting.attendance.absence.staff');
Route::post('/setting/attendance/absence/update', [SettingAttendanceController::class, 'absenceUpdate'])->middleware('nakatsuka.auth')->name('setting.attendance.absence.update');

// monthly attendance (Web preview + PDF download)
Route::get('/setting/attendance/monthly', [MonthlyAttendanceController::class, 'form'])->middleware('nakatsuka.auth')->name('setting.attendance.monthly.form');
Route::get('/setting/attendance/monthly/preview', [MonthlyAttendanceController::class, 'preview'])->middleware('nakatsuka.auth')->name('setting.attendance.monthly.preview');
Route::post('/setting/attendance/monthly/download', [MonthlyAttendanceController::class, 'download'])->middleware('nakatsuka.auth')->name('setting.attendance.monthly.download');
Route::get('/setting/attendance/personal-summary', [SettingAttendanceController::class, 'personalSummary'])->middleware('nakatsuka.auth')->name('setting.attendance.personal.summary');
Route::get('/setting/attendance/personal-summary/pdf', [SettingAttendanceController::class, 'personalSummaryPdf'])->middleware('nakatsuka.auth')->name('setting.attendance.personal.summary.pdf');

// assignment settings
Route::get('/setting/assignment/manage', [SettingAssignmentController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.assignment.manage');
Route::get('/setting/assignment/edit', [SettingAssignmentController::class, 'edit'])->middleware('nakatsuka.auth')->name('setting.assignment.edit');
Route::post('/setting/assignment/update', [SettingAssignmentController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.assignment.update');
Route::post('/setting/assignment/copy', [SettingAssignmentController::class, 'copy'])->middleware('nakatsuka.auth')->name('setting.assignment.copy');

// assignment daily/monthly PDF
Route::get('/setting/assignment/monthly', [MonthlyAssignmentController::class, 'form'])->middleware('nakatsuka.auth')->name('setting.assignment.monthly.form');
Route::post('/setting/assignment/monthly/download', [MonthlyAssignmentController::class, 'download'])->middleware('nakatsuka.auth')->name('setting.assignment.monthly.download');

// utilization rate
Route::get('/setting/utilizationrate', [SettingUtilizationRateController::class, 'getUtilizationRate'])->middleware('nakatsuka.auth')->name('setting.utilizationrate.index');

// user management
Route::get('/setting/user/manage', [SettingUserController::class, 'manage'])->middleware('nakatsuka.auth')->name('setting.user.manage');
Route::get('/setting/user/create', [SettingUserController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.user.create');
Route::post('/setting/user/create', [SettingUserController::class, 'create'])->middleware('nakatsuka.auth')->name('setting.user.create.submit');
Route::get('/setting/user/list', [SettingUserController::class, 'list'])->middleware('nakatsuka.auth')->name('setting.user.list');
Route::get('/setting/user/update', [SettingUserController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.user.update');
Route::post('/setting/user/update', [SettingUserController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.user.update.submit');
Route::get('/setting/user/password-reset', [SettingUserController::class, 'resetPasswordForm'])->middleware('nakatsuka.auth')->name('setting.user.password.form');
Route::post('/setting/user/password-reset', [SettingUserController::class, 'resetPasswordSubmit'])->middleware('nakatsuka.auth')->name('setting.user.password.reset');
Route::post('/setting/user/delete', [SettingUserController::class, 'delete'])->middleware('nakatsuka.auth')->name('setting.user.delete');

// news
Route::get('/setting/news/update', [SettingNewsController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.news.update');
Route::post('/setting/news/update', [SettingNewsController::class, 'update'])->middleware('nakatsuka.auth')->name('setting.news.update.submit');
Route::get('/setting/news/history', [SettingNewsController::class, 'history'])->middleware('nakatsuka.auth')->name('setting.news.history');

// account (link user / password)
Route::get('/setting/account/linkuser/update', [SettingAccountController::class, 'linkUserUpdate'])->middleware('nakatsuka.auth')->name('setting.account.linkuser.update');
Route::post('/setting/account/linkuser/update', [SettingAccountController::class, 'linkUserUpdate'])->middleware('nakatsuka.auth')->name('setting.account.linkuser.update.submit');
Route::get('/setting/account/password/update', [SettingAccountController::class, 'passwordUpdate'])->middleware('nakatsuka.auth')->name('setting.account.password.update');
Route::post('/setting/account/password/update', [SettingAccountController::class, 'passwordUpdate'])->middleware('nakatsuka.auth')->name('setting.account.password.update.submit');

// 有給申請・承認・システム内通知
Route::get('/paid-leave', [PaidLeaveController::class, 'index'])->middleware('nakatsuka.auth')->name('paid-leave.index');
Route::post('/paid-leave', [PaidLeaveController::class, 'store'])->middleware('nakatsuka.auth')->name('paid-leave.store');
Route::get('/paid-leave/mine', [PaidLeaveController::class, 'mine'])->middleware('nakatsuka.auth')->name('paid-leave.mine');
Route::get('/paid-leave/approvals', [PaidLeaveApprovalController::class, 'index'])->middleware('nakatsuka.auth')->name('paid-leave.approvals');
Route::post('/paid-leave/{id}/approve', [PaidLeaveApprovalController::class, 'approve'])->middleware('nakatsuka.auth')->name('paid-leave.approve')->where('id', '[0-9]+');
Route::get('/notifications', [InAppNotificationController::class, 'index'])->middleware('nakatsuka.auth')->name('notifications.index');
Route::post('/notifications/{id}/read', [InAppNotificationController::class, 'markRead'])->middleware('nakatsuka.auth')->name('notifications.read')->where('id', '[0-9]+');
Route::post('/notifications/read-all', [InAppNotificationController::class, 'markAllRead'])->middleware('nakatsuka.auth')->name('notifications.read-all');

// お問い合わせ（不具合・追加要望）
Route::get('/inquiry', [SystemInquiryController::class, 'create'])->middleware('nakatsuka.auth')->name('inquiry.create');
Route::post('/inquiry', [SystemInquiryController::class, 'store'])->middleware('nakatsuka.auth')->name('inquiry.store');
Route::get('/setting/inquiry/list', [SystemInquiryController::class, 'adminIndex'])->middleware('nakatsuka.auth')->name('setting.inquiry.index');
Route::patch('/setting/inquiry/{id}/status', [SystemInquiryController::class, 'adminUpdateStatus'])->middleware('nakatsuka.auth')->name('setting.inquiry.status')->where('id', '[0-9]+');
Route::delete('/setting/inquiry/{id}', [SystemInquiryController::class, 'adminDestroy'])->middleware('nakatsuka.auth')->name('setting.inquiry.destroy')->where('id', '[0-9]+');
