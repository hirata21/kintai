<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * ログイン成功時のリダイレクト先を制御
     */
    public function toResponse($request)
    {
        $user = $request->user();

        // 念のためのフォールバック（万が一 $user が取れない場合でも落とさない）
        if (!$user) {
            return redirect()->intended(route('punch.show'));
        }

        // roleカラム ('admin' / 'user') によって振り分け
        $targetRouteName = ($user->role === 'admin')
            ? 'admin.attendances.index' // 管理トップ（例）
            : 'punch.show';             // 一般ユーザーの打刻/ホーム画面（例）

        // intended(): 認証前にアクセスしようとした保護ページがあればそっちを優先
        return redirect()->intended(route($targetRouteName));
    }
}