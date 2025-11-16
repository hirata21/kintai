<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();

            // 申請者／対象勤怠（削除時は一緒に消す）
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('attendance_id')
                ->constrained('attendances')
                ->cascadeOnDelete();

            // 却下なし運用：pending / approved の2値のみ
            $table->enum('status', ['pending', 'approved'])
                ->default('pending')
                ->index();

            // 修正前後のスナップショット（必要な値をJSONで保持）
            // 例: {"start_at":"09:00","end_at":"18:00","breaks":[...],"note":"..."}
            $table->json('payload_before')->nullable();
            $table->json('payload_current')->nullable();

            $table->timestamps();

            // よく使う検索条件のためのインデックス
            $table->index(['user_id', 'created_at']);
            $table->index('attendance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
}