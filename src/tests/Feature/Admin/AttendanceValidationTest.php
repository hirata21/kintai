<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;

class AttendanceValidationTest extends TestCase
{
    use RefreshDatabase;

    private const UPDATE = 'admin.attendances.update';

    /**
     * 管理者ユーザーと対象勤怠を用意
     * @return array{0:\App\Models\User,1:\App\Models\Attendance}
     */
    private function adminAndAttendance(): array
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        /** @var \App\Models\User $user */
        $user  = User::factory()->create();

        /** @var \App\Models\Attendance $att */
        $att   = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-04',
            'start_at'  => '2025-10-04 09:00:00',
            'end_at'    => '2025-10-04 18:00:00',
            'status'    => 'clocked_out',
            'note'      => null,
        ]);

        return [$admin, $att];
    }

    /** @test */
    public function 管理_出勤が退勤より後はエラー(): void
    {
        [$admin, $att] = $this->adminAndAttendance();

        // 管理用ガードが別なら第二引数に 'admin' を指定してください
        $this->actingAs($admin);

        $res = $this->from(route('admin.attendances.show', $att))
            ->patch(
                route(self::UPDATE, $att),
                [
                    'start_at' => '19:00',
                    'end_at'   => '18:00',
                    'breaks'   => [],
                    'note'     => 'x',
                ],
                [
                    'Accept' => 'application/json',
                ]
            );

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['start_at']);
    }
}
