<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 順番大事：
        // users → attendances → breaks → requests の流れで依存がつながる
        $this->call([
            UsersSeeder::class,
            AttendancesSeeder::class,
            BreaksSeeder::class,
            RequestsSeeder::class,
        ]);
    }
}
