<?php

namespace App\Http\Controllers;

use App\Mail\SystemInquiryReceivedMail;
use App\Services\SystemInquiryService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemInquiryController extends Controller
{
    public function __construct(
        private SystemInquiryService $systemInquiryService,
        private UserService $userService,
    ) {}

    public function create(Request $request): View
    {
        $uid = (int) $request->session()->get('login_user_id');
        $user = $uid > 0 ? $this->userService->GetUser($uid) : null;

        return view('system_inquiry.create', [
            'title' => 'お問い合わせ',
            'current_user_name' => $user ? (string) $user->user_name : '',
            'can_view_inquiry_list' => $user && (int) $user->permission === 1,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ]);

        $uid = (int) $request->session()->get('login_user_id');
        $user = $uid > 0 ? $this->userService->GetUser($uid) : null;
        if (! $user) {
            return redirect()->route('login');
        }

        $userName = (string) $user->user_name;
        $row = $this->systemInquiryService->create($uid, $userName, $validated['body']);

        if (! $row) {
            return back()->withInput()->with('error', '送信に失敗しました。しばらくしてから再度お試しください。');
        }

        $at = SystemInquiryService::formatStoredAt($row->created_at, 'Y年n月j日 G:i');
        $this->sendReceivedNotification($row, $userName, $at);

        return redirect()->route('inquiry.create')->with(
            'status',
            'お問い合わせを送信しました。（送信者: '.$userName.'、送信日時: '.$at.'）'
        );
    }

    /**
     * 管理者（権限1）のみ。
     */
    public function adminIndex(Request $request)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $rows = $this->systemInquiryService->listRecent(200);

        return view('system_inquiry.index', [
            'title' => 'お問い合わせ一覧',
            'rows' => $rows,
            'inquiry_status_labels' => SystemInquiryService::statusLabels(),
        ]);
    }

    /**
     * 管理者（権限1）のみ。
     */
    public function adminUpdateStatus(Request $request, int $id)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(SystemInquiryService::statusLabels()))],
        ]);

        if (! $this->systemInquiryService->updateStatus($id, $request->input('status'))) {
            return redirect()->route('setting.inquiry.index')->with('error', '状態を更新できませんでした。');
        }

        return redirect()->route('setting.inquiry.index')->with('status', '対応状況を更新しました。');
    }

    /**
     * 管理者（権限1）のみ。
     */
    public function adminDestroy(Request $request, int $id)
    {
        if ($redirect = $this->redirectUnlessMaster($request)) {
            return $redirect;
        }

        if (! $this->systemInquiryService->deleteById($id)) {
            return redirect()->route('setting.inquiry.index')->with('error', '削除できませんでした。');
        }

        return redirect()->route('setting.inquiry.index')->with('status', 'お問い合わせを削除しました。');
    }

    private function isMasterUser(Request $request): bool
    {
        $uid = (int) $request->session()->get('login_user_id');
        if ($uid <= 0) {
            return false;
        }

        $user = $this->userService->GetUser($uid);

        return $user && (int) $user->permission === 1;
    }

    private function redirectUnlessMaster(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        if (! $this->isMasterUser($request)) {
            return redirect()->route('top.setting')->with('status', 'お問い合わせ一覧は管理者（権限1）のみ利用できます。');
        }

        return null;
    }

    private function sendReceivedNotification(object $inquiry, string $userName, string $submittedAt): void
    {
        $email = trim((string) config('system_inquiry.notification_email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('お問い合わせ通知メールの送信先が設定されていません。', [
                'inquiry_id' => (int) $inquiry->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new SystemInquiryReceivedMail(
                inquiryId: (int) $inquiry->id,
                submittedBy: $userName,
                submittedAt: $submittedAt,
                inquiryBody: (string) $inquiry->body,
                inquiryListUrl: route('setting.inquiry.index'),
            ));
        } catch (\Throwable $e) {
            // メール障害があっても、利用者のお問い合わせ登録は成功扱いにする。
            Log::error('お問い合わせ通知メールの送信に失敗しました。', [
                'inquiry_id' => (int) $inquiry->id,
                'recipient' => $email,
                'exception' => $e,
            ]);
        }
    }
}
