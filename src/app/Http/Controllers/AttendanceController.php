<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function show()
    {
        $userId   = Auth::id();
        $todayStr = today()->toDateString();

        $attendance = Attendance::with([
            'breaks' => fn($q) => $q->orderBy('start_at'),
        ])->firstOrCreate(
            [
                'user_id'   => $userId,
                'work_date' => $todayStr,
            ],
            [
                'status' => 'off',
            ]
        );

        $isClockedIn  = (bool) ($attendance->start_at && ! $attendance->end_at);
        $isClockedOut = (bool) $attendance->end_at;

        $hasOpenBreak = $attendance->relationLoaded('breaks')
            ? $attendance->breaks->contains(fn($b) => is_null($b->end_at))
            : $attendance->breaks()->whereNull('end_at')->exists();

        return view('attendance.punch', [
            'attendance'   => $attendance,
            'isClockedIn'  => $isClockedIn,
            'isClockedOut' => $isClockedOut,
            'isOnBreak'    => $hasOpenBreak,
        ]);
    }

    public function clockIn()
    {
        $userId   = Auth::id();
        $todayStr = today()->toDateString();

        $attendance = Attendance::firstOrCreate(
            [
                'user_id'   => $userId,
                'work_date' => $todayStr,
            ],
            [
                'start_at' => now(),
                'status'   => 'working',
            ]
        );

        if ($attendance->start_at && $attendance->end_at) {
            return back()->with('status', 'すでに退勤済みです');
        }

        if (! $attendance->start_at) {
            $attendance->start_at = now();
            $attendance->status   = 'working';
            $attendance->save();
        }

        return back()->with('status', '出勤を記録しました');
    }

    public function clockOut()
    {
        $userId   = Auth::id();
        $todayStr = today()->toDateString();

        $attendance = Attendance::with('breaks')
            ->where('user_id', $userId)
            ->whereDate('work_date', $todayStr)
            ->first();

        if (! $attendance) {
            return back()->with('status', '本日の勤怠が見つかりませんでした');
        }

        if (! $attendance->start_at) {
            return back()->with('status', 'まだ出勤していません');
        }

        if ($attendance->end_at) {
            return back()->with('status', 'すでに退勤済みです');
        }

        $openBreak = $attendance->breaks
            ->whereNull('end_at')
            ->sortByDesc('start_at')
            ->first();

        if ($openBreak) {
            $openBreak->update(['end_at' => now()]);
            $attendance->load('breaks');
        }

        $attendance->end_at = now();
        $attendance->status = 'clocked_out';
        $attendance->save();

        $workMinutes  = $this->calcWorkMinutesFallback($attendance);
        $breakMinutes = $this->calcBreakMinutesFallback($attendance);
        
        $attendance->break_minutes = $breakMinutes;
        $attendance->save();

        return back()->with([
            'status'        => '退勤を記録しました',
            'work_minutes'  => $workMinutes,
            'break_minutes' => $breakMinutes,
        ]);
    }

    public function breakIn()
    {
        $userId   = Auth::id();
        $todayStr = today()->toDateString();

        $attendance = Attendance::where('user_id', $userId)
            ->whereDate('work_date', $todayStr)
            ->first();

        if (! $attendance || ! $attendance->start_at || $attendance->end_at) {
            return back()->with('status', '出勤中のみ休憩に入れます');
        }

        if ($attendance->breaks()->whereNull('end_at')->exists()) {
            return back()->with('status', 'すでに休憩中です');
        }

        $attendance->breaks()->create([
            'start_at' => now(),
        ]);

        $attendance->status = 'on_break';
        $attendance->save();

        return back()->with('status', '休憩を開始しました');
    }

    public function breakOut()
    {
        $userId = Auth::id();

        $openBreak = BreakTime::whereNull('end_at')
            ->whereHas('attendance', fn($q) => $q->where('user_id', $userId))
            ->orderByDesc('start_at')
            ->first();

        if (! $openBreak) {
            return back()->with('status', '開始中の休憩はありません');
        }

        $attendance = $openBreak->attendance()->with('breaks')->first();

        if (! $attendance) {
            return back()->with('status', '勤怠情報が見つかりませんでした');
        }

        if (! $attendance->start_at || $attendance->end_at) {
            return back()->with('status', '出勤中のみ休憩から戻れます');
        }

        $openBreak->end_at = now();
        $openBreak->save();

        $attendance->load('breaks');

        $breakMinutes = $this->calcBreakMinutesFallback($attendance);

        $attendance->status = 'working';
        $attendance->save();

        return back()->with([
            'status'        => '休憩を終了しました',
            'break_minutes' => $breakMinutes,
        ]);
    }

    private function calcBreakMinutesFallback(Attendance $attendance): int
    {
        $breaks = $attendance->relationLoaded('breaks')
            ? $attendance->breaks
            : $attendance->breaks()->get();

        $total = 0;

        foreach ($breaks as $break) {
            if ($break->start_at && $break->end_at) {
                $start = Carbon::parse($break->start_at);
                $end   = Carbon::parse($break->end_at);
                $total += $start->diffInMinutes($end);
            }
        }

        return $total;
    }

    private function calcWorkMinutesFallback(Attendance $attendance): int
    {
        if (! $attendance->start_at || ! $attendance->end_at) {
            return 0;
        }

        $start = Carbon::parse($attendance->start_at);
        $end   = Carbon::parse($attendance->end_at);

        $totalMinutes = $end->diffInMinutes($start);
        $breakMinutes = $this->calcBreakMinutesFallback($attendance);

        return max(0, $totalMinutes - $breakMinutes);
    }
}