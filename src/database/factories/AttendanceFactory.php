<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    /** @var class-string<\App\Models\Attendance> */
    protected $model = Attendance::class;

    public function definition(): array
    {
        $workDate = Carbon::instance($this->faker->dateTimeBetween('-1 month', '+1 month'))
            ->startOfDay();

        return [
            'user_id'       => User::factory(),   // 関連ユーザー自動生成
            'work_date'     => $workDate->toDateString(),
            'start_at'      => $workDate->copy()->setTime(9, 0),
            'end_at'        => $workDate->copy()->setTime(18, 0),
            'break_minutes' => 60,
            'status'        => 'clocked_out',     // enum: off / working / on_break / clocked_out
            'note'          => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }

    /** 状態: 勤務外 */
    public function off(): self
    {
        return $this->state(fn() => ['status' => 'off']);
    }

    /** 状態: 勤務中 */
    public function working(): self
    {
        return $this->state(fn() => [
            'status'   => 'working',
            'end_at'   => null,
        ]);
    }

    /** 状態: 休憩中 */
    public function onBreak(): self
    {
        return $this->state(fn() => [
            'status'        => 'on_break',
            'break_minutes' => 30,
        ]);
    }

    /** 状態: 退勤済み */
    public function clockedOut(): self
    {
        return $this->state(fn() => [
            'status'        => 'clocked_out',
            'break_minutes' => 60,
        ]);
    }
}
