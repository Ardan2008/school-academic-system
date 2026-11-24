<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'guru')
            ->whereExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('penugasan_mengajar')
                    ->whereColumn('penugasan_mengajar.guru_id', 'users.id');
            })
            ->update(['penugasan_diatur' => true]);
    }

    public function down(): void {}
};
