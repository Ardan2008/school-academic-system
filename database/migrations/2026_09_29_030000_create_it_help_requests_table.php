<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan bantuan yang dikirim lewat pop-up "Hubungi Tim IT Sekolah"
 * di landing page. Datanya berasal dari pengunjung publik, jadi tidak
 * dipasangkan ke user: kolom user_id sengaja dibiarkan nullable.
 *
 * Identitas diisi sesuai role (Guru memakai NIP, Siswa memakai NIS) supaya
 * Tim IT bisa langsung tahu permintaan bantuan ini berasal dari akun siapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_help_requests', function (Blueprint $table) {
            $table->id();

            // Kosong untuk pengunjung yang belum pernah masuk ke sistem.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('role', 20);
            $table->string('identifier', 30);
            $table->string('email', 120);
            $table->text('pesan');
            $table->string('status', 20)->default('baru');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['role', 'identifier']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_help_requests');
    }
};
