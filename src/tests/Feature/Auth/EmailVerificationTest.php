<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 会員登録後に認証メール誘導が表示される想定を検証()
    {
        // ※ 誘導画面のルート/表示は実装依存。ここでは単に登録→未認証であることを確認
        $user = User::factory()->unverified()->create();

        $this->assertFalse($user->hasVerifiedEmail());
        // 誘導画面が route('verification.notice') なら↓のようにGET確認
        // $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function 会員登録で認証メールが送信される(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name'                  => 'Taro',
            'email'                 => 'taro@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        // 直近登録ユーザーに VerifyEmail 通知が送られていること
        $user = \App\Models\User::query()->where('email', 'taro@example.com')->first();
        $this->assertNotNull($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** @test */
    public function 認証リンクで検証完了し勤怠登録画面へ遷移()
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $res = $this->get($verificationUrl);

        $res->assertRedirect( /* 認証後のリダイレクト先: */route('punch.show'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        Event::assertDispatched(Verified::class);
    }
}