<?php

declare(strict_types=1);

namespace Tests\Feature\Timesheet;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class IndexNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一般_一覧_当月表示と前月翌月リンクで遷移できる(): void
    {
        // ロケール/タイムゾーン固定 & 現在時刻固定
        config(['app.locale' => 'ja', 'app.timezone' => 'Asia/Tokyo']);
        app()->setLocale('ja');
        Carbon::setLocale('ja');
        Carbon::setTestNow('2025-10-15 10:00:00');

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 当月・前月・翌月にダミー
        Attendance::factory()->create(['user_id' => $user->id, 'work_date' => '2025-10-10']);
        Attendance::factory()->create(['user_id' => $user->id, 'work_date' => '2025-09-10']);
        Attendance::factory()->create(['user_id' => $user->id, 'work_date' => '2025-11-10']);

        // UIは "YYYY/MM" 表示 & <time datetime="YYYY-MM">
        $this->get(route('timesheet.index'))
            ->assertOk()
            ->assertSee('datetime="2025-10"', false)
            ->assertSee('2025/10');

        // 前月に遷移 → "2025/09" と datetime="2025-09"
        $this->get(route('timesheet.index', ['month' => '2025-09']))
            ->assertOk()
            ->assertSee('datetime="2025-09"', false)
            ->assertSee('2025/09');

        // 翌月に遷移 → "2025/11" と datetime="2025-11"
        $this->get(route('timesheet.index', ['month' => '2025-11']))
            ->assertOk()
            ->assertSee('datetime="2025-11"', false)
            ->assertSee('2025/11');
    }

    /** @test */
    public function 一般_一覧_詳細リンクで詳細画面へ(): void
    {
        config(['app.locale' => 'ja', 'app.timezone' => 'Asia/Tokyo']);
        app()->setLocale('ja');
        Carbon::setLocale('ja');

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        /** @var \App\Models\Attendance $att */
        $att = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-05',
        ]);

        $res = $this->get(route('timesheet.index', ['month' => '2025-10']))->assertOk();
        $this->assertStringContainsString('詳細', $res->getContent());

        $this->get(route('timesheet.show', $att))->assertOk();
    }

    public function 一覧に休憩合計が表示される(): void
    {
        config(['app.locale' => 'ja', 'app.timezone' => 'Asia/Tokyo']);
        app()->setLocale('ja');
        Carbon::setTestNow('2025-10-15 10:00:00');

        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2025-10-10 の勤怠（09:00-18:00、休憩合計 0:30）
        $att = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-10',
            'start_at'  => '2025-10-10 09:00:00',
            'end_at'    => '2025-10-10 18:00:00',
            'status'    => 'clocked_out',
        ]);

        $att->breaks()->create([
            'start_at' => '2025-10-10 12:00:00',
            'end_at'   => '2025-10-10 12:30:00',
        ]);

        // 一覧（当月）。UIは「休憩」列に "0:30" のような H:MM を表示
        $res  = $this->get(route('timesheet.index'))->assertOk();
        $html = $res->getContent();

        // 対象日の行（10/10(金)）があり、「休憩」列に 0:30 が出る想定
        $this->assertStringContainsString('datetime="2025-10-10"', $html); // <time datetime="2025-10-10">10/10(金)
        $this->assertStringContainsString('0:30', $html);
    }
}