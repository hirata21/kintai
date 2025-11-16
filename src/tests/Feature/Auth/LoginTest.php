<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN_ROUTE = 'login';
    private const ADMIN_LOGIN_ROUTE = 'admin.login'; // 実プロジェクトのルート名に合わせて

    /** @test */
    public function 一般_メール未入力でエラー()
    {
        $res = $this->post(route(self::LOGIN_ROUTE), [
            'email' => '',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors('email');
        $this->assertStringContainsString('メールアドレスを入力してください', session('errors')->first('email'));
    }

    /** @test */
    public function 一般_パスワード未入力でエラー()
    {
        $res = $this->post(route(self::LOGIN_ROUTE), [
            'email' => 'a@example.com',
            'password' => '',
        ]);

        $res->assertSessionHasErrors('password');
        $this->assertStringContainsString('パスワードを入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function 一般_未登録情報でエラー()
    {
        $res = $this->post(route(self::LOGIN_ROUTE), [
            'email' => 'unknown@example.com',
            'password' => 'password123',
        ]);

        // 一般的には email または password でエラーを返す
        $res->assertSessionHasErrors();
        $this->assertStringContainsString('ログイン情報が登録されていません', collect(session('errors')->all())->join(' '));
    }

    /** @test */
    public function 管理_メール未入力でエラー()
    {
        $res = $this->post(route(self::ADMIN_LOGIN_ROUTE), [
            'email' => '',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors('email');
        $this->assertStringContainsString('メールアドレスを入力してください', session('errors')->first('email'));
    }

    /** @test */
    public function 管理_パスワード未入力でエラー()
    {
        $res = $this->post(route(self::ADMIN_LOGIN_ROUTE), [
            'email' => 'admin@example.com',
            'password' => '',
        ]);

        $res->assertSessionHasErrors('password');
        $this->assertStringContainsString('パスワードを入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function 管理_未登録情報でエラー()
    {
        $res = $this->post(route(self::ADMIN_LOGIN_ROUTE), [
            'email' => 'noadmin@example.com',
            'password' => 'password123',
        ]);

        $res->assertSessionHasErrors();
        $this->assertStringContainsString('ログイン情報が登録されていません', collect(session('errors')->all())->join(' '));
    }
}
