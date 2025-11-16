<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class StaffController extends Controller
{
    /**
     * スタッフ一覧（管理）: 一般ユーザーのみ
     */
    public function index(Request $request)
    {
        $keyword = $request->get('q');

        $users = User::query()
            ->when($keyword, function ($query) use ($keyword) {
                $query->where(function ($w) use ($keyword) {
                    $w->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->where('role', 'user') // 管理者は除外
            ->orderBy('name')
            ->paginate(20)
            ->through(function ($u) {
                return (object) [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'email'      => $u->email,
                ];
            });

        return view('admin.staff.index', ['rows' => $users]);
    }

    /**
     * 指定ユーザーの月次勤怠（管理側表示）
     */
    public function attendances(Request $request, User $user)
    {
        $ymStr = $request->get('month');

        try {
            $ym = $ymStr
                ? Carbon::createFromFormat('Y-m', $ymStr)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $ym = now()->startOfMonth();
        }

        $start = $ym->copy()->startOfMonth();
        $end   = $ym->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn($a) => $a->work_date->format('Y-m-d'));

        $rows = collect();

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $a = $attendances->get($key);

            if ($a) {
                $startDt = $a->start_at ? Carbon::parse($a->start_at) : null;
                $endDt   = $a->end_at   ? Carbon::parse($a->end_at)   : null;

                $in       = $startDt ? $startDt->format('H:i') : '';
                $out      = $endDt   ? $endDt->format('H:i')   : '';
                $breakMin = (int) ($a->break_minutes ?? 0);
                $workMin  = $this->calcWorkMinutes($startDt, $endDt, $breakMin);

                $status = $a->status ?? (
                    $startDt && !$endDt ? 'working'
                    : ($startDt && $endDt ? 'clocked_out' : 'off')
                );

                $attendanceId = $a->id;
            } else {
                $in = $out = '';
                $breakMin = $workMin = null;
                $status = 'off';
                $attendanceId = null;
            }

            $rows->push((object) [
                'date'          => $d->copy(),
                'in'            => $in,
                'out'           => $out,
                'break'         => $breakMin,
                'work'          => $workMin,
                'status'        => $status,
                'attendance_id' => $attendanceId,
            ]);
        }

        return view('admin.staff.attendances', [
            'user'   => $user,
            'ym'     => $start,
            'prevYm' => $start->copy()->subMonth()->format('Y-m'),
            'nextYm' => $start->copy()->addMonth()->format('Y-m'),
            'rows'   => $rows,
        ]);
    }

    /**
     * スタッフの月次勤怠CSVを出力
     */
    public function export(Request $request, User $user): StreamedResponse
    {
        $month = $request->query('month'); // 'YYYY-MM'
        try {
            $ym = $month
                ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $ym = now()->startOfMonth();
        }

        $start = $ym->copy()->startOfMonth();
        $end   = $ym->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        $filename = sprintf('attendance_%d_%s.csv', $user->id, $start->format('Ym'));

        return new StreamedResponse(function () use ($attendances) {
            // ストリームリソース名は $fp 等にして上書きを防ぐ
            $fp = fopen('php://output', 'w');

            // Excel 向け BOM（UTF-8）
            fwrite($fp, "\xEF\xBB\xBF");

            // ヘッダー行
            fputcsv($fp, ['日付', '出勤', '退勤', '休憩(分)', '実働(分)', '状態']);

            foreach ($attendances as $a) {
                $date = $a->work_date ? Carbon::parse($a->work_date)->format('Y-m-d') : '';
                $startDt = $a->start_at ? Carbon::parse($a->start_at) : null;
                $endDt   = $a->end_at   ? Carbon::parse($a->end_at)   : null;

                $in    = $startDt ? $startDt->format('H:i') : '';
                $outAt = $endDt   ? $endDt->format('H:i')   : '';
                $break = (int) ($a->break_minutes ?? 0);

                // work を計算するヘルパーを呼ぶ（controller に定義済みの想定）
                // calcWorkMinutes($startDt, $endDt, $break) は存在しない場合は代替して下さい
                $work  = method_exists($this, 'calcWorkMinutes')
                    ? $this->calcWorkMinutes($startDt, $endDt, $break)
                    : max(0, $startDt && $endDt ? $startDt->diffInMinutes($endDt) - $break : 0);

                $status = $a->status ?? '';

                fputcsv($fp, [$date, $in, $outAt, $break, $work, $status]);
            }

            fclose($fp);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * 実働時間（分）を計算
     */
    private function calcWorkMinutes(?Carbon $start, ?Carbon $end, int $break): int
    {
        if (! $start || ! $end) {
            return 0;
        }
        return max(0, $start->diffInMinutes($end) - $break);
    }
}