<?php

namespace Tests\Feature\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Carbon\Carbon;

class NowFormatTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 打刻画面に現在日時がUIと同形式で表示される(): void
    {
        $this->withoutExceptionHandling();

        // ロケール/タイムゾーンを明示（曜日の(水)などを安定させる）
        config(['app.locale' => 'ja', 'app.timezone' => 'Asia/Tokyo']);
        app()->setLocale('ja');
        Carbon::setLocale('ja');
        Carbon::setTestNow('2025-10-01 09:34:00'); // 2025-10-01 は水曜

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // ルート名は実装に合わせて（例：attendance.punch.form）
        $res = $this->get(route('punch.show'));
        $res->assertOk();

        // 期待表示（UIの書式に合わせる：Y年n月j日(D) と H:i）
        $expectedDate = Carbon::now()->locale('ja')->translatedFormat('Y年n月j日(D)');
        $expectedTime = Carbon::now()->format('H:i');

        $res->assertSee($expectedDate);
        $res->assertSee($expectedTime);
    }
}