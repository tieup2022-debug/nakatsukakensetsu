<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TopSettingController extends Controller
{
    public function index(Request $request)
    {
        if (!session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        return view('top.setting')->with([
            'result' => null,
        ]);
    }
}

