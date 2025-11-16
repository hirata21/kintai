<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 修正対象の勤怠
            'attendance_id' => ['required', 'exists:attendances,id'],

            // 時刻（画面はHH:MMで送る想定。前後関係はafterで見る）
            'start_at'      => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'end_at'        => ['nullable', 'regex:/^\d{2}:\d{2}$/'],

            // 休憩
            'breaks'                => ['nullable', 'array'],
            'breaks.*.start_at'     => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'breaks.*.end_at'       => ['nullable', 'regex:/^\d{2}:\d{2}$/'],

            // 備考
            'note'                  => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_at.regex'          => '出勤時間もしくは退勤時間が不適切な値です',
            'end_at.regex'            => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.start_at.regex' => '休憩時間が不適切な値です',
            'breaks.*.end_at.regex'   => '休憩時間もしくは退勤時間が不適切な値です',

            'note.required'           => '備考を記入してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // 安全なパーサ（例外を飲み込む）
            $parseHm = function ($value): Carbon|false|null {
                if ($value === null || $value === '') {
                    return null;
                }

                try {
                    $dt = Carbon::createFromFormat('H:i', $value);
                } catch (\Throwable $e) {
                    return false;
                }

                return $dt && $dt->format('H:i') === $value ? $dt : false;
            };

            // メイン開始・終了
            $start = $parseHm($this->input('start_at'));
            $end   = $parseHm($this->input('end_at'));

            // 出勤・退勤の順序
            if ($start instanceof Carbon && $end instanceof Carbon && $start->gt($end)) {
                $v->errors()->add('start_at', '出勤時間もしくは退勤時間が不適切な値です');
                $v->errors()->add('end_at',   '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 休憩行
            foreach ((array) $this->input('breaks', []) as $i => $row) {
                $sRaw = Arr::get($row, 'start_at');
                $eRaw = Arr::get($row, 'end_at');

                $s = $parseHm($sRaw);
                $e = $parseHm($eRaw);

                // どちらかが入力されている行だけチェックする
                $hasInput = ($sRaw !== null && $sRaw !== '') || ($eRaw !== null && $eRaw !== '');
                if (! $hasInput) {
                    continue;
                }

                // 退勤が空なのに休憩がある
                if (($s instanceof Carbon || $e instanceof Carbon) && $end === null) {
                    $v->errors()->add('end_at', '休憩時間もしくは退勤時間が不適切な値です');
                }

                // 休憩開始の形式不正
                if ($s === false) {
                    $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                } else {
                    // 出勤より前はダメ
                    if ($s instanceof Carbon && $start instanceof Carbon && $s->lt($start)) {
                        $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                    }
                    // 退勤より後もダメ
                    if ($s instanceof Carbon && $end instanceof Carbon && $s->gt($end)) {
                        $v->errors()->add("breaks.$i.start_at", '休憩時間が不適切な値です');
                    }
                }

                // 休憩終了の形式不正
                if ($e === false) {
                    $v->errors()->add("breaks.$i.end_at", '休憩時間もしくは退勤時間が不適切な値です');
                } else {
                    // 退勤より後はダメ
                    if ($e instanceof Carbon && $end instanceof Carbon && $e->gt($end)) {
                        $v->errors()->add("breaks.$i.end_at", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                }

                // 開始・終了の順序
                if ($s instanceof Carbon && $e instanceof Carbon && $e->lt($s)) {
                    $v->errors()->add("breaks.$i.end_at", '休憩時間が不適切な値です');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'attendance_id'        => '対象の勤怠',
            'start_at'             => '出勤時間',
            'end_at'               => '退勤時間',
            'breaks.*.start_at'    => '休憩開始時間',
            'breaks.*.end_at'      => '休憩終了時間',
            'note'                 => '備考',
        ];
    }
}