<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 出勤・退勤（HH:MM想定。細かい検証はafterでやる）
            'start_at' => ['nullable', 'string'],
            'end_at'   => ['nullable', 'string'],

            // 休憩（行ごとに start / end で揃える）
            'breaks'         => ['nullable', 'array'],
            'breaks.*.start' => ['nullable', 'string'],
            'breaks.*.end'   => ['nullable', 'string'],

            // 備考
            'note'           => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => '備考を記入してください',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // "HH:MM" を Carbon にする小さなパーサ
            $parseHm = function (?string $value): Carbon|false|null {
                if ($value === null || $value === '') {
                    return null;
                }

                try {
                    $dt = Carbon::createFromFormat('H:i', $value);
                } catch (\Throwable $e) {
                    return false;
                }

                return ($dt && $dt->format('H:i') === $value) ? $dt : false;
            };

            // 出退勤
            $in  = $parseHm($this->input('start_at'));
            $out = $parseHm($this->input('end_at'));

            $inInvalid  = $this->filled('start_at') && $in === false;
            $outInvalid = $this->filled('end_at')   && $out === false;
            $inAfterOut = ($in instanceof Carbon && $out instanceof Carbon && $in->gt($out));

            if ($inInvalid || $outInvalid || $inAfterOut) {
                $v->errors()->add('start_at', '出勤時間もしくは退勤時間が不適切な値です');
                $v->errors()->add('end_at',   '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩行の検証
            foreach ((array) $this->input('breaks', []) as $i => $row) {
                $startRaw = $row['start'] ?? null;
                $endRaw   = $row['end']   ?? null;

                // どちらかに入力がある行だけチェックする
                $hasAnyInput = ($startRaw !== null && $startRaw !== '') ||
                    ($endRaw   !== null && $endRaw   !== '');
                if (! $hasAnyInput) {
                    continue;
                }

                $bs = $parseHm($startRaw);
                $be = $parseHm($endRaw);

                // 親の退勤が不正な場合はここで終了（= 休憩の整合性が取れない）
                $parentOutInvalid = $this->filled('end_at') && $out === false;

                // 休憩終了が退勤より後
                $breakEndAfterOut = (
                    $be instanceof Carbon &&
                    $out instanceof Carbon &&
                    $be->gt($out)
                );

                if ($parentOutInvalid || $breakEndAfterOut) {
                    $v->errors()->add("breaks.$i.end", '休憩時間もしくは退勤時間が不適切な値です');
                    continue;
                }

                // 休憩開始が出勤より前 / 退勤より後
                $breakStartBeforeIn = (
                    $bs instanceof Carbon &&
                    $in instanceof Carbon &&
                    $bs->lt($in)
                );
                $breakStartAfterOut = (
                    $bs instanceof Carbon &&
                    $out instanceof Carbon &&
                    $bs->gt($out)
                );

                if ($breakStartBeforeIn || $breakStartAfterOut) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    continue;
                }

                // 形式不正（HH:MMじゃない）
                $formatBad =
                    (($startRaw !== null && $startRaw !== '') && $bs === false) ||
                    (($endRaw   !== null && $endRaw   !== '') && $be === false);

                // 片方だけ入力
                $pairBad =
                    (($startRaw !== null && $startRaw !== '') && ($endRaw === null || $endRaw === '')) ||
                    (($endRaw   !== null && $endRaw   !== '') && ($startRaw === null || $startRaw === ''));

                // 開始 >= 終了
                $orderBadBreak =
                    ($bs instanceof Carbon && $be instanceof Carbon && $bs->gte($be));

                if ($formatBad || $pairBad || $orderBadBreak) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                }
            }
        });
    }
}
