<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NakatsukaAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->session()->has('login_user_id')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}

