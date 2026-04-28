<?php

namespace App\Http\Controllers;

use App\Services\SystemInquiryService;
use App\Services\UserService;
use Illuminate\Http\Request;
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
        ]);
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
}
