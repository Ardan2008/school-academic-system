<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token atur ulang password.
 *
 * Token hanya disimpan sebagai SHA-256 hash, bukan nilai mentahnya, sehingga
 * bocornya isi tabel tidak langsung berarti token bisa dipakai. Token bersifat
 * single-use (used_at) dan punya masa berlaku expires_at.
 *
 * Tabel ini menggantikan tabel password_reset_tokens bawaan Laravel yang
 * sebelumnya sudah dihapus; alur reset memakai service internal kita sendiri
 * dan tidak bergantung pada password broker framework.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
