<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;

class CorrectionValidationTest extends TestCase
{
    use RefreshDatabase;

    private const STORE = 'requests.store'; // 申請POSTルート

    /**
     * ログインユーザーと勤怠1件を用意して返す
     * 
     * @return array{0:\App\Models\User,1:\App\Models\Attendance}
     */
    private function actingUserWithAttendance(): array
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->createOne();   // 単一モデルを明確化
        $this->actingAs($user);                 // Authenticatable 型でOK

        /** @var \App\Models\Attendance $attendance */
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-03',
            'start_at'  => '2025-10-03 09:00:00',
            'end_at'    => '2025-10-03 18:00:00',
            'status'    => 'clocked_out',
            'note'      => null,
        ]);

        return [$user, $attendance];
    }

    /** @test */
    public function 出勤が退勤より後はエラー(): void
    {
        [, $att] = $this->actingUserWithAttendance();

        $res = $this->from(route('timesheet.show', $att))
            ->post(route(self::STORE), [
                'attendance_id' => $att->id,
                'start_at'      => '19:00',
                'end_at'        => '18:00',
                'breaks'        => [],
                'note'          => 'x',
            ]);

        $res->assertSessionHasErrors(['start_at']);
    }

    /** @test */
    public function 休憩開始が退勤より後はエラー(): void
    {
        [, $att] = $this->actingUserWithAttendance();

        $res = $this->post(route(self::STORE), [
            'attendance_id' => $att->id,
            'start_at'      => '09:00',
            'end_at'        => '18:00',
            'breaks'        => [
                ['start_at' => '19:00', 'end_at' => '19:30'],
            ],
            'note'          => 'x',
        ]);

        $res->assertSessionHasErrors(['breaks.0.start_at']);
    }

    /** @test */
    public function 休憩終了が退勤より後はエラー(): void
    {
        [, $att] = $this->actingUserWithAttendance();

        $res = $this->post(route(self::STORE), [
            'attendance_id' => $att->id,
            'start_at'      => '09:00',
            'end_at'        => '18:00',
            'breaks'        => [
                ['start_at' => '17:30', 'end_at' => '19:00'],
            ],
            'note'          => 'x',
        ]);

        $res->assertSessionHasErrors(['breaks.0.end_at']);
    }

    /** @test */
    public function 備考未入力はエラー(): void
    {
        [, $att] = $this->actingUserWithAttendance();

        $res = $this->post(route(self::STORE), [
            'attendance_id' => $att->id,
            'start_at'      => '09:00',
            'end_at'        => '18:00',
            'breaks'        => [],
            'note'          => '', //空で送る
        ]);

        $res->assertSessionHasErrors(['note']);
    }
}