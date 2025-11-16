<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // 所属ユーザー
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // 日付ごとに1レコード
            $table->date('work_date');

            // 打刻時刻（出勤・退勤）
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();

            // 休憩合計（分）
            $table->unsignedInteger('break_minutes')->nullable();

            // 状態はアプリ側で使っている4種類に固定
            $table->enum('status', ['off', 'working', 'on_break', 'clocked_out'])
                ->default('off');

            // 備考（画面側が max:255 のため string に寄せるのもアリ）
            $table->string('note', 255)->nullable();

            // 1ユーザー1日1レコード
            $table->unique(['user_id', 'work_date']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
}