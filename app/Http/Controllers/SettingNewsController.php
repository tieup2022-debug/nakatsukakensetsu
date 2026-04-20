<?php

namespace App\Http\Controllers;

use App\Services\NewsService;
use Illuminate\Http\Request;

class SettingNewsController extends Controller
{
    private NewsService $newsService;

    public function __construct(NewsService $newsService)
    {
        $this->newsService = $newsService;
    }

    public function update(Request $request)
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $result = $this->newsService->update(
                $request->input('news'),
                $request->session()->get('login_user_id')
            );
        }

        return view('setting.news.update')->with([
            'news_data' => $this->newsService->GetNews(),
            'result' => $result,
        ]);
    }

    public function history()
    {
        $history = $this->newsService->GetHistory();
        if ($history === false || $history === null) $history = [];

        return view('setting.news.history')->with([
            'history_list' => $history,
        ]);
    }
}

