<?php

declare(strict_types=1);

namespace Tests\Feature\Timesheet;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class ShowDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細_名前日付出退勤休憩が一致して表示される(): void
    {
        // ロケール/タイムゾーンを固定（曜日や日本語表記のブレ防止）
        config(['app.locale' => 'ja', 'app.timezone' => 'Asia/Tokyo']);
        app()->setLocale('ja');
        Carbon::setLocale('ja');

        /** @var \App\Models\User $user */
        $user = User::factory()->create(['name' => '山田太郎']);
        $this->actingAs($user);

        /** @var \App\Models\Attendance $attendance */
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'work_date' => '2025-10-02',
            'start_at'  => '2025-10-02 09:00:00',
            'end_at'    => '2025-10-02 18:00:00',
            'status'    => 'clocked_out',
            'note'      => 'メモ',
        ]);

        // 期待値（UIは「2025年」「10月2日」に分割して表示）
        $date = Carbon::parse('2025-10-02', 'Asia/Tokyo');
        $expectedYear  = $date->translatedFormat('Y年');     // 例: 2025年
        $expectedMD    = $date->translatedFormat('n月j日');  // 例: 10月2日
        $expectedIn    = '09:00';
        $expectedOut   = '18:00';

        $res = $this->get(route('timesheet.showByDate', '2025-10-02'));
        $res->assertOk();

        $html = $res->getContent();

        // 名前
        $this->assertStringContainsString('山田太郎', $html);

        // 日付（分割断言）
        $this->assertStringContainsString($expectedYear, $html);
        $this->assertStringContainsString($expectedMD,   $html);

        // 出退勤
        $this->assertStringContainsString($expectedIn,  $html);
        $this->assertStringContainsString($expectedOut, $html);
    }
}
