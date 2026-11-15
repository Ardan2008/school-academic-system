<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\Tugas;
use App\Support\Periode;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Database menyimpan waktu dalam UTC, tetapi keputusan akademik (hari jadwal,
 * label tenggat, tahun ajaran, dan semester) harus mengikuti kalender WIB.
 */
class WaktuAkademikTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hari_ini_memakai_zona_wib_bukan_utc(): void
    {
        // Senin 23:30 UTC = Selasa 06:30 WIB.
        Carbon::setTestNow(Carbon::parse('2026-01-05 23:30:00', 'UTC'));

        $this->assertSame('Selasa', JadwalPelajaran::hariIni());
    }

    public function test_label_deadline_memakai_zona_wib_bukan_utc(): void
    {
        // Selasa 06:30 WIB, tenggat Selasa 08:00 WIB (masih hari yang sama di WIB).
        Carbon::setTestNow(Carbon::parse('2026-01-05 23:30:00', 'UTC'));

        $tugas = new Tugas([
            'deadline' => Carbon::parse('2026-01-06 01:00:00', 'UTC'),
        ]);

        $this->assertSame('Hari ini', $tugas->labelDeadline());
    }

    public function test_periode_memakai_zona_wib_saat_tahun_baru(): void
    {
        // 31 Des 2026 23:30 UTC = 1 Jan 2027 06:30 WIB.
        Carbon::setTestNow(Carbon::parse('2026-12-31 23:30:00', 'UTC'));

        $this->assertSame('2026/2027', Periode::tahunAjaranSekarang());
        $this->assertSame(Periode::SEMESTER_GENAP, Periode::semesterSekarang());
    }

    public function test_periode_memakai_zona_wib_saat_tahun_ajaran_baru(): void
    {
        // 30 Jun 2026 23:30 UTC = 1 Jul 2026 06:30 WIB.
        Carbon::setTestNow(Carbon::parse('2026-06-30 23:30:00', 'UTC'));

        $this->assertSame('2026/2027', Periode::tahunAjaranSekarang());
        $this->assertSame(Periode::SEMESTER_GANJIL, Periode::semesterSekarang());
    }
}
