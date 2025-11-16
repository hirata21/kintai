<?php

declare(strict_types=1);

namespace Tests\Feature\Timesheet;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class IndexOwnAllTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 一般_一覧_自分の月内勤怠が全件表示され他人分は混入しない(): void
    {
        Carbon::setTestNow('2025-10-05 10:00:00');

        /** @var \App\Models\User $me */
        $me = User::factory()->create();
        $this->actingAs($me);

        // 自分のレコード3件（出退勤つき）
        Attendance::factory()->create([
            'user_id' => $me->id,
            'work_date' => '2025-10-01',
            'start_at' => '2025-10-01 09:00:00',
            'end_at' => '2025-10-01 18:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $me->id,
            'work_date' => '2025-10-02',
            'start_at' => '2025-10-02 10:00:00',
            'end_at' => '2025-10-02 19:00:00',
        ]);
        Attendance::factory()->create([
            'user_id' => $me->id,
            'work_date' => '2025-10-03',
            'start_at' => '2025-10-03 08:30:00',
            'end_at' => '2025-10-03 17:30:00',
        ]);

        // 他人分（同じ月）
        $other = User::factory()->create();
        Attendance::factory()->create([
            'user_id' => $other->id,
            'work_date' => '2025-10-02',
            'start_at' => '2025-10-02 07:00:00',
            'end_at' => '2025-10-02 15:00:00',
        ]);

        $html = $this->get(route('timesheet.index', ['month' => '2025-10']))
            ->assertOk()->getContent();

        // 自分の3日分の出退勤時刻が載っている
        $this->assertStringContainsString('09:00', $html);
        $this->assertStringContainsString('18:00', $html);
        $this->assertStringContainsString('10:00', $html);
        $this->assertStringContainsString('19:00', $html);
        $this->assertStringContainsString('08:30', $html);
        $this->assertStringContainsString('17:30', $html);

        // 他人分の 07:00/15:00 は載らない（混入しない）
        $this->assertStringNotContainsString('07:00', $html);
        $this->assertStringNotContainsString('15:00', $html);
    }
}
