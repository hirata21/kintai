<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // 管理者
        DB::table('users')->insert([
            'name'              => '管理者 太郎',
            'email'             => 'admin@example.com',
            'password'          => Hash::make('password123'),
            'role'              => 'admin',
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // 勤怠のメイン対象ユーザー
        DB::table('users')->insert([
            'name'              => 'ユーザー',
            'email'             => 'user@example.com',
            'password'          => Hash::make('password123'),
            'role'              => 'user',
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // 一覧画面のテスト用ユーザー1
        DB::table('users')->insert([
            'name'              => '山田 太郎',
            'email'             => 'taro@example.com',
            'password'          => Hash::make('password123'),
            'role'              => 'user',
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // 一覧画面のテスト用ユーザー2
        DB::table('users')->insert([
            'name'              => '佐藤 次郎',
            'email'             => 'jiro@example.com',
            'password'          => Hash::make('password123'),
            'role'              => 'user',
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
