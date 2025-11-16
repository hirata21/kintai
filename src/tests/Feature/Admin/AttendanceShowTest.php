<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;

class AttendanceShowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理_勤怠詳細_表示値が正しい(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'web');

        /** @var \App\Models\User $user */
        $user = User::factory()->create(['name' => '山田花子']);

        /** @var \App\Models\Attendance $att */
        $att = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-12',
            'start_at'  => '2025-10-12 09:00:00',
            'end_at'    => '2025-10-12 18:00:00',
            'status'    => 'clocked_out',
            'note'      => '管理用メモ',
        ]);

        $response = $this->get(route('admin.attendances.show', $att))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('山田花子', $html);
        $this->assertStringContainsString('09:00', $html);
        $this->assertStringContainsString('18:00', $html);
        $this->assertStringContainsString('管理用メモ', $html);
    }
}
