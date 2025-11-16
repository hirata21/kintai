<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_DAILY       = 'admin.attendances.index';     // ?date=YYYY-MM-DD
    private const ADMIN_STAFF_MONTH = 'admin.staff.attendances';     // /admin/staff/{user}?month=YYYY-MM

    /** @test */
    public function 管理_日別一覧_当日表示と前日翌日遷移()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create(); // role=admin
        $this->actingAs($admin);

        Carbon::setTestNow('2025-10-10 09:00:00');

        $user = User::factory()->create();

        Attendance::create([
            'user_id'       => $user->id,
            'work_date'     => '2025-10-10',
            'start_at'      => '2025-10-10 09:00:00',
            'end_at'        => null,
            'break_minutes' => 0,
            'status'        => 'working', // enumの実値に合わせる
            'note'          => null,
        ]);

        $res = $this->get(route(self::ADMIN_DAILY, ['date' => '2025-10-10']));

        $res->assertOk()
            // 見出しの表記：2025年10月10日の勤怠
            ->assertSee('2025年10月10日の勤怠')
            // 中央の表記：2025/10/10
            ->assertSee('2025/10/10')
            // 前日/翌日のリンク（クエリはハイフン日付）
            ->assertSee('?date=2025-10-09')
            ->assertSee('?date=2025-10-11')
            // 行にユーザー名と「詳細」リンクが出ていること（任意強化）
            ->assertSee($user->name)
            ->assertSee('詳細');

        // 前日・翌日に遷移しても 200 を返すこと
        $this->get(route(self::ADMIN_DAILY, ['date' => '2025-10-09']))->assertOk();
        $this->get(route(self::ADMIN_DAILY, ['date' => '2025-10-11']))->assertOk();
    }

    /** @test */
    public function 管理_ユーザー別_月移動と詳細遷移リンク存在()
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->admin()->create();
        /** @var \App\Models\User $user */
        $user  = User::factory()->create();
        $this->actingAs($admin);

        Attendance::create([
            'user_id'       => $user->id,
            'work_date'     => '2025-10-03',
            'start_at'      => '2025-10-03 09:00:00',
            'end_at'        => null,
            'break_minutes' => 0,
            'status'        => 'working',
            'note'          => null,
        ]);

        $res = $this->get(route(self::ADMIN_STAFF_MONTH, [$user->id, 'month' => '2025-10']));

        $res->assertOk()
            // 画面の月表示が "2025/10" の可能性が高いので両方に耐える
            ->assertSee('2025/10')
            // 前月・翌月のリンク（もし画面に出しているなら）
            ->assertSee('month=2025-09')
            ->assertSee('month=2025-11')
            // 対象ユーザー名や「詳細」リンクなど
            ->assertSee($user->name)
            ->assertSee('詳細');
    }
}