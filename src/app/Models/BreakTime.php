<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakTime extends Model
{
    use HasFactory;

    /**
     * デフォルトだと break_times を見に行くので、
     * 今回の仕様に合わせて breaks に固定する。
     */
    protected $table = 'breaks';

    /**
     * 一括代入を許可するカラム
     */
    protected $fillable = [
        'attendance_id',
        'start_at',
        'end_at',
    ];

    /**
     * 日付系のキャスト
     */
    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    /**
     * この休憩が属している勤怠
     * breaks.attendance_id → attendances.id
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
