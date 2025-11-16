<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use App\Actions\Fortify\RegisterResponse as CustomRegisterResponse;
use App\Actions\Fortify\LoginResponse as CustomLoginResponse;
use App\Actions\Fortify\LogoutResponse as CustomLogoutResponse;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Illuminate\Validation\ValidationException;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegisterResponse::class, CustomRegisterResponse::class);
        $this->app->singleton(LoginResponse::class,    CustomLoginResponse::class);
        $this->app->singleton(LogoutResponse::class,   CustomLogoutResponse::class);
    }

    public function boot(): void
    {
        // ユーザー作成処理は既存クラスを使用
        Fortify::createUsersUsing(CreateNewUser::class);

        // ビュー設定（既存）
        Fortify::registerView(fn() => view('auth.register'));
        Fortify::loginView(fn() => view('auth.login'));

        // Fortify の LoginRequest を自分の FormRequest に差し替え
        app()->bind(FortifyLoginRequest::class, LoginRequest::class);

        /**
         * Fortify の認証処理をカスタマイズ
         *
         * - 管理者ログインは /admin/login (またはルート名 login.admin) から来ることを許可
         * - 一般ログイン画面から管理者アカウントでのログインを拒否し、分かりやすいエラーメッセージを返す
         *
         * ※ 管理者判定は `role === 'admin'` を例としているので、
         *    実際のカラム名（is_admin など）に合わせて変更してください。
         */
        Fortify::authenticateUsing(function (Request $request) {
            // --- admin 用のログイン処理（バリデーションを専用 FormRequest で行う） ---
            // 管理者ログインPOSTは routes/web.php で 'login.admin' としている想定。
            // 確実に判定するために routeIs と URI パターンの両方をチェックします。
            $isAdminLoginRoute = $request->routeIs('login.admin')
                || $request->is('admin/login')
                || $request->is('admin/*/login');

            if ($isAdminLoginRoute) {
                // 管理者用 FormRequest でバリデーション（例: ログイン時に追加チェックがあれば）
                $form = app(AdminLoginRequest::class);
                $form->setContainer(app())->setRedirector(app('redirect'));
                $form->validateResolved();

                // 認証処理（メールでユーザー取得 → パスワードチェック）
                $user = User::where('email', $request->input('email'))->first();
                if ($user && Hash::check($request->input('password'), $user->password)) {
                    // 成功したらそのままユーザーオブジェクトを返し Fortify に認証を任せる
                    return $user;
                }

                // 見つからない or パスワード不一致
                return null;
            }

            // --- 通常（一般）ログイン処理 ---
            // Fortify 用の LoginRequest (あなたの LoginRequest にバインド済み) を利用してバリデーション
            $form = FortifyLoginRequest::createFrom($request);
            $form->setContainer(app())->setRedirector(app('redirect'));
            $form->validateResolved();

            // ユーザーを取得してパスワード検証
            $user = User::where('email', $request->input('email'))->first();

            // パスワード自体が一致しなければ失敗（null を返す）
            if (! $user || ! Hash::check($request->input('password'), $user->password)) {
                return null;
            }

            // 管理者かどうかの判定ロジック
            // ここでは role カラムが 'admin' の場合を管理者とみなす（必要に応じて変更）
            $isAdminAccount = (isset($user->role) && $user->role === 'admin')
                // もし boolean フラグを使っているなら下記を使う:
                // || (isset($user->is_admin) && $user->is_admin)
            ;

            // 一般ログイン画面から管理者アカウントでのログインを拒否する場合
            if ($isAdminAccount) {
                // セキュリティポリシーに応じてメッセージを調整してください。
                throw ValidationException::withMessages([
                    'email' => ['ログイン情報が登録されていません'],
                ]);
            }

            // 管理者でなければ通常どおり認証成功としてユーザーを返す
            return $user;
        });

        // ログイン試行のレートリミット設定（既存ロジックを維持）
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(100)->by(
                    strtolower($request->input('email')) . '|' . $request->ip()
                ),
            ];
        });
    }
}
