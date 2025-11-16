<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use Illuminate\Support\Str;

class BreaksSeeder extends Seeder
{
    public function run(): void
    {
        // 設定: null にすると全件（start/end があるもの）を処理する
        // 例: $limitDate = '2025-10-28'; // 指定日だけ処理したい場合
        $limitDate = null;

        // 設定: break_minutes が null のときに使うデフォルト比率 (勤務時間に対する割合)
        // 0.10 = 勤務時間の10% を休憩にする（必要なら 0.0833=5/60 などに変更）
        $defaultBreakRatio = 0.10;

        // 使用するテーブル名（BreakTime モデルが存在すればそれを使うが、無ければ 'breaks' テーブルに直接挿入）
        $useEloquent = class_exists(\App\Models\BreakTime::class);
        $tableName = $useEloquent ? (new \App\Models\BreakTime())->getTable() : 'breaks';

        // 対象勤怠を取得（start_at と end_at があるもの）
        $query = Attendance::query()
            ->whereNotNull('start_at')
            ->whereNotNull('end_at');

        if ($limitDate) {
            $query->whereDate('work_date', $limitDate);
        }

        $attendances = $query->withCount('breaks')->with('breaks')->get();

        info("[BreaksSeeder] 処理件数: " . $attendances->count() . ", useEloquent=" . ($useEloquent ? 'yes' : 'no'));

        foreach ($attendances as $attendance) {
            $aid = $attendance->id;
            $workStart = Carbon::parse($attendance->start_at);
            $workEnd   = Carbon::parse($attendance->end_at);

            // 安全チェック: end が start より前ならスキップ
            if ($workEnd->lte($workStart)) {
                info("[BreaksSeeder] スキップ (end <= start): attendance_id={$aid}");
                continue;
            }

            // 既に breaks があればスキップ（重複防止）
            if ($attendance->breaks_count > 0) {
                info("[BreaksSeeder] 既存 breaks があるためスキップ: attendance_id={$aid}, breaks_count={$attendance->breaks_count}");
                continue;
            }

            // 休憩分（分）の決定
            if ($attendance->break_minutes !== null) {
                $breakMin = (int) $attendance->break_minutes;
            } else {
                $totalMin = $workStart->diffInMinutes($workEnd);
                // 最低1分、最大を勤務時間の半分未満に制限
                $calced = max(1, (int) floor($totalMin * $defaultBreakRatio));
                $calced = min($calced, max(0, (int) floor($totalMin / 2) - 1));
                $breakMin = max(0, $calced);
            }

            if ($breakMin <= 0) {
                info("[BreaksSeeder] 生成休憩時間が0のためスキップ: attendance_id={$aid}");
                continue;
            }

            // 休憩を勤務時間の中央に配置（中央を起点に breakMin を配置）
            $totalMin = $workStart->diffInMinutes($workEnd);
            $gapBefore = (int) floor(($totalMin - $breakMin) / 2);
            $breakStart = $workStart->copy()->addMinutes($gapBefore);
            $breakEnd   = $breakStart->copy()->addMinutes($breakMin);

            // safety: ensure start < end and within work range
            if ($breakEnd->lte($breakStart) || $breakStart->lt($workStart) || $breakEnd->gt($workEnd)) {
                // try fallback: place break after 3 hours from start (or start+1h) and clamp
                $fallbackStart = $workStart->copy()->addMinutes(min(180, max(60, (int)floor($totalMin / 3))));
                $fallbackEnd = $fallbackStart->copy()->addMinutes($breakMin);
                if ($fallbackEnd->gt($workEnd)) {
                    // clamp end to workEnd, start accordingly
                    $fallbackEnd = $workEnd->copy()->subMinutes(1);
                    $fallbackStart = $fallbackEnd->copy()->subMinutes($breakMin);
                    if ($fallbackStart->lt($workStart)) {
                        $fallbackStart = $workStart->copy()->addMinutes(1);
                    }
                }
                $breakStart = $fallbackStart;
                $breakEnd = $fallbackEnd;
            }

            // 重複チェック（attendance_id + start_at の一致を基準）
            $startStr = $breakStart->toDateTimeString();
            $endStr   = $breakEnd->toDateTimeString();

            $exists = DB::table($tableName)
                ->where('attendance_id', $aid)
                ->where('start_at', $startStr)
                ->exists();

            if ($exists) {
                info("[BreaksSeeder] 既存の休憩（start一致）をスキップ: attendance_id={$aid}, start_at={$startStr}");
                continue;
            }

            // 挿入
            try {
                if ($useEloquent) {
                    \App\Models\BreakTime::create([
                        'attendance_id' => $aid,
                        'start_at' => $startStr,
                        'end_at'   => $endStr,
                    ]);
                } else {
                    DB::table($tableName)->insert([
                        'attendance_id' => $aid,
                        'start_at' => $startStr,
                        'end_at'   => $endStr,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                info("[BreaksSeeder] 挿入成功: attendance_id={$aid}, start_at={$startStr}, end_at={$endStr}, break_min={$breakMin}");
            } catch (Exception $e) {
                Log::error('[BreaksSeeder] 挿入エラー: ' . $e->getMessage(), [
                    'attendance_id' => $aid,
                    'start_at' => $startStr,
                    'end_at' => $endStr,
                ]);
                info("[BreaksSeeder] 挿入失敗（ログ参照）: attendance_id={$aid}, start_at={$startStr}");
            }
        }

        info("[BreaksSeeder] 完了");
    }
}