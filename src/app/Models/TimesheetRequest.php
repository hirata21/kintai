<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';

    /** このモデルは requests テーブルを扱う */
    protected $table = 'requests';

    /** 一括代入許可 */
    protected $fillable = [
        'user_id',
        'attendance_id',
        'status',
        'payload_before',
        'payload_current',
    ];

    /** JSON → 配列キャスト */
    protected $casts = [
        'payload_before'  => 'array',
        'payload_current' => 'array',
    ];

    /* =========================
       リレーション
       ========================= */

    /** 対象となる勤怠1件（requests.attendance_id → attendances.id） */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** 申請したユーザー（明示名：requester） */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** 下位互換用：$tsRequest->user でも参照できるように（エイリアス） */
    public function user(): BelongsTo
    {
        return $this->requester();
    }

    /* =========================
       スコープ
       ========================= */

    /** 未承認だけに絞る */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /** 承認済みに絞る */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }
}
