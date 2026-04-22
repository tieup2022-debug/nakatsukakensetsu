<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class WebRememberService
{
    /**
     * セッションに login_user_id が無いとき、Remember Cookie から復元する。
     */
    public function attemptRestore(Request $request): void
    {
        if ($request->session()->has('login_user_id')) {
            return;
        }

        $name = config('remember_web.cookie');
        $payload = $request->cookie($name);
        if (! is_string($payload) || $payload === '') {
            return;
        }

        try {
            $data = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return;
        }

        if (! is_array($data) || ! isset($data['uid'], $data['tok'])) {
            return;
        }

        if (! is_numeric($data['uid']) || ! is_string($data['tok']) || $data['tok'] === '') {
            return;
        }

        $userId = (int) $data['uid'];
        $token = $data['tok'];

        $user = DB::table('m_user')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (! $user || empty($user->access_token_web)) {
            return;
        }

        if (! hash_equals($user->access_token_web, $token)) {
            return;
        }

        $request->session()->regenerate();
        $request->session()->put('login_user_id', $userId);
        $request->session()->put(config('tokens.web_token'), $user->access_token_web);
        $request->session()->forget(config('tokens.app_token'));
    }

    public function invalidateWebToken(int $userId): void
    {
        DB::table('m_user')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->update([
                'access_token_web' => null,
                'updated_at' => now(),
            ]);
    }

    public function cookieSecure(Request $request): bool
    {
        $configured = config('session.secure');
        if ($configured !== null && $configured !== '') {
            return (bool) filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return $request->secure();
    }
}
