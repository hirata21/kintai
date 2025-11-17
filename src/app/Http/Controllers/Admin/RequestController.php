<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimesheetRequest;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    /**
     * 申請一覧（tab: pending / closed）
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'pending'); // 'pending' | 'closed'

        // 基本クエリ
        $baseQuery = TimesheetRequest::with(['attendance', 'requester'])
            ->latest('created_at');

        // 件数
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where('status', 'approved')->count();

        // 一覧本体
        $query = clone $baseQuery;
        if ($tab === 'pending') {
            $query->where('status', 'pending');
        } else {
            $tab = 'closed';
            $query->where('status', 'approved');
        }

        /** @var \Illuminate\Pagination\LengthAwarePaginator $rows */
        $rows = $query->paginate(20)->appends($request->query());

        // 表示用整形
        $rows->getCollection()->transform(function (TimesheetRequest $req) use ($tab) {
            $payload = $this->normalizePayloadArray($req->payload_current);

            if ($tab === 'pending') {
                // 承認前は申請の時刻をそのまま（HH:MMへ丸め）
                $req->after_start_at = $this->fmtHm($payload['start_at'] ?? null);
                $req->after_end_at   = $this->fmtHm($payload['end_at']   ?? null);
            } else {
                // 承認済みタブでは勤怠側の確定値
                $attendance = $req->attendance;
                $req->after_start_at = $attendance?->start_at ? Carbon::parse($attendance->start_at)->format('H:i') : null;
                $req->after_end_at   = $attendance?->end_at   ? Carbon::parse($attendance->end_at)->format('H:i')   : null;
            }

            // 備考は 申請payload > 申請note > 勤怠note > '—'
            $req->reason_note = $payload['note']
                ?? $req->note
                ?? optional($req->attendance)->note
                ?? '—';

            return $req;
        });

        return view('admin.requests.index', [
            'rows'          => $rows,
            'tab'           => $tab,
            'pendingCount'  => $pendingCount,
            'approvedCount' => $approvedCount,
        ]);
    }

    /**
     * 承認フォーム表示
     */
    public function approveForm(int $id)
    {
        $correction = TimesheetRequest::with(['attendance', 'requester'])
            ->findOrFail($id);

        return view('admin.requests.approve', compact('correction'));
    }

    /**
     * 承認実行（後勝ち／上書き）
     * - 同じattendanceの既存approvedを先に無効化（今回は削除）
     * - payload_currentを勤怠へ適用
     * - 競合防止のため行ロック＋Tx
     * - JSON/AJAXと通常遷移の両対応
     */
    public function approve(Request $http, int $id)
    {
        $correction = TimesheetRequest::with(['attendance', 'attendance.breaks'])
            ->findOrFail($id);

        // 事前ガード
        if ($correction->status !== 'pending') {
            return $this->errorResponse($http, 'この申請はすでに処理済みです。', 409);
        }
        if (! $correction->attendance) {
            return $this->errorResponse($http, '関連する勤怠データが見つかりません。', 404);
        }

        DB::transaction(function () use ($correction) {
            // 勤怠をロック
            /** @var Attendance $attendance */
            $attendance = Attendance::whereKey($correction->attendance_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 同勤怠の申請行もロック（競合対策）
            TimesheetRequest::where('attendance_id', $correction->attendance_id)
                ->lockForUpdate()
                ->get();

            // 既存approved（自分以外）を先に無効化（今回は削除）
            TimesheetRequest::where('attendance_id', $correction->attendance_id)
                ->where('status', 'approved')
                ->where('id', '!=', $correction->id)
                ->delete();

            // payload_current を適用
            $this->applyPayloadToAttendance($attendance, $correction);

            // 今回をapprovedに
            $correction->update(['status' => 'approved']);
        });

        return $this->okResponse($http, '申請を承認しました。', route('requests.index', ['tab' => 'closed']));
    }

    /* =========================
       内部ヘルパ
       ========================= */

    /**
     * payload_current を配列へ正規化
     */
    private function normalizePayloadArray(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_object($raw)) return (array) $raw;
        return [];
    }

    /**
     * H:i へ丸め（null/空は null）
     */
    private function fmtHm(?string $val): ?string
    {
        if ($val === null || $val === '') return null;
        if (preg_match('/^\d{1,2}:\d{2}$/', $val)) {
            [$h, $m] = explode(':', $val);
            return sprintf('%02d:%02d', (int)$h, (int)$m);
        }
        try {
            return Carbon::parse($val)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * payload_current を勤怠へ適用（出勤/退勤、休憩、備考、break_minutes計算）
     */
    private function applyPayloadToAttendance(Attendance $attendance, TimesheetRequest $correction): void
    {
        $p = $this->normalizePayloadArray($correction->payload_current);

        // 勤怠日の文字列
        $workDate = $attendance->work_date
            ? Carbon::parse($attendance->work_date)->toDateString()
            : Carbon::today()->toDateString();

        // "HH:MM" → 当日Carbon、その他はCarbon任せ
        $toDateTime = function (?string $hm) use ($workDate): ?Carbon {
            if (! $hm) return null;
            if (preg_match('/^\d{1,2}:\d{2}$/', $hm)) {
                // 分解してゼロ詰めしておく
                [$h, $m] = explode(':', $hm);
                $hm = sprintf('%02d:%02d', (int)$h, (int)$m);
                return Carbon::createFromFormat('Y-m-d H:i', "$workDate $hm");
            }
            return Carbon::parse($hm);
        };

        // キー互換（start_at/clock_in_at, end_at/clock_out_at）
        if (array_key_exists('start_at', $p) || array_key_exists('clock_in_at', $p)) {
            $attendance->start_at = $toDateTime($p['start_at'] ?? $p['clock_in_at'] ?? null);
        }
        if (array_key_exists('end_at', $p) || array_key_exists('clock_out_at', $p)) {
            $attendance->end_at = $toDateTime($p['end_at'] ?? $p['clock_out_at'] ?? null);
        }

        // 休憩（payloadにあれば全入替。ただし「存在するが中身が空」の場合は既存を維持）
        $recalcBreaks = false;
        if (array_key_exists('breaks', $p)) {
            $rows = $p['breaks'];
            if (is_string($rows)) {
                $rows = json_decode($rows, true) ?: [];
            }
            if (!is_array($rows)) {
                $rows = [];
            }

            // フィルタ：実際に start/end のどちらかが入っている行のみ有効と見なす
            $filtered = array_values(array_filter($rows, function ($r) {
                if (!is_array($r) && !is_object($r)) return false;
                $s = isset($r['start']) ? (string)$r['start'] : (isset($r['start_at']) ? (string)$r['start_at'] : '');
                $e = isset($r['end'])   ? (string)$r['end']   : (isset($r['end_at'])   ? (string)$r['end_at']   : '');
                return trim((string)$s) !== '' || trim((string)$e) !== '';
            }));

            if (count($filtered) === 0) {
                // breaks キーはあったが中身は空 -> 既存 breaks を維持（上書きしない）
                $recalcBreaks = true;
            } else {
                // 有効行あり -> 上書き処理（既存削除→挿入）
                $attendance->breaks()->delete();
                $total = 0;
                foreach ($filtered as $r) {
                    $bs = $toDateTime($r['start'] ?? $r['start_at'] ?? null);
                    $be = $toDateTime($r['end']   ?? $r['end_at']   ?? null);
                    if ($bs && ! $be) {
                        // end が無ければ start+10min を補完
                        $be = $bs->copy()->addMinutes(10);
                    }
                    if ($be && ! $bs) {
                        $bs = $be->copy()->subMinutes(10);
                    }

                    if ($bs && $be && $bs->lt($be)) {
                        $attendance->breaks()->create([
                            'start_at' => $bs,
                            'end_at'   => $be,
                        ]);
                        $total += $bs->diffInMinutes($be);
                    }
                }
                $attendance->break_minutes = $total;
            }
        } else {
            // payloadにbreaksが無い場合は現状のbreaksで合計を再計算
            $recalcBreaks = true;
        }

        if ($recalcBreaks) {
            $attendance->load('breaks');
            $attendance->break_minutes = $attendance->breaks->sum(function ($b) {
                $s = Carbon::parse($b->start_at);
                $e = Carbon::parse($b->end_at);
                return $s->diffInMinutes($e);
            });
        }

        // 備考
        if (array_key_exists('note', $p)) {
            $attendance->note = is_string($p['note']) ? $p['note'] : $attendance->note;
        }

        $attendance->save();
    }

    /**
     * 成功レスポンス（AJAXならJSON、通常はリダイレクト）
     */
    private function okResponse(Request $http, string $flashMessage, ?string $redirectTo = null)
    {
        if ($http->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->to($redirectTo ?: url()->previous())
            ->with('status', $flashMessage);
    }

    /**
     * エラーレスポンス（AJAXならJSON with status code、通常はリダイレクト with error）
     */
    private function errorResponse(Request $http, string $message, int $status = 400)
    {
        if ($http->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }
        return redirect()->back()->with('error', $message);
    }
}