<?php

namespace App\Http\Controllers;

use App\Http\Requests\admin\AdminLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * 新規登録フォーム表示
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * 新規ユーザー登録
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            // users.role はenumで必須なので初期値を入れておく
            'role'     => 'user',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    /**
     * ログインフォーム（一般ユーザー）
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * 一般ユーザー ログイン処理（webガード1本運用）
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('punch.show'));
        }

        return redirect()
            ->route('login.show')
            ->withErrors(['email' => 'ログイン情報が登録されていません'])
            ->onlyInput('email');
    }

    /**
     * 管理者ログイン処理（webガードで入ってroleで判定する方式）
     */
    public function loginAdmin(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $error = ['email' => 'ログイン情報が登録されていません'];

        // webガードでログイン
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // ロールがadminでなければ即ログアウト
            if (Auth::user()?->role !== 'admin') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('admin.login')
                    ->withErrors($error)
                    ->onlyInput('email');
            }

            // adminとしてOK
            // ユーザー側の intended が残っていると変なところに飛ぶので消しておく
            $request->session()->forget('url.intended');

            return redirect()->route('admin.attendances.index');
        }

        // 認証失敗
        return redirect()
            ->route('admin.login')
            ->withErrors($error)
            ->onlyInput('email');
    }
}