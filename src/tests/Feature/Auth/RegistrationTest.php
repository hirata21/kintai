<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // ルート名をプロジェクトに合わせて調整
    private const REGISTER_ROUTE = 'register';

    /** @test */
    public function 名前未入力でエラー文言が出る()
    {
        $res = $this->post(route(self::REGISTER_ROUTE), [
            'name' => '',
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertSessionHasErrors('name');

        // カスタムメッセージを厳密に確認したい場合は↓
        $msg = session('errors')->first('name');
        $this->assertStringContainsString('お名前を入力してください', $msg);
    }

    /** @test */
    public function メール未入力でエラー()
    {
        $res = $this->post(route(self::REGISTER_ROUTE), [
            'name' => '山田太郎',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertSessionHasErrors('email');
        $this->assertStringContainsString('メールアドレスを入力してください', session('errors')->first('email'));
    }

    /** @test */
    public function パスワード8未満でエラー()
    {
        $res = $this->post(route(self::REGISTER_ROUTE), [
            'name' => '山田太郎',
            'email' => 'a@example.com',
            'password' => '1234567',
            'password_confirmation' => '1234567',
        ]);

        $res->assertSessionHasErrors('password');
        $this->assertStringContainsString('パスワードは8文字以上で入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function パスワード不一致でエラー()
    {
        $res = $this->post(route(self::REGISTER_ROUTE), [
            'name' => '山田太郎',
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $res->assertSessionHasErrors('password');
        $this->assertStringContainsString('パスワードと一致しません', session('errors')->first('password'));
    }

    /** @test */
    public function パスワード未入力でエラー()
    {
        $res = $this->post(route(self::REGISTER_ROUTE), [
            'name' => '山田太郎',
            'email' => 'a@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $res->assertSessionHasErrors('password');
        $this->assertStringContainsString('パスワードを入力してください', session('errors')->first('password'));
    }

    /** @test */
    public function 入力が正しければDBに保存される()
    {
        $res = $this->post(route(self::REGISTER_ROUTE), [
            'name' => '山田太郎',
            'email' => 'a@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $res->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'a@example.com',
            'name'  => '山田太郎',
        ]);

        $user = User::where('email', 'a@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
    }
}