<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WebRememberService
{
    private const TOKEN_TABLE = 't_web_remember_tokens';

    /**
     * セッションに login_user_id が無いとき、Remember Cookie から復元する。
     */
    public function attemptRestore(Request $request): void
    {
        if ($request->session()->has('login_user_id')) {
            return;
        }

        $data = $this->decodeCookie($request);
        if (! is_array($data) || ! isset($data['uid'], $data['tok'])) {
            return;
        }

        if (! is_numeric($data['uid']) || ! is_string($data['tok']) || $data['tok'] === '') {
            return;
        }

        $userId = (int) $data['uid'];
        $token = $data['tok'];

        if (($data['v'] ?? null) === 2 && isset($data['sel']) && is_string($data['sel'])) {
            $this->restoreFromDeviceToken($request, $userId, $data['sel'], $token);

            return;
        }

        // 旧形式との互換: 移行前に発行済みの Cookie は m_user.access_token_web で復元する。
        $this->restoreFromLegacyToken($request, $userId, $token);
    }

    /**
     * この端末用の Remember Token を発行する。
     *
     * @return array{v:int, uid:int, sel:string, tok:string}|null
     */
    public function issueDeviceToken(int $userId, ?string $userAgent = null): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // ファイル同期から migrate 完了までの短い時間は旧方式にフォールバックする。
        if (! $this->deviceTokenTableExists()) {
            return $this->issueLegacyToken($userId);
        }

        $selector = Str::random(40);
        $token = Str::random(64);
        $now = now();
        $expiresAt = $now->copy()->addMinutes((int) config('remember_web.lifetime_minutes', 43200));

        DB::transaction(function () use ($userId, $selector, $token, $userAgent, $now, $expiresAt): void {
            DB::table(self::TOKEN_TABLE)
                ->where('user_id', $userId)
                ->where('expires_at', '<=', $now)
                ->delete();

            DB::table(self::TOKEN_TABLE)->insert([
                'user_id' => $userId,
                'selector' => $selector,
                'token_hash' => hash('sha256', $token),
                'user_agent' => $userAgent !== null ? Str::limit($userAgent, 500, '') : null,
                'expires_at' => $expiresAt,
                'last_used_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return [
            'v' => 2,
            'uid' => $userId,
            'sel' => $selector,
            'tok' => $token,
        ];
    }

    /**
     * 現在のブラウザに対応する Remember Token だけを無効化する。
     */
    public function revokeCurrentDevice(Request $request, ?int $expectedUserId = null): void
    {
        $data = $this->decodeCookie($request);
        if (! is_array($data) || ! is_numeric($data['uid'] ?? null)) {
            return;
        }

        $userId = (int) $data['uid'];
        if ($userId <= 0 || ($expectedUserId !== null && $expectedUserId !== $userId)) {
            return;
        }

        if (($data['v'] ?? null) === 2 && isset($data['sel'], $data['tok'])
            && is_string($data['sel']) && is_string($data['tok']) && $this->deviceTokenTableExists()) {
            $row = DB::table(self::TOKEN_TABLE)
                ->where('user_id', $userId)
                ->where('selector', $data['sel'])
                ->first();

            if ($row && hash_equals((string) $row->token_hash, hash('sha256', $data['tok']))) {
                DB::table(self::TOKEN_TABLE)->where('id', $row->id)->delete();
            }

            return;
        }

        // 旧形式の Cookie は、DB上の値と一致する場合だけ旧トークンを無効化する。
        $legacyToken = $data['tok'] ?? null;
        if (! is_string($legacyToken) || $legacyToken === '') {
            return;
        }

        DB::table('m_user')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->where('access_token_web', $legacyToken)
            ->update([
                'access_token_web' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * パスワード変更等で、そのユーザーの全端末を無効化する。
     */
    public function invalidateWebToken(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            if ($this->deviceTokenTableExists()) {
                DB::table(self::TOKEN_TABLE)->where('user_id', $userId)->delete();
            }

            DB::table('m_user')
                ->where('id', $userId)
                ->whereNull('deleted_at')
                ->update([
                    'access_token_web' => null,
                    'updated_at' => now(),
                ]);
        });
    }

    public function cookieSecure(Request $request): bool
    {
        $configured = config('session.secure');
        if ($configured !== null && $configured !== '') {
            return (bool) filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return $request->secure();
    }

    private function restoreFromDeviceToken(Request $request, int $userId, string $selector, string $token): void
    {
        if ($selector === '' || $token === '' || ! $this->deviceTokenTableExists()) {
            return;
        }

        $row = DB::table(self::TOKEN_TABLE)
            ->where('user_id', $userId)
            ->where('selector', $selector)
            ->where('expires_at', '>', now())
            ->first();

        if (! $row || ! hash_equals((string) $row->token_hash, hash('sha256', $token))) {
            return;
        }

        $userExists = DB::table('m_user')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $userExists) {
            return;
        }

        DB::table(self::TOKEN_TABLE)
            ->where('id', $row->id)
            ->update([
                'last_used_at' => now(),
                'updated_at' => now(),
            ]);

        $this->restoreSession($request, $userId, $selector);
    }

    private function restoreFromLegacyToken(Request $request, int $userId, string $token): void
    {
        $user = DB::table('m_user')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if (! $user || empty($user->access_token_web)) {
            return;
        }

        if (! hash_equals((string) $user->access_token_web, $token)) {
            return;
        }

        $this->restoreSession($request, $userId, (string) $user->access_token_web);
    }

    private function restoreSession(Request $request, int $userId, string $sessionToken): void
    {
        $request->session()->regenerate();
        $request->session()->put('login_user_id', $userId);
        $request->session()->put(config('tokens.web_token'), $sessionToken);
        $request->session()->forget(config('tokens.app_token'));
    }

    /** @return array<string, mixed>|null */
    private function decodeCookie(Request $request): ?array
    {
        $payload = $request->cookie(config('remember_web.cookie'));
        if (! is_string($payload) || $payload === '') {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /** @return array{v:int, uid:int, sel:string, tok:string}|null */
    private function issueLegacyToken(int $userId): ?array
    {
        $token = Str::random(64);
        $updated = DB::table('m_user')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->update([
                'access_token_web' => $token,
                'updated_at' => now(),
            ]);

        if ($updated < 1) {
            return null;
        }

        return [
            'v' => 1,
            'uid' => $userId,
            'sel' => '',
            'tok' => $token,
        ];
    }

    private function deviceTokenTableExists(): bool
    {
        try {
            return Schema::hasTable(self::TOKEN_TABLE);
        } catch (\Throwable) {
            return false;
        }
    }
}
