<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom reference_key dipakai notifikasi yang berasal dari satu peristiwa
     * nyata (mis. satu pengumuman atau satu tenggat tugas) supaya pemeriksaan
     * berkala tidak pernah menyisipkan baris ganda untuk peristiwa yang sama.
     * Baris lama tetap NULL dan tidak terganggu karena MySQL mengizinkan
     * beberapa nilai NULL pada indeks unik.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('notifications', 'reference_key')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('reference_key', 120)->nullable()->after('url');
                $table->unique(['user_id', 'reference_key'], 'notifications_user_reference_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_user_reference_unique');
            $table->dropColumn('reference_key');
        });
    }
};
