<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AttendancesSeeder extends Seeder
{
    public function run(): void
    {
        // === 設定 ===
        // シードするユーザーのメールアドレス配列（必要に応じて追加してください）
        $userEmails = [
            'user@example.com',
            'taro@example.com',
            'jiro@example.com',
        ];

        // 見つからないユーザーがいたら自動作成するか（開発環境向け）
        $createIfMissing = true;

        $today = Carbon::today(); // 実行日ベース
        $currentMonthStart = $today->copy()->startOfMonth();
        $prevMonthStart    = $currentMonthStart->copy()->subMonth();

        // 決定的なパターン（index によって時刻が決まる）
        $startTimes = ['09:00:00', '09:15:00', '09:30:00', '08:50:00', '09:05:00'];
        $endTimes   = ['18:00:00', '17:45:00', '18:15:00', '17:30:00'];
        $breakOptions = [60, 60, 45, 60];
        $notes = [null, null, '遅刻理由: 電車遅延', null, '午前中会議のため出勤遅め'];

        // ヘルパ: 指定月の平日 を配列で返す（当月は今日まで）
        $listWeekdays = function (Carbon $startOfMonth) use ($today) {
            $dates = [];
            $d = $startOfMonth->copy();
            $end = $startOfMonth->copy()->endOfMonth();
            while ($d->lte($end)) {
                if ($d->isWeekday()) {
                    if ($d->lte($today)) {
                        $dates[] = $d->copy();
                    }
                }
                $d->addDay();
            }
            return $dates;
        };

        $prevDates = $listWeekdays($prevMonthStart);
        $currentDates = $listWeekdays($currentMonthStart);
        $allDates = array_merge($prevDates, $currentDates);

        foreach ($userEmails as $email) {
            // ユーザー取得（なければ作る or スキップ）
            $user = DB::table('users')->where('email', $email)->first();
            if (! $user) {
                if ($createIfMissing) {
                    // 自動作成（開発用の安全なダミー）
                    $name = explode('@', $email)[0];
                    $password = Hash::make('password'); // dev 用の固定パスワード
                    $id = DB::table('users')->insertGetId([
                        'name' => ucfirst($name),
                        'email' => $email,
                        'password' => $password,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $user = DB::table('users')->where('id', $id)->first();
                    info("[AttendancesSeeder] ユーザー自動作成: {$email} (id={$id})");
                } else {
                    info("[AttendancesSeeder] ユーザーが見つからないためスキップ: {$email}");
                    continue;
                }
            }

            // 各日付について勤怠を作成
            foreach ($allDates as $date) {
                $workDate = $date->toDateString();

                // 同一 user + work_date が既にある場合スキップ
                $exists = DB::table('attendances')
                    ->where('user_id', $user->id)
                    ->where('work_date', $workDate)
                    ->exists();
                if ($exists) continue;

                $i = $date->day % count($startTimes);
                $j = $date->day % count($endTimes);
                $k = $date->day % count($breakOptions);
                $n = $date->day % count($notes);

                $startAtStr = $date->toDateString() . ' ' . $startTimes[$i];
                $endAtStr   = $date->toDateString() . ' ' . $endTimes[$j];

                if ($date->isSameDay($today)) {
                    $endAt = null;
                    $status = 'working';
                    $note = '勤務中（退勤前）';
                } else {
                    $endAt = $endAtStr;
                    $status = 'clocked_out';
                    $note = $notes[$n];
                }

                DB::table('attendances')->insert([
                    'user_id'       => $user->id,
                    'work_date'     => $workDate,
                    'start_at'      => Carbon::parse($startAtStr),
                    'end_at'        => $endAt ? Carbon::parse($endAt) : null,
                    'break_minutes' => $breakOptions[$k],
                    'status'        => $status,
                    'note'          => $note,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            info("[AttendancesSeeder] 挿入完了: user_id={$user->id}, email={$email}");
        }

        info("[AttendancesSeeder] 全ユーザー処理完了");
    }
}
