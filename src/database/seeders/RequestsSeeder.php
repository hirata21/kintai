<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class RequestsSeeder extends Seeder
{
    public function run(): void
    {
        // --- 設定 ---
        // ここにリクエストを作成したいユーザーのメールを列挙
        $userEmails = [
            'user@example.com',
            'taro@example.com',
            'jiro@example.com',
        ];

        // 見つからないユーザーは自動作成するか（開発用）
        $createIfMissing = true;

        // pending / approved に使う勤務日（各ユーザー共通）
        // もし特定ユーザーごとに変えたい場合は配列にする等カスタムしてください
        $pendingDate  = '2025-10-28'; // pending を作る対象日
        $approvedDate = '2025-10-27'; // approved を作る対象日

        foreach ($userEmails as $email) {
            // ユーザー取得／作成
            $user = DB::table('users')->where('email', $email)->first();
            if (! $user) {
                if ($createIfMissing) {
                    $name = explode('@', $email)[0];
                    $id = DB::table('users')->insertGetId([
                        'name' => ucfirst($name),
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $user = DB::table('users')->where('id', $id)->first();
                    info("[RequestsSeeder] ユーザー自動作成: {$email} (id={$id})");
                } else {
                    info("[RequestsSeeder] ユーザーが見つからないためスキップ: {$email}");
                    continue;
                }
            }

            // 対象勤怠を取得（ユーザー本人のレコードであることを確認）
            $attPending = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('work_date', $pendingDate)
                ->first();

            $attApproved = DB::table('attendances')
                ->where('user_id', $user->id)
                ->where('work_date', $approvedDate)
                ->first();

            // pending（承認待ち）を作成
            if ($attPending) {
                DB::table('requests')->insert([
                    'user_id'         => $user->id,
                    'attendance_id'   => $attPending->id,
                    'status'          => 'pending',
                    'payload_before'  => json_encode([
                        'start_at' => '09:30',
                        'end_at'   => '18:00',
                        'breaks'   => [], // 必要ならここに配列で細かく入れてください
                        'note'     => '寝坊しました',
                    ], JSON_UNESCAPED_UNICODE),
                    'payload_current' => json_encode([
                        'start_at' => '09:05',
                        'end_at'   => '18:00',
                        'breaks'   => [],
                        'note'     => '電車遅延（遅延証あり）',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                info("[RequestsSeeder] pending 作成: user_id={$user->id}, attendance_id={$attPending->id}");
            } else {
                info("[RequestsSeeder] pending 用勤怠が見つからずスキップ: user_id={$user->id}, date={$pendingDate}");
            }

            // approved（承認済み）を作成
            if ($attApproved) {
                DB::table('requests')->insert([
                    'user_id'         => $user->id,
                    'attendance_id'   => $attApproved->id,
                    'status'          => 'approved',
                    'payload_before'  => json_encode([
                        'start_at' => '09:00',
                        'end_at'   => '18:10',
                        'breaks'   => [],
                        'note'     => '特になし',
                    ], JSON_UNESCAPED_UNICODE),
                    'payload_current' => json_encode([
                        'start_at' => '09:00',
                        'end_at'   => '18:00',
                        'breaks'   => [],
                        'note'     => '定時退勤に修正',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                info("[RequestsSeeder] approved 作成: user_id={$user->id}, attendance_id={$attApproved->id}");
            } else {
                info("[RequestsSeeder] approved 用勤怠が見つからずスキップ: user_id={$user->id}, date={$approvedDate}");
            }
        }

        info("[RequestsSeeder] 完了");
    }
}
