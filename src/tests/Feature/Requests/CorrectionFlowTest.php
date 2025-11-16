<?php

namespace Tests\Feature\Requests;

use App\Models\User;
use App\Models\Attendance;
use App\Models\TimesheetRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class CorrectionFlowTest extends TestCase
{
    use RefreshDatabase;

    // web.php の name に合わせる（あなたのコメントに従って修正済み）
    private const DETAIL_UPDATE     = 'requests.store';
    private const ADMIN_REQ_INDEX   = 'admin.requests.index';
    private const ADMIN_REQ_SHOW    = 'admin.requests.approve.form';
    private const ADMIN_REQ_APPROVE = 'admin.requests.approve';

    /** @test */
    public function 申請作成で承認待ちに出て承認後は承認済みへ移る(): void
    {
        $this->withoutExceptionHandling();

        // 1) 下準備：ユーザー & 勤怠1件
        /** @var \App\Models\User $user */
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);

        Carbon::setTestNow('2025-10-01 09:00:00');

        /** @var \App\Models\Attendance $attendance */
        $attendance = Attendance::create([
            'user_id'       => $user->id,
            'work_date'     => '2025-10-01',
            'start_at'      => '2025-10-01 09:00:00',
            'end_at'        => null,
            'break_minutes' => 0,
            'status'        => 'working', // enum 実値に合わせる
            'note'          => null,
        ]);

        // 2) ユーザーが修正申請を送る（controller store() はフラットなキーを期待）
        $payload = [
            'attendance_id' => $attendance->id,
            'start_at'      => '09:15',
            'end_at'        => '18:05',
            'breaks'        => [
                ['start_at' => '12:00', 'end_at' => '12:30'],
            ],
            'note'          => '打刻ずれ修正',
        ];

        // /requests へ POST
        $res = $this->post(route(self::DETAIL_UPDATE), $payload);
        $res->assertRedirect();

        // 申請が pending で作成されている（テーブル名で直接確認）
        $this->assertDatabaseHas('requests', [
            'attendance_id' => $attendance->id,
            'user_id'       => $user->id,
            'status'        => 'pending',
        ]);

        /** @var \App\Models\TimesheetRequest $req */
        $req = TimesheetRequest::query()->first();
        $this->assertNotNull($req, 'TimesheetRequest が作成されていません');
        $this->assertSame('pending', $req->status);

        // 3) 管理者として承認フロー
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // 承認待ちタブに出ている
        $this->get(route(self::ADMIN_REQ_INDEX, ['tab' => 'pending']))
            ->assertOk()
            ->assertSee(e($user->name));

        // 承認画面詳細が開ける
        $this->get(route(self::ADMIN_REQ_SHOW, $req->id))
            ->assertOk()
            ->assertSee('勤怠詳細');

        // 承認実行（コントローラ実装の違いを吸収：200 or 302 を許容）
        $approveRes = $this->post(route(self::ADMIN_REQ_APPROVE, $req->id), [], ['Accept' => 'application/json']);
        $this->assertTrue(in_array($approveRes->getStatusCode(), [200, 302], true));

        // ステータスが approved に、勤怠が上書きされている
        $req->refresh();
        $this->assertSame('approved', $req->status);

        $attendance->refresh();
        $this->assertSame('2025-10-01 09:15:00', $attendance->start_at->toDateTimeString());
        $this->assertSame('2025-10-01 18:05:00', $attendance->end_at->toDateTimeString());

        // 承認済みタブに出る
        $this->get(route(self::ADMIN_REQ_INDEX, ['tab' => 'closed']))
            ->assertOk()
            ->assertSee(e($user->name));
    }
}