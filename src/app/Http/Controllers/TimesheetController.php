<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\TimesheetRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TimesheetController extends Controller
{
    /**
     * 勤怠一覧（1か月分）
     */
    public function index(Request $request): View
    {
        // ?month=YYYY-MM
        $monthParam = (string) $request->query('month', '');
        if ($monthParam !== '' && !preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = '';
        }

        $month = $monthParam
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : now()->startOfMonth();

        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        // 対象月の勤怠＋休憩をまとめて取得（休憩は開始時刻順）
        $attendances = Attendance::where('user_id', Auth::id())
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->with(['breaks' => fn($q) => $q->orderBy('start_at')]) // eager load
            ->orderBy('work_date')
            ->get()
            ->keyBy(function ($a) {
                $d = $a->work_date instanceof Carbon ? $a->work_date : Carbon::parse($a->work_date);
                return $d->format('Y-m-d');
            });

        $rows = collect();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            /** @var Attendance|null $attendance */
            $attendance = $attendances->get($d->format('Y-m-d'));

            // 出退勤（start_at / end_at のみ） — 表示用は null または 'HH:MM'
            $in  = $attendance?->start_at ? Carbon::parse($attendance->start_at)->format('H:i') : null;
            $out = $attendance?->end_at   ? Carbon::parse($attendance->end_at)->format('H:i')   : null;

            // ---- 休憩合計の算出ルール（表示ルールを満たす）
            // 優先順位: 保存済み break_minutes (null でなければ) -> breaks リレーションから再計算 -> 勤務無しなら null
            $breakMin = null;
            if ($attendance && !empty($attendance->start_at)) {
                if ($attendance->break_minutes !== null) {
                    // 保存値があればそれを優先（DB に保存済み）
                    $breakMin = (int) $attendance->break_minutes;
                } else {
                    // breaks リレーションから再計算（eager loaded のため追加クエリを防ぐ）
                    $sum = 0;
                    foreach ($attendance->breaks as $b) {
                        if (empty($b->start_at)) {
                            continue;
                        }
                        $bStart = Carbon::parse($b->start_at);
                        $bEnd   = $b->end_at ? Carbon::parse($b->end_at) : now();
                        if ($bEnd->gt($bStart)) {
                            $sum += $bStart->diffInMinutes($bEnd);
                        }
                    }
                    $breakMin = (int) $sum;
                }
                // 注意: 勤務あり（start_at がある）なら 0（ゼロ分の休憩）も許容して表示する方針
            }

            // ---- 実働合計の算出ルール
            // 出勤ありなら (end or now) - start - break を算出して int を返す（出勤無しは null）
            $workMin = null;
            if ($attendance && !empty($attendance->start_at)) {
                $workEnd = $attendance->end_at ? Carbon::parse($attendance->end_at) : now();
                $totalMinutes = max(0, Carbon::parse($attendance->start_at)->diffInMinutes($workEnd));
                $calc = max(0, $totalMinutes - (int)($breakMin ?? 0));
                $workMin = (int) $calc;
            }

            $rows->push((object) [
                'date'   => $d->copy(), // Carbon（Bladeで日付表示に利用）
                'in'     => $in,
                'out'    => $out,
                'break'  => $breakMin,   // 勤務ありなら int（0含む）、勤務無しなら null
                'work'   => $workMin,    // 勤務ありなら int（0含む）、勤務無しなら null
                'attendance_id' => $attendance?->id ?? null,
                'status' => $attendance ? ($attendance->status ?? '勤務') : '—',
            ]);
        }

        return view('timesheet.index', [
            'ym'     => $start,
            'prevYm' => $start->copy()->subMonth()->format('Y-m'),
            'nextYm' => $start->copy()->addMonth()->format('Y-m'),
            'rows'   => $rows,
        ]);
    }

    /**
     * 日付指定で1件表示（/timesheet/YYYY-MM-DD）
     */
    public function showByDate(Request $request, string $date)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        $userId = Auth::id();

        $attendance = Attendance::where('user_id', $userId)
            ->whereDate('work_date', $date)
            ->with(['user', 'breaks' => fn($q) => $q->orderBy('start_at')])
            ->first();

        if (!$attendance) {
            // ダミー（Blade が user/breaks を参照できるように）
            $attendance = new Attendance([
                'user_id'   => $userId,
                'work_date' => $date,
            ]);
            $attendance->setRelation('user', Auth::user());
            $attendance->setRelation('breaks', collect());
        }

        // 申請IDがクエリにあれば、一時的にpayloadをあてる
        $requestId = $request->query('request_id');
        if ($requestId) {
            $this->applyRequestPayloadIfExists($attendance, $requestId, $userId);
        }

        // DBに実在する勤怠だけ pending を調べる
        $isPendingApproval = false;
        if ($attendance->exists) {
            $isPendingApproval = TimesheetRequest::where('attendance_id', $attendance->id)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->exists();
        }

        return view('timesheet.show', [
            'attendance'        => $attendance,
            'isPendingApproval' => $isPendingApproval,
        ]);
    }

    /**
     * ルートバインド版 show (/timesheet/{attendance})
     */
    public function show(Request $request, Attendance $attendance)
    {
        $userId = Auth::id();

        if ($attendance->user_id !== $userId) {
            abort(403);
        }

        $attendance->load([
            'user',
            'breaks' => fn($q) => $q->orderBy('start_at'),
        ]);

        // 申請IDがあったら適用
        $requestId = $request->query('request_id');
        if ($requestId) {
            $this->applyRequestPayloadIfExists($attendance, $requestId, $userId);
        }

        $isPendingApproval = TimesheetRequest::where('attendance_id', $attendance->id)
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();

        return view('timesheet.show', [
            'attendance'        => $attendance,
            'isPendingApproval' => $isPendingApproval,
        ]);
    }

    /**
     * 修正申請の保存
     */
    public function store(AttendanceCorrectionRequest $request)
    {
        $data   = $request->validated();
        $userId = $request->user()->id;

        // すでに自分の申請で pending があれば弾く
        $existsPending = TimesheetRequest::where('attendance_id', $data['attendance_id'])
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->exists();

        if ($existsPending) {
            return back()->withInput()->with('error', '承認待ちのため修正はできません。');
        }

        /** @var Attendance $attendance */
        $attendance = Attendance::with('breaks')->findOrFail($data['attendance_id']);

        // 申請前スナップショット（start_at / end_at のみ）
        $payloadBefore = [
            'start_at' => optional($attendance->start_at)?->format('Y-m-d H:i:s'),
            'end_at'   => optional($attendance->end_at)?->format('Y-m-d H:i:s'),
            'breaks'   => $attendance->breaks->map(fn($b) => [
                'start_at' => optional($b->start_at)?->format('Y-m-d H:i:s'),
                'end_at'   => optional($b->end_at)?->format('Y-m-d H:i:s'),
            ])->values()->all(),
            'note'     => $attendance->note,
        ];

        // 入力された休憩（空行は除外）
        $afterBreaks = collect($data['breaks'] ?? [])
            ->map(function ($row) {
                $s = trim($row['start_at'] ?? '');
                $e = trim($row['end_at'] ?? '');
                return ($s === '' && $e === '') ? null : ['start_at' => $s, 'end_at' => $e];
            })
            ->filter()
            ->values()
            ->all();

        // 申請後にこうしたい内容
        $payloadCurrent = [
            'start_at' => $data['start_at'] ?: null,
            'end_at'   => $data['end_at']   ?: null,
            'breaks'   => $afterBreaks,
            'note'     => $data['note'],
        ];

        TimesheetRequest::create([
            'user_id'         => $userId,
            'attendance_id'   => $attendance->id,
            'status'          => 'pending',
            'payload_before'  => $payloadBefore,
            'payload_current' => $payloadCurrent,
        ]);

        return redirect()
            ->route('timesheet.showByDate', $attendance->work_date->format('Y-m-d'))
            ->with('success', '修正申請を送信しました。')
            ->withInput();
    }

    /**
     * 申請IDがあれば、そのpayloadをAttendanceに一時適用する共通処理
     * - 休憩の H:i は該当日の時刻として解釈する
     */
    private function applyRequestPayloadIfExists(Attendance $attendance, string|int $requestId, int $userId): void
    {
        $tsRequest = TimesheetRequest::where('id', $requestId)
            ->where('attendance_id', $attendance->id)
            ->where('user_id', $userId)
            ->first();

        if (!$tsRequest) {
            return;
        }

        $payload = $tsRequest->payload_current
            ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($payload)) {
            return;
        }

        // 出勤・退勤（start_at / end_at のみ）
        if (!empty($payload['start_at'])) {
            $attendance->start_at = $this->parseDateTimeSmart($attendance->work_date, $payload['start_at']);
        }
        if (!empty($payload['end_at'])) {
            $attendance->end_at = $this->parseDateTimeSmart($attendance->work_date, $payload['end_at']);
        }

        // 休憩（H:i はその日の時刻として解釈）
        if (!empty($payload['breaks']) && is_array($payload['breaks'])) {
            $workDate = $attendance->work_date instanceof Carbon
                ? $attendance->work_date->copy()->startOfDay()
                : Carbon::parse($attendance->work_date)->startOfDay();

            $fakeBreaks = collect($payload['breaks'])->map(function ($row) use ($workDate) {
                $sRaw = $row['start_at'] ?? $row['start'] ?? null;
                $eRaw = $row['end_at']   ?? $row['end']   ?? null;

                $s = $sRaw ? $this->parseOnDate($workDate, $sRaw) : null;
                $e = $eRaw ? $this->parseOnDate($workDate, $eRaw) : null;

                return (object) [
                    'start_at' => $s,
                    'end_at'   => $e,
                ];
            });

            $attendance->setRelation('breaks', $fakeBreaks);
        }

        if (array_key_exists('note', $payload)) {
            $attendance->note = $payload['note'];
        }
    }

    /**
     * ヘルパ：H:i / Y-m-d H:i:s / ISO8601 など“賢く”解釈。
     * - 時刻だけなら該当日の日時として返す
     */
    private function parseDateTimeSmart($workDate, string $value): ?Carbon
    {
        if ($value === '') return null;

        // H:i のみ（例: "09:30"）
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            $date = $workDate instanceof Carbon ? $workDate->copy()->startOfDay() : Carbon::parse($workDate)->startOfDay();
            [$H, $i] = explode(':', $value, 2);
            return $date->copy()->setTime((int)$H, (int)$i, 0);
        }

        // フル日時（DB保存形式など）
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * ヘルパ：指定日の H:i / H:i:s を Carbon に（その他は parse）
     */
    private function parseOnDate(Carbon $date, string $value): ?Carbon
    {
        if ($value === '') return null;

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            $parts = explode(':', $value);
            $H = (int)($parts[0] ?? 0);
            $i = (int)($parts[1] ?? 0);
            $s = (int)($parts[2] ?? 0);
            return $date->copy()->setTime($H, $i, $s);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}