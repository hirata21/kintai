<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceUpdateRequest;
use App\Models\Attendance;
use App\Models\User;
use App\Models\TimesheetRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * 管理：勤怠一覧（日付別）
     */
    public function index(Request $request)
    {
        // 表示対象日を決定
        $dateStr = $request->query('date', now()->toDateString());
        $date    = $this->parseDateOrToday($dateStr);

        $prev = $date->copy()->subDay()->toDateString();
        $next = $date->copy()->addDay()->toDateString();

        // 対象日の勤怠（※休憩も同時ロード）を user_id キーで取得
        $attendancesByUser = Attendance::with(['breaks' => fn($q) => $q->orderBy('start_at')])
            ->whereDate('work_date', $date->toDateString())
            ->get()
            ->keyBy('user_id');

        // 一般ユーザーのみ（role名は環境に合わせること）
        $users = User::where('role', 'user')->orderBy('name')->get();

        // 一覧表示用の行を作成（休憩合計はbreaksから計算）
        $rows = $users->map(function (User $user) use ($attendancesByUser) {
            $attendance = $attendancesByUser->get($user->id);

            if ($attendance) {
                $in  = $attendance->start_at ? Carbon::parse($attendance->start_at)->format('H:i') : '';
                $out = $attendance->end_at   ? Carbon::parse($attendance->end_at)->format('H:i')   : '';

                // 休憩合計（分）をリレーションから集計
                $breakMinutes = $attendance->breaks
                    ? $attendance->breaks->sum(function ($br) {
                        try {
                            $s = Carbon::parse($br->start_at);
                            $e = Carbon::parse($br->end_at);
                            return max(0, $s->diffInMinutes($e));
                        } catch (\Throwable $e) {
                            return 0;
                        }
                    })
                    : 0;

                // 実働（分）= 勤務合計 − 休憩合計（負にならない）
                $workMinutes = 0;
                if ($attendance->start_at && $attendance->end_at) {
                    $start = Carbon::parse($attendance->start_at);
                    $end   = Carbon::parse($attendance->end_at);
                    $workMinutes = max(0, $start->diffInMinutes($end) - $breakMinutes);
                }

                return (object) [
                    'user_id'       => $user->id,
                    'attendance_id' => $attendance->id,
                    'user_name'     => $user->name,
                    'in'            => $in,
                    'out'           => $out,
                    'break'         => $breakMinutes,
                    'work'          => $workMinutes,
                    'status'        => $attendance->status ?? '—',
                ];
            }

            // レコードがない場合
            return (object) [
                'user_id'       => $user->id,
                'attendance_id' => null,
                'user_name'     => $user->name,
                'in'            => '',
                'out'           => '',
                'break'         => 0,
                'work'          => 0,
                'status'        => '',
            ];
        });

        return view('admin.attendances.index', compact('date', 'prev', 'next', 'rows'));
    }

    /**
     * 管理：勤怠詳細表示
     */
    public function show(Attendance $attendance)
    {
        $attendance->load([
            'breaks' => fn($q) => $q->orderBy('start_at'),
            'user',
        ]);

        // この勤怠に承認待ちがあるかどうか
        $isPendingApproval = TimesheetRequest::where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        return view('admin.attendances.show', [
            'attendance'        => $attendance,
            'isPendingApproval' => $isPendingApproval,
            'isExisting'        => true,
        ]);
    }

    /**
     * 管理：勤怠の新規作成
     */
    public function store(AttendanceUpdateRequest $request)
    {
        $userId   = $request->input('user_id');
        $workDate = $request->input('work_date') ?: now()->toDateString();

        if (empty($userId)) {
            return back()->withErrors([
                'user_id' => 'ユーザーを特定できませんでした。もう一度やり直してください。',
            ]);
        }

        $createdAttendanceId = null;

        DB::transaction(function () use ($request, $userId, $workDate, &$createdAttendanceId) {
            // 1) 勤怠本体を作成
            $attendance = new Attendance();
            $attendance->user_id   = $userId;
            $attendance->work_date = $workDate;

            $attendance->start_at      = $this->toDateTimeFromHm($request->input('start_at'), $workDate);
            $attendance->end_at        = $this->toDateTimeFromHm($request->input('end_at'), $workDate);
            $attendance->note          = $request->input('note');
            $attendance->break_minutes = 0; // 後で計算するので一旦0
            $attendance->save();

            // 2) 休憩を作成
            foreach ((array) $request->input('breaks', []) as $row) {
                $start = $this->toDateTimeFromHm($row['start'] ?? null, $workDate);
                $end   = $this->toDateTimeFromHm($row['end'] ?? null, $workDate);

                if ($start && $end) {
                    $attendance->breaks()->create([
                        'start_at' => $start,
                        'end_at'   => $end,
                    ]);
                }
            }

            // 3) 休憩合計を再計算
            $attendance->break_minutes = $this->calcBreakMinutes($attendance);
            $attendance->save();

            $createdAttendanceId = $attendance->id;
        });

        return redirect()
            ->route('admin.attendances.show', ['attendance' => $createdAttendanceId])
            ->with('success', '勤怠を新規作成しました。');
    }

    /**
     * 管理：勤怠の更新
     */
    public function update(AttendanceUpdateRequest $request, Attendance $attendance)
    {
        // この勤怠の日付（時刻をくっつけるベース）
        $workDate = optional($attendance->work_date)?->toDateString()
            ?? Carbon::parse($attendance->created_at)->toDateString();

        DB::transaction(function () use ($request, $attendance, $workDate) {
            // 1) 勤怠本体
            $attendance->start_at = $this->toDateTimeFromHm($request->input('start_at'), $workDate);
            $attendance->end_at   = $this->toDateTimeFromHm($request->input('end_at'), $workDate);
            $attendance->note     = $request->input('note');
            $attendance->save();

            // 2) 休憩 全削除→再登録
            $attendance->breaks()->delete();

            foreach ((array) $request->input('breaks', []) as $row) {
                $start = $this->toDateTimeFromHm($row['start'] ?? null, $workDate);
                $end   = $this->toDateTimeFromHm($row['end'] ?? null, $workDate);

                if ($start && $end) {
                    $attendance->breaks()->create([
                        'start_at' => $start,
                        'end_at'   => $end,
                    ]);
                }
            }

            // 3) 休憩合計を再計算
            $attendance->break_minutes = $this->calcBreakMinutes($attendance);
            $attendance->save();
        });

        return redirect()
            ->route('admin.attendances.show', ['attendance' => $attendance->id])
            ->with('success', '勤怠を更新しました。');
    }

    /**
     * ユーザーと日付で開く詳細（未登録でも表示）
     */
    public function showByUserAndDate(User $user, string $date)
    {
        $workDate = $this->parseDateOrToday($date)->toDateString();

        $attendance = Attendance::with(['breaks' => fn($q) => $q->orderBy('start_at'), 'user'])
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $attendance) {
            // ダミーをつくってBladeに渡す
            $attendance = new Attendance([
                'user_id'   => $user->id,
                'work_date' => $workDate,
            ]);
            $attendance->setRelation('breaks', collect());
            $attendance->setRelation('user', $user);

            return view('admin.attendances.show', [
                'attendance'        => $attendance,
                'isPendingApproval' => false,
                'isExisting'        => false,
            ]);
        }

        // 既存レコードがある場合は承認待ちも調べる
        $isPendingApproval = TimesheetRequest::where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        return view('admin.attendances.show', [
            'attendance'        => $attendance,
            'isPendingApproval' => $isPendingApproval,
            'isExisting'        => true,
        ]);
    }

    /**
     * "YYYY-mm-dd" をパースして失敗したら今日を返す
     */
    private function parseDateOrToday(string $dateStr): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $dateStr)->startOfDay();
        } catch (\Throwable $e) {
            return now()->startOfDay();
        }
    }

    /**
     * "HH:MM" を日付付きの DateTime にする
     */
    private function toDateTimeFromHm(?string $hm, string $baseDate): ?Carbon
    {
        if (! $hm) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i', $baseDate . ' ' . $hm);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 休憩テーブルから合計分数を計算する
     */
    private function calcBreakMinutes(Attendance $attendance): int
    {
        $attendance->loadMissing('breaks');

        return $attendance->breaks->sum(function ($break) {
            try {
                $start = Carbon::parse($break->start_at);
                $end   = Carbon::parse($break->end_at);
                return max(0, $start->diffInMinutes($end));
            } catch (\Throwable $e) {
                return 0;
            }
        });
    }
}