<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat tindak lanjut Admin atas permintaan bantuan dari pop-up
 * "Hubungi Tim IT Sekolah" di landing page. Satu permintaan bisa punya
 * beberapa balasan (misalnya email pertama gagal lalu dikirim ulang,
 * atau admin menambahkan info lanjutan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('it_help_replies')) {
            Schema::create('it_help_replies', function (Blueprint $table) {
                $table->id();

                $table->foreignId('it_help_request_id')
                    ->constrained('it_help_requests')->cascadeOnDelete();

                // Admin yang menulis balasan.
                $table->foreignId('user_id')
                    ->constrained('users')->nullOnDelete();

                $table->text('pesan');
                $table->string('status_pengiriman', 30)->default('terkirim');
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('it_help_replies', 'status_pengiriman')) {
            Schema::table('it_help_replies', function (Blueprint $table) {
                $table->string('status_pengiriman', 30)->default('terkirim')->after('pesan');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('it_help_replies');
    }
};
