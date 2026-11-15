<?php

namespace App\Console\Commands;

use App\Services\NotifikasiAkademikService;
use Illuminate\Console\Command;

class PeriksaPengingatAkademikCommand extends Command
{
    protected $signature = 'notifikasi:periksa-pengingat';

    protected $description = 'Periksa dan sinkronkan notifikasi pengingat akademik berkala (pengingat deadline, pengingat tugas akademik guru, dan pengingat input nilai) dari data nyata di database.';

    public function handle(NotifikasiAkademikService $service): int
    {
        $this->info('Memulai pemeriksaan pengingat akademik berkala...');

        $service->periksaSemuaPengingat();

        $this->info('Pemeriksaan pengingat akademik selesai.');

        return self::SUCCESS;
    }
}
