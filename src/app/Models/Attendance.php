<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attendance extends Model
{
    use HasFactory;

    /**
     * 一括代入許可
     *
     * ※ 将来カラムを足すかもしれないのでここに書いておく。
     */
    protected $fillable = [
        'user_id',
        'work_date',
        'start_at',       // 出勤時刻
        'end_at',         // 退勤時刻
        'break_minutes',  // 休憩合計(分) - nullable に対応
        'status',         // 'off','working','on_break','clocked_out'
        'note',
    ];

    /**
     * キャスト
     */
    protected $casts = [
        'work_date'     => 'date',
        'start_at'      => 'datetime',
        'end_at'        => 'datetime',
        // nullable integer のままキャスト（null のまま保持される）
        'break_minutes' => 'integer',
    ];

    /* =========================================================
       リレーション
       ========================================================= */

    /**
     * この勤怠を持っているユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この日の休憩履歴(複数)
     * attendances.id → break_times.attendance_id
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(BreakTime::class, 'attendance_id')
            ->orderBy('start_at', 'asc');
    }

    /**
     * 開いている休憩 (end_at が NULL の最新1件)
     */
    public function currentOpenBreak(): HasOne
    {
        return $this->hasOne(BreakTime::class, 'attendance_id')
            ->whereNull('end_at')
            ->latestOfMany('start_at');
    }

    /* =========================================================
       集計系ヘルパ
       ========================================================= */

    /**
     * 実働時間（分）を計算して返す
     *
     * - 保存済みの break_minutes がある場合はそれを優先して使用する（高速化）
     * - なければ breaks リレーションから算出する
     *
     * @return int 実働分（start_at または end_at が無い場合は 0 を返す）
     */
    public function calcWorkMinutes(): int
    {
        if (! $this->start_at || ! $this->end_at) {
            return 0;
        }

        $in  = Carbon::parse($this->start_at);
        $out = Carbon::parse($this->end_at);

        $total = $in->diffInMinutes($out);

        // 保存済みの break_minutes があればそれを優先して使う（NULL の場合は算出）
        $breakMin = $this->break_minutes !== null
            ? (int) $this->break_minutes
            : $this->calcBreakMinutes();

        return max(0, $total - $breakMin);
    }

    /**
     * 休憩合計（分）を計算して返す
     *
     * - breaks リレーションの各行を計算し合計する
     * - 休憩が open の場合は現在時刻で計算する
     *
     * @return int
     */
    public function calcBreakMinutes(): int
    {
        $this->loadMissing('breaks');

        $sum = 0;

        $hasRange = ($this->start_at && $this->end_at);
        $in  = $hasRange ? Carbon::parse($this->start_at) : null;
        $out = $hasRange ? Carbon::parse($this->end_at)   : null;

        // now() を1回だけ
        $now = now();

        foreach ($this->breaks as $br) {
            if (! $br->start_at) {
                continue;
            }

            $bStart = Carbon::parse($br->start_at);
            $bEnd   = $br->end_at
                ? Carbon::parse($br->end_at)
                : $now; // まだ閉じてなければ現在時刻まで

            if ($hasRange) {
                // 勤務帯にクリップ
                $s = $bStart->max($in);
                $e = $bEnd->min($out);

                if ($e->gt($s)) {
                    $sum += $s->diffInMinutes($e);
                }
            } else {
                if ($bEnd->gt($bStart)) {
                    $sum += $bStart->diffInMinutes($bEnd);
                }
            }
        }

        return $sum;
    }

    /**
     * calcBreakMinutes() の結果を break_minutes に反映して保存
     *
     * @return int 計算された休憩分
     */
    public function recomputeBreakMinutes(): int
    {
        $min = $this->calcBreakMinutes();
        $this->break_minutes = $min;

        try {
            $this->save();
        } catch (\Throwable $e) {
            // カラムが無い環境でも落とさない
        }

        return $min;
    }

    /* =========================================================
       アクセサ
       ========================================================= */

    /**
     * 保持している break_minutes が null の場合は 0 を返す安全版アクセサ
     *
     * Blade / 集計では $attendance->break_minutes_safe を使うと安全です。
     *
     * @return int
     */
    public function getBreakMinutesSafeAttribute(): int
    {
        return $this->break_minutes === null ? 0 : (int) $this->break_minutes;
    }

    /**
     * calcBreakMinutes() の結果を返すアクセサ（常に最新を計算する）
     *
     * Blade: {{ $attendance->break_minutes_calc }}
     *
     * @return int
     */
    public function getBreakMinutesCalcAttribute(): int
    {
        return $this->calcBreakMinutes();
    }

    /**
     * calcWorkMinutes() の結果を返すアクセサ
     *
     * Blade: {{ $attendance->work_minutes_calc }}
     *
     * @return int
     */
    public function getWorkMinutesCalcAttribute(): int
    {
        return $this->calcWorkMinutes();
    }

    /**
     * 休憩合計を "H:MM" で返す（保存値がある場合はそれを優先）
     *
     * @return string|null
     */
    public function getBreakHmAttribute(): ?string
    {
        // 保存値があるならそれを優先、なければ再計算
        $m = $this->break_minutes !== null ? (int) $this->break_minutes : $this->calcBreakMinutes();

        if ($m <= 0) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }

    /**
     * 実働を "H:MM" で返す
     *
     * @return string|null
     */
    public function getWorkHmAttribute(): ?string
    {
        $m = $this->calcWorkMinutes();

        if ($m <= 0) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($m, 60), $m % 60);
    }

    /* =========================================================
       スコープ
       ========================================================= */

    /**
     * ログイン中ユーザーの今日の勤怠
     */
    public function scopeTodayForUser($query, int $userId)
    {
        return $query->where('user_id', $userId)
            ->whereDate('work_date', today());
    }
}