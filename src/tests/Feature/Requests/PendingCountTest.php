<?php

declare(strict_types=1);

namespace Tests\Feature\Requests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimesheetRequest;

class PendingCountTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 申請一覧_承認待ちタブに自分の申請が全て出る(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'web');

        /** @var \App\Models\User $user */
        $user = User::factory()->create(['name' => '佐藤太郎']);

        /** @var \App\Models\Attendance $att */
        $att = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-20',
            'start_at'  => '2025-10-20 09:00:00',
            'end_at'    => '2025-10-20 18:00:00',
            'status'    => 'clocked_out',
            'note'      => null,
        ]);

        // 自分（= 佐藤太郎）の pending を2件
        TimesheetRequest::create([
            'user_id'         => $user->id,
            'attendance_id'   => $att->id,
            'status'          => 'pending',
            'payload_before'  => [],
            'payload_current' => ['note' => 'A'],
        ]);
        TimesheetRequest::create([
            'user_id'         => $user->id,
            'attendance_id'   => $att->id,
            'status'          => 'pending',
            'payload_before'  => [],
            'payload_current' => ['note' => 'B'],
        ]);

        // 他ユーザーの pending（混在させる）
        /** @var \App\Models\User $other */
        $other = User::factory()->create(['name' => '他ユーザー']);
        /** @var \App\Models\Attendance $att2 */
        $att2 = Attendance::factory()->create([
            'user_id'   => $other->id,
            'work_date' => '2025-10-21',
            'start_at'  => null,
            'end_at'    => null,
            'status'    => 'off',
            'note'      => null,
        ]);
        TimesheetRequest::create([
            'user_id'         => $other->id,
            'attendance_id'   => $att2->id,
            'status'          => 'pending',
            'payload_before'  => [],
            'payload_current' => ['note' => 'X'],
        ]);

        // 管理の申請一覧（承認待ちタブ）
        $response = $this->get(route('requests.index', ['tab' => 'pending']))
            ->assertOk();

        $html = $response->getContent();

        // 「佐藤太郎」が2回出る（= 自分の申請2件）
        $this->assertSame(2, preg_match_all('/佐藤太郎/u', $html, $m));

        // 行が描画されている簡易チェック（文言はUIに合わせて調整可）
        $this->assertGreaterThanOrEqual(1, substr_count($html, '詳細'));
    }
}
