<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChangeDetectionTampilanTest extends TestCase
{
    private function sumber(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    public function test_utilitas_change_detection_tersedia_secara_global(): void
    {
        $utilitas = $this->sumber('resources/js/change-detection.js');
        $app = $this->sumber('resources/js/app.js');

        $this->assertStringContainsString('export default changeDetection;', $utilitas);
        $this->assertStringContainsString("options.save || '[data-change-save]'", $utilitas);
        $this->assertStringContainsString('form.checkValidity()', $utilitas);
        $this->assertStringContainsString('new MutationObserver', $utilitas);
        $this->assertStringContainsString('snapshot()', $utilitas);
        $this->assertStringContainsString('refresh: jadwalkanSinkron', $utilitas);
        $this->assertStringContainsString("import buatChangeDetection from './change-detection.js';", $app);
        $this->assertStringContainsString('window.saskansaChange = buatChangeDetection();', $app);
    }

    public function test_form_edit_admin_diikat_ke_utilitas_change_detection(): void
    {
        $pass = [
            'resources/views/dashboard/admin/data-guru/index.blade.php',
            'resources/views/dashboard/admin/data-siswa/index.blade.php',
            'resources/views/dashboard/admin/data-kelas/index.blade.php',
            'resources/views/dashboard/admin/data-jurusan/index.blade.php',
            'resources/views/dashboard/admin/data-mata-pelajaran/index.blade.php',
            'resources/views/dashboard/admin/jam-belajar/index.blade.php',
        ];

        foreach ($pass as $path) {
            $this->assertStringContainsString('saskansaChange', $this->sumber($path), $path);
            $this->assertMatchesRegularExpression('/saskansaChange\??\.bind(?:\?\.)?\(/', $this->sumber($path), $path);
        }

        $guru = $this->sumber('resources/views/dashboard/admin/data-guru/index.blade.php');
        $this->assertStringContainsString('refreshForm', $guru);
        $this->assertStringContainsString('siapkanPenugasanChange', $guru);
        $this->assertStringContainsString("save: '[data-penugasan-simpan]'", $guru);

        $jam = $this->sumber('resources/views/dashboard/admin/jam-belajar/index.blade.php');
        $this->assertStringContainsString('window.saskansaChange?.refresh?.(jamForm)', $jam);
    }

    public function test_form_jadwal_edit_menghormati_validitas_dan_status_sibuk(): void
    {
        $sumber = $this->sumber('resources/js/jadwal-guru.js');

        $this->assertStringContainsString('if (edit && window.saskansaChange?.bind)', $sumber);
        $this->assertStringContainsString('window.saskansaChange.valid(form, sukses)', $sumber);
        $this->assertStringContainsString('window.saskansaChange.busy(form, true)', $sumber);
        $this->assertStringContainsString('window.saskansaChange?.refresh?.(form)', $sumber);
        $this->assertStringContainsString("save: '[data-simpan-utama], [data-simpan-lagi]'", $sumber);
    }

    public function test_form_guru_diikat_pada_alur_edit_saja(): void
    {
        $tugas = $this->sumber('resources/views/dashboard/guru/tugas-materi/index.blade.php');
        $this->assertSame(2, substr_count($tugas, 'const pasangChange = () => window.saskansaChange?.bind(form'));
        $this->assertStringContainsString('data-file-uploader="edit-tugas"', $tugas);
        $this->assertStringContainsString('data-materi-uploader="edit"', $tugas);

        $pengumpulan = $this->sumber('resources/views/dashboard/guru/tugas-materi/pengumpulan.blade.php');
        $this->assertStringContainsString("querySelectorAll('form[data-change-form]')", $pengumpulan);
        $this->assertStringContainsString("save: '[data-change-save]'", $pengumpulan);
        $this->assertStringContainsString('data-change-save', $pengumpulan);

        $nilai = $this->sumber('resources/views/dashboard/guru/input-nilai/index.blade.php');
        $this->assertStringContainsString('const nilaiIndex = new Map()', $nilai);
        $this->assertStringContainsString('const pasangChange = () => window.saskansaChange?.bind(form', $nilai);
        $this->assertStringContainsString('data-change-ignore', $nilai);
    }

    public function test_form_siswa_dan_profil_memiliki_pengaman_simpan(): void
    {
        $siswa = $this->sumber('resources/views/dashboard/siswa/tugas-materi/index.blade.php');
        $this->assertStringContainsString("window.saskansaChange?.bind(form, { save: '[data-submit-confirm]' })", $siswa);
        $this->assertStringContainsString('change?.refresh();', $siswa);
        $this->assertStringContainsString('change?.snapshot();', $siswa);

        $profil = $this->sumber('resources/views/components/ui/profile.blade.php');
        $this->assertStringContainsString('$canEdit     = $updateUrl !== \'\';', $profil);
        $this->assertStringContainsString('const syncCropSave = () => {', $profil);
        $this->assertStringContainsString('const syncPasswordSave = () => {', $profil);
        $this->assertStringContainsString('pwForm.addEventListener(\'input\', syncPasswordSave)', $profil);
    }

    public function test_utilitas_tidak_mengunci_status_valid_setelah_gagal(): void
    {
        $utilitas = $this->sumber('resources/js/change-detection.js');

        $this->assertStringNotContainsString('state.valid = valid;', $utilitas);
        $this->assertStringContainsString('const hitungValid = () => state.valid &&', $utilitas);
    }
}
