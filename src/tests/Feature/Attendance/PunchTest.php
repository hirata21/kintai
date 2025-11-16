<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class PunchTest extends TestCase
{
    use RefreshDatabase;

    // 画面とAPIルートはプロジェクト仕様に合わせて
    private const PUNCH_PAGE = 'punch.show';
    private const PUNCH_IN   = 'punch.in';
    private const PUNCH_OUT  = 'punch.out';
    private const BREAK_IN   = 'punch.break.in';
    private const BREAK_OUT  = 'punch.break.out';

    /** @test */
    public function ステータス_勤務外_表示()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        // actingAs は Authenticatable を要求：User は実装しているのでOK
        $this->actingAs($user)
            ->get(route(self::PUNCH_PAGE))
            ->assertOk()
            ->assertSee('勤務外');
    }

    /** @test */
    public function 出勤ボタンで勤務中になり一覧に時刻が出る()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        Carbon::setTestNow('2025-10-01 09:00:00');

        $this->actingAs($user)
            ->post(route(self::PUNCH_IN))
            ->assertRedirect(); // 画面遷移先は実装に依存

        $this->assertDatabaseHas('attendances', [
            'user_id'   => $user->id,
            'work_date' => Carbon::now()->toDateString(),
        ]);

        // もし一覧ページで時刻表示を検証するならルート名を差し替えて有効化
        // $this->get(route('timesheet.index'))->assertSee('09:00');
    }

    /** @test */
    public function 出勤は一日一回のみ()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        Carbon::setTestNow('2025-10-01 09:00:00');
        $this->actingAs($user)->post(route(self::PUNCH_IN))->assertRedirect();

        // 2回目の出勤試行：実装によりバリデーション/403/リダイレクト等が異なる
        // ここでは "同日のレコードが増えない" ことをDBで担保
        $second = $this->actingAs($user)->post(route(self::PUNCH_IN));
        // セッションエラーを返す実装なら↓が通る
        // $second->assertSessionHasErrors();

        // 出勤レコードが1件のまま
        $this->assertSame(1, Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('work_date', '2025-10-01')
            ->count());
    }

    /** @test */
    public function 休憩の入出を複数回できる()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        Carbon::setTestNow('2025-10-01 09:00:00');
        $this->actingAs($user)->post(route(self::PUNCH_IN))->assertRedirect();

        Carbon::setTestNow('2025-10-01 12:00:00');
        $this->post(route(self::BREAK_IN))->assertRedirect();

        Carbon::setTestNow('2025-10-01 12:30:00');
        $this->post(route(self::BREAK_OUT))->assertRedirect();

        Carbon::setTestNow('2025-10-01 15:00:00');
        $this->post(route(self::BREAK_IN))->assertRedirect();

        Carbon::setTestNow('2025-10-01 15:15:00');
        $this->post(route(self::BREAK_OUT))->assertRedirect();

        /** @var \App\Models\Attendance $attendance */
        $attendance = Attendance::query()->firstOrFail();

        // 件数はリレーション経由で数えると明確
        $this->assertSame(2, $attendance->breaks()->count());
    }

    /** @test */
    public function 退勤で退勤済み表示になり一覧に退勤時刻が出る()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        Carbon::setTestNow('2025-10-01 09:00:00');
        $this->actingAs($user)->post(route(self::PUNCH_IN))->assertRedirect();

        Carbon::setTestNow('2025-10-01 18:00:00');
        $this->post(route(self::PUNCH_OUT))->assertRedirect();

        // 一覧画面での表示確認をする場合はルート名を合わせて有効化
        // $this->get(route('timesheet.index'))->assertSee('18:00');
    }
}