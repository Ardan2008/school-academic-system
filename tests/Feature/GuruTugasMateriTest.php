<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuruTugasMateriTest extends TestCase
{
    use RefreshDatabase;

    private const JURUSAN = 'Rekayasa Perangkat Lunak';

    private function guru(string $mapel = 'Matematika'): User
    {
        return User::factory()->guru()->create([
            'name' => 'Guru Pengampu',
            'mata_pelajaran' => $mapel,
            'must_change_password' => false,
        ]);
    }

    private function mapel(string $nama = 'Matematika'): MataPelajaran
    {
        return MataPelajaran::factory()->nama($nama)->create();
    }

    private function siswa(string $kelas = '12-1'): User
    {
        return User::factory()->siswa($kelas)->create([
            'jurusan' => self::JURUSAN,
            'name' => 'Siswa Uji',
            'status' => User::STATUS_AKTIF,
        ]);
    }

    private function jadwal(User $guru, MataPelajaran $mapel, string $kelas = '12-1'): JadwalPelajaran
    {
        return JadwalPelajaran::create([
            'kelas' => $kelas,
            'jurusan' => self::JURUSAN,
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);
    }

    private function tugas(User $guru, MataPelajaran $mapel, string $judul = 'Tugas Uji'): Tugas
    {
        return Tugas::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => $judul,
            'deskripsi' => 'Kerjakan dengan rapi.',
            'deadline' => now()->addDays(2),
        ]);
    }

    private function fileMateri(string $nama = 'Modul_Pembelajaran.pdf', string $mime = 'application/pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 12, $mime);
    }

    private function payloadMateri(MataPelajaran $mapel, array $overrides = []): array
    {
        return array_merge([
            'mata_pelajaran_id' => $mapel->id,
            'rombel' => '12-1|'.self::JURUSAN,
            'judul' => 'Materi Matriks',
            'deskripsi' => 'Rangkuman matriks.',
            'tanggal' => now()->toDateString(),
        ], $overrides);
    }

    private function materiDenganFile(User $guru, MataPelajaran $mapel, string $nama = 'Modul.pdf'): Materi
    {
        $path = 'materi/uji/'.Str::random(12).'.pdf';
        Storage::disk('local')->put($path, 'isi materi uji');

        return Materi::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Uji',
            'deskripsi' => 'Deskripsi materi uji.',
            'tanggal' => now()->toDateString(),
            'file_path' => $path,
            'nama_file' => $nama,
            'mime_type' => 'application/pdf',
            'ekstensi' => 'pdf',
            'ukuran_bytes' => 14,
        ]);
    }

    public function test_guru_membuat_dan_melihat_tugas_pada_kelas_yang_diajar(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Tambah Tugas')
            ->assertSee('data-tambah-tugas-trigger', false)
            ->assertSee('id="tugas-modal"', false)
            ->assertSee('lg:left-64', false)
            ->assertSee('inset-0', false)
            ->assertSee('data-tugas-close', false)
            ->assertSee('data-tugas-batal', false)
            ->assertSee('data-tugas-simpan', false);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.tugas.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'PR Aljabar',
                'deskripsi' => 'Kerjakan nomor 1 sampai 10.',
                'deadline' => now()->addDays(3)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']));

        $this->assertDatabaseHas('tugas', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'judul' => 'PR Aljabar',
        ]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('PR Aljabar');
    }

    public function test_mapel_tugas_materi_menjadi_dropdown_dan_hanya_menampilkan_yang_diampu(): void
    {
        $guru = $this->guru();
        $mapelSaya = $this->mapel();
        $this->mapel('Fisika');
        $this->siswa('12-1');
        $this->siswa('11-2');

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika')
            ->assertSee('Kelas 12-1')
            ->assertSee('Kelas 11-2')
            ->assertDontSee('<select id="tugas-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="tugas-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="tugas-mapel" name="mata_pelajaran_id" value="'.$mapelSaya->id.'">/', $html);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertDontSee('<select id="materi-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="materi-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="materi-mapel" name="mata_pelajaran_id" value="'.$mapelSaya->id.'">/', $html);
    }

    public function test_guru_tidak_dapat_menyimpan_tugas_atau_materi_dengan_mapel_tidak_diampu(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $this->mapel();
        $mapelLain = $this->mapel('Fisika');
        $this->siswa();

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.tugas.store'), [
                'mata_pelajaran_id' => $mapelLain->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Tugas Mapel Guru Lain',
            ])
            ->assertSessionHasErrors(['mata_pelajaran_id' => 'Anda tidak mengampu mata pelajaran ini.']);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.materi.store'), $this->payloadMateri($mapelLain, [
                'file' => $this->fileMateri(),
            ]))
            ->assertSessionHasErrors(['mata_pelajaran_id' => 'Anda tidak mengampu mata pelajaran ini.']);

        $this->assertDatabaseCount('tugas', 0);
        $this->assertDatabaseCount('materi', 0);
        Storage::disk('local')->assertDirectoryEmpty('materi');
    }

    public function test_guru_tidak_dapat_mengubah_tugas_atau_materi_ke_mapel_tidak_diampu(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapelSaya = $this->mapel();
        $mapelLain = $this->mapel('Fisika');
        $this->siswa();
        $tugas = $this->tugas($guru, $mapelSaya);
        $materi = $this->materiDenganFile($guru, $mapelSaya);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), [
                'mata_pelajaran_id' => $mapelLain->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Tugas Mapel Guru Lain',
            ])
            ->assertSessionHasErrors(['mata_pelajaran_id' => 'Anda tidak mengampu mata pelajaran ini.']);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.materi.update', $materi), $this->payloadMateri($mapelLain))
            ->assertSessionHasErrors(['mata_pelajaran_id' => 'Anda tidak mengampu mata pelajaran ini.']);

        $this->assertDatabaseHas('tugas', [
            'id' => $tugas->id,
            'mata_pelajaran_id' => $mapelSaya->id,
            'judul' => 'Tugas Uji',
        ]);
        $this->assertDatabaseHas('materi', [
            'id' => $materi->id,
            'mata_pelajaran_id' => $mapelSaya->id,
        ]);
    }

    public function test_edit_tugas_dan_materi_menampilkan_dropdown_dengan_mapel_tersimpan(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $tugas = $this->tugas($guru, $mapel, 'Tugas Uji');
        $materi = $this->materiDenganFile($guru, $mapel);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas', 'edit' => $tugas->id]))
            ->assertOk()
            ->assertSee('Edit Tugas')
            ->assertDontSee('<select id="tugas-edit-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="tugas-edit-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="tugas-edit-mapel" name="mata_pelajaran_id" value="'.$mapel->id.'">/', $html);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi', 'edit' => $materi->id]))
            ->assertOk()
            ->assertSee('Edit Materi')
            ->assertDontSee('<select id="materi-edit-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="materi-edit-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="materi-edit-mapel" name="mata_pelajaran_id" value="'.$mapel->id.'">/', $html);
    }

    public function test_guru_tidak_bisa_membuat_tugas_di_kelas_lain(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.tugas.store'), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '11-1|'.self::JURUSAN,
                'judul' => 'Tugas Kelas Lain',
                'deadline' => null,
            ])
            ->assertSessionHasErrors('rombel');

        $this->assertDatabaseCount('tugas', 0);
    }

    public function test_guru_tidak_bisa_mengubah_atau_menghapus_tugas_guru_lain(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru();
        $mapel = $this->mapel();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guruLain, $mapel, 'Tugas Guru Lain');

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Diubah',
            ])
            ->assertForbidden();

        $this->actingAs($guru, 'guru')
            ->delete(route('dashboard.guru.tugas-materi.tugas.destroy', $tugas))
            ->assertForbidden();

        $this->assertDatabaseHas('tugas', ['id' => $tugas->id, 'judul' => 'Tugas Guru Lain']);
    }

    public function test_hapus_tugas_mengembalikan_json_sukses_untuk_permintaan_ajax(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->deleteJson(route('dashboard.guru.tugas-materi.tugas.destroy', $tugas))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Tugas berhasil dihapus.')
            ->assertJsonPath('data.id', $tugas->id);

        $this->assertDatabaseMissing('tugas', ['id' => $tugas->id]);
    }

    public function test_halaman_tugas_materi_menyiapkan_konfirmasi_hapus_sweetalert(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guru, $mapel, 'Tugas Konfirmasi');
        $materi = $this->materiDenganFile($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']))
            ->assertOk()
            ->assertSee('data-delete-form', false)
            ->assertSee('data-delete-title="Hapus data tugas?"', false)
            ->assertDontSee('onsubmit="return confirm(', false);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('data-delete-title="Hapus data materi?"', false)
            ->assertDontSee('onsubmit="return confirm(', false);

        $this->assertDatabaseHas('tugas', ['id' => $tugas->id]);
        $this->assertDatabaseHas('materi', ['id' => $materi->id]);
    }

    public function test_hapus_lampiran_tugas_memberi_notifikasi_sukses(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $path = 'tugas/uji/'.Str::random(12).'.pdf';
        Storage::disk('local')->put($path, 'isi lampiran uji');

        $tugas = Tugas::create([
            'kelas' => '12-1',
            'jurusan' => self::JURUSAN,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Tugas Berlampiran',
            'deskripsi' => 'Kerjakan dengan rapi.',
            'deadline' => now()->addDays(2),
            'file_path' => $path,
            'nama_file' => 'Lampiran.pdf',
            'mime_type' => 'application/pdf',
            'ekstensi' => 'pdf',
            'ukuran_bytes' => 16,
        ]);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), [
                'mata_pelajaran_id' => $mapel->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Tugas Berlampiran',
                'deskripsi' => 'Kerjakan dengan rapi.',
                'hapus_lampiran' => 1,
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas']))
            ->assertSessionHas('delete_success', 'Lampiran tugas berhasil dihapus.');

        $this->assertDatabaseHas('tugas', ['id' => $tugas->id, 'file_path' => null]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_guru_dapat_mengelola_materi(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.materi.store'), $this->payloadMateri($mapel, [
                'file' => $this->fileMateri(),
            ]))
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $materi = Materi::query()->firstOrFail();

        $this->assertSame($guru->id, $materi->guru_id);
        $this->assertSame('Materi Matriks', $materi->judul);
        $this->assertSame('pdf', $materi->ekstensi);
        $this->assertSame('application/pdf', $materi->mime_type);
        $this->assertNotNull($materi->file_path);
        Storage::disk('local')->assertExists($materi->file_path);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.materi.update', $materi), $this->payloadMateri($mapel, [
                'judul' => 'Materi Matriks Revisi',
                'deskripsi' => 'Rangkuman matriks revisi.',
            ]))
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $this->assertDatabaseHas('materi', [
            'id' => $materi->id,
            'judul' => 'Materi Matriks Revisi',
            'file_path' => $materi->file_path,
        ]);

        $this->actingAs($guru, 'guru')
            ->delete(route('dashboard.guru.tugas-materi.materi.destroy', $materi))
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $this->assertDatabaseMissing('materi', ['id' => $materi->id]);
        Storage::disk('local')->assertMissing($materi->file_path);
    }

    public function test_hapus_materi_mengembalikan_json_sukses_untuk_permintaan_ajax(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $materi = $this->materiDenganFile($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->deleteJson(route('dashboard.guru.tugas-materi.materi.destroy', $materi))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Materi berhasil dihapus.')
            ->assertJsonPath('data.id', $materi->id);

        $this->assertDatabaseMissing('materi', ['id' => $materi->id]);
    }

    public function test_guru_tidak_dapat_menyimpan_materi_tanpa_file(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.materi.store'), $this->payloadMateri($mapel))
            ->assertSessionHasErrors(['file' => 'File materi wajib diunggah.']);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('id="materi-tambah-modal"', false)
            ->assertSee('data-open="true"', false);

        $this->assertDatabaseCount('materi', 0);
        Storage::disk('local')->assertDirectoryEmpty('materi');
    }

    public function test_guru_tidak_dapat_mengunggah_file_melebih_batas_20_mb(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.materi.store'), $this->payloadMateri($mapel, [
                'file' => UploadedFile::fake()->create('Besar.pdf', 20481, 'application/pdf'),
            ]))
            ->assertSessionHasErrors(['file' => 'Ukuran file maksimal 20 MB.']);

        $this->assertDatabaseCount('materi', 0);
        Storage::disk('local')->assertDirectoryEmpty('materi');
    }

    public function test_guru_tidak_dapat_mengunggah_format_tidak_didukung(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->post(route('dashboard.guru.tugas-materi.materi.store'), $this->payloadMateri($mapel, [
                'file' => UploadedFile::fake()->create('virus.exe', 12, 'application/octet-stream'),
            ]))
            ->assertSessionHasErrors(['file' => 'Format file tidak didukung.']);

        $this->assertDatabaseCount('materi', 0);
        Storage::disk('local')->assertDirectoryEmpty('materi');
    }

    public function test_form_materi_menyiapkan_informasi_dan_pratinjau_file(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $materi = $this->materiDenganFile($guru, $mapel, 'Modul Utama.pdf');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('data-tambah-materi-trigger', false)
            ->assertSee('id="materi-tambah-modal"', false)
            ->assertSee('data-materi-form-backdrop', false)
            ->assertSee('data-materi-form-batal', false)
            ->assertSee('data-materi-form-simpan-label', false)
            ->assertSee('lg:left-64', false)
            ->assertSee('overflow-y-auto', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('Modul Utama.pdf')
            ->assertSee('PDF')
            ->assertSee('14 B');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi', 'edit' => $materi->id]))
            ->assertOk()
            ->assertSee('data-materi-file-info', false)
            ->assertSee('data-materi-ganti', false)
            ->assertSee('data-materi-batal', false)
            ->assertSee('data-materi-preview', false);
    }

    public function test_guru_mengganti_file_materi_dan_file_lama_dihapus(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $materi = $this->materiDenganFile($guru, $mapel);
        $pathLama = $materi->file_path;

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.materi.update', $materi), $this->payloadMateri($mapel, [
                'file' => $this->fileMateri('Rangkuman Baru.pdf'),
            ]))
            ->assertRedirect(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']));

        $materi->refresh();

        $this->assertNotSame($pathLama, $materi->file_path);
        $this->assertSame('Rangkuman Baru.pdf', $materi->nama_file);
        Storage::disk('local')->assertMissing($pathLama);
        Storage::disk('local')->assertExists($materi->file_path);
    }

    public function test_guru_dapat_melihat_detail_dan_file_materi_miliknya(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $materi = $this->materiDenganFile($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->getJson(route('dashboard.guru.tugas-materi.materi.show', $materi))
            ->assertOk()
            ->assertJsonPath('detail.nama_file', 'Modul.pdf')
            ->assertJsonPath('detail.format', 'PDF')
            ->assertJsonPath('detail.ukuran', '14 B')
            ->assertJsonPath('detail.bisa_dipratinjau', true);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.materi.preview', $materi))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.materi.unduh', $materi))
            ->assertDownload('Modul.pdf');
    }

    public function test_guru_tidak_dapat_mengelola_materi_guru_lain_termasuk_file(): void
    {
        Storage::fake('local');

        $pemilik = $this->guru();
        $guruLain = $this->guru('Fisika');
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($pemilik, $mapel);
        $materi = $this->materiDenganFile($pemilik, $mapel);
        $path = $materi->file_path;

        $this->actingAs($guruLain, 'guru')
            ->getJson(route('dashboard.guru.tugas-materi.materi.show', $materi))
            ->assertForbidden();
        $this->actingAs($guruLain, 'guru')
            ->get(route('dashboard.guru.tugas-materi.materi.preview', $materi))
            ->assertForbidden();
        $this->actingAs($guruLain, 'guru')
            ->get(route('dashboard.guru.tugas-materi.materi.unduh', $materi))
            ->assertForbidden();
        $this->actingAs($guruLain, 'guru')
            ->put(route('dashboard.guru.tugas-materi.materi.update', $materi), $this->payloadMateri($mapel))
            ->assertForbidden();
        $this->actingAs($guruLain, 'guru')
            ->delete(route('dashboard.guru.tugas-materi.materi.destroy', $materi))
            ->assertForbidden();

        $this->assertDatabaseHas('materi', ['id' => $materi->id, 'file_path' => $path]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_guru_melihat_pengumpulan_dan_menyimpan_nilai(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $siswa = $this->siswa();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guru, $mapel);

        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'jawaban' => 'Jawaban siswa uji',
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.pengumpulan', $tugas))
            ->assertOk()
            ->assertSee('Siswa Uji')
            ->assertSee('Jawaban siswa uji')
            ->assertSee('Dikumpulkan');

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.pengumpulan.update', $pengumpulan), [
                'nilai' => 92.5,
                'umpan_balik' => 'Langkah kedua perlu diperjelas.',
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.pengumpulan', $tugas));

        $this->assertDatabaseHas('pengumpulan_tugas', [
            'id' => $pengumpulan->id,
            'nilai' => 92.5,
            'umpan_balik' => 'Langkah kedua perlu diperjelas.',
            'status' => PengumpulanTugas::STATUS_DINILAI,
        ]);
    }

    public function test_guru_tidak_bisa_membuka_pengumpulan_tugas_guru_lain(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru();
        $mapel = $this->mapel();
        $tugas = $this->tugas($guruLain, $mapel);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.pengumpulan', $tugas))
            ->assertForbidden();
    }

    public function test_guru_tidak_bisa_menilai_pengumpulan_guru_lain(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru();
        $mapel = $this->mapel();
        $siswa = $this->siswa();
        $tugas = $this->tugas($guruLain, $mapel);

        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.pengumpulan.update', $pengumpulan), [
                'nilai' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('pengumpulan_tugas', [
            'id' => $pengumpulan->id,
            'nilai' => null,
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);
    }

    public function test_validasi_tambah_tugas_membuka_modal_dengan_pesan_error(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->from(route('dashboard.guru.tugas-materi.index'))
            ->post(route('dashboard.guru.tugas-materi.tugas.store'), [
                'mata_pelajaran_id' => '',
                'rombel' => '',
                'judul' => '',
            ])
            ->assertRedirect(route('dashboard.guru.tugas-materi.index'));

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('data-open="true"', false)
            ->assertSee('Judul tugas wajib diisi.')
            ->assertSee('Mata pelajaran wajib dipilih.');
    }

    public function test_edit_tugas_tetap_memakai_form_sebelumnya(): void
    {
        $guru = $this->guru();
        $mapel = $this->mapel();
        $this->siswa();
        $this->jadwal($guru, $mapel);
        $tugas = $this->tugas($guru, $mapel);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'tugas', 'edit' => $tugas->id]))
            ->assertOk()
            ->assertSee('Edit Tugas')
            ->assertDontSee('id="tugas-modal"', false);
    }

    public function test_guru_tanpa_mapel_melihat_peringatan_bukan_form(): void
    {
        $guru = User::factory()->guru()->create([
            'mata_pelajaran' => null,
            'must_change_password' => false,
        ]);

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Mata Pelajaran Belum Ditugaskan')
            ->assertSee('Belum ada mata pelajaran yang ditugaskan')
            ->assertSee('Penyimpanan tugas belum dapat dilakukan.');

        $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('Penyimpanan materi belum dapat dilakukan.');
    }

    public function test_filter_mapel_menentukan_pilihan_dropdown_pada_form_tambah(): void
    {
        $guru = $this->guru();
        $this->mapel();
        $mapelKedua = MataPelajaran::factory()->nama('Matematika')->kode('MP-KEDUA')->create();
        $this->siswa();

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['mapel_id' => $mapelKedua->id]))
            ->assertOk()
            ->assertDontSee('<select id="tugas-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="tugas-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="tugas-mapel" name="mata_pelajaran_id" value="'.$mapelKedua->id.'">/', $html);

        $html = $this->actingAs($guru, 'guru')
            ->get(route('dashboard.guru.tugas-materi.index', ['tab' => 'materi', 'mapel_id' => $mapelKedua->id]))
            ->assertOk()
            ->assertDontSee('<select id="materi-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="materi-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="materi-mapel" name="mata_pelajaran_id" value="'.$mapelKedua->id.'">/', $html);
    }

    public function test_guru_dapat_mengubah_mapel_tugas_dan_materi_ke_mapel_lain_yang_diampu(): void
    {
        Storage::fake('local');

        $guru = $this->guru();
        $mapelTersimpan = $this->mapel();
        $mapelLain = MataPelajaran::factory()->nama('Matematika')->kode('MP-LAIN')->create();
        $this->siswa();
        $tugas = $this->tugas($guru, $mapelTersimpan);
        $materi = $this->materiDenganFile($guru, $mapelTersimpan);

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.tugas.update', $tugas), [
                'mata_pelajaran_id' => $mapelLain->id,
                'rombel' => '12-1|'.self::JURUSAN,
                'judul' => 'Tugas Diubah',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($guru, 'guru')
            ->put(route('dashboard.guru.tugas-materi.materi.update', $materi), $this->payloadMateri($mapelLain, [
                'judul' => 'Materi Diubah',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tugas', [
            'id' => $tugas->id,
            'mata_pelajaran_id' => $mapelLain->id,
            'judul' => 'Tugas Diubah',
        ]);
        $this->assertDatabaseHas('materi', [
            'id' => $materi->id,
            'mata_pelajaran_id' => $mapelLain->id,
            'judul' => 'Materi Diubah',
        ]);
    }
}
