<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // 基本情報
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // メール認証用
            $table->timestamp('email_verified_at')->nullable();

            // 権限（一般 / 管理）
            $table->enum('role', ['user', 'admin'])->default('user');

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}