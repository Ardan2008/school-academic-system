<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Halaman Tugas & Materi siswa harus membaca tabel `tugas`, `materi`, dan
 * `pengumpulan_tugas` apa adanya: hanya untuk rombel siswa yang login, status
 * pengerjaan dihitung dari pengumpulan, dan tetap memberi keterangan yang jelas
 * saat data belum ada.
 */
class SiswaTugasMateriTest extends TestCase
{
    use RefreshDatabase;

    private function buatTugas(
        MataPelajaran $mapel,
        ?User $guru,
        string $kelas,
        ?string $jurusan,
        string $judul,
        ?string $deadline = null,
    ): Tugas {
        return Tugas::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru?->id,
            'judul' => $judul,
            'deskripsi' => 'Kerjakan dengan rapi.',
            'deadline' => $deadline,
        ]);
    }

    private function buatMateriBerkas(
        MataPelajaran $mapel,
        ?User $guru,
        string $kelas = '12-1',
        ?string $jurusan = 'Rekayasa Perangkat Lunak',
        string $nama = 'Modul.pdf',
        string $mime = 'application/pdf',
        string $ekstensi = 'pdf',
        string $judul = 'Materi Berkas',
    ): Materi {
        $path = 'materi/uji/'.Str::random(12).'.'.$ekstensi;
        Storage::disk('local')->put($path, 'isi materi uji');

        return Materi::create([
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru?->id,
            'judul' => $judul,
            'deskripsi' => 'Materi lengkap dengan berkas.',
            'tanggal' => now()->toDateString(),
            'file_path' => $path,
            'nama_file' => $nama,
            'mime_type' => $mime,
            'ekstensi' => $ekstensi,
            'ukuran_bytes' => 14,
        ]);
    }

    public function test_siswa_hanya_melihat_tugas_dan_materi_kelasnya_sendiri(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $mapelLain = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $guru = User::factory()->guru()->create(['name' => 'Budi Guru']);

        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'PR Matematika', now()->addDays(3)->toDateTimeString());
        // Rombel lain tidak boleh ikut tampil.
        $this->buatTugas($mapelLain, $guru, '11-2', 'Multimedia', 'PR Fisika', now()->addDays(3)->toDateTimeString());

        Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Matriks',
            'deskripsi' => 'Rangkuman matriks.',
            'tanggal' => now()->subDay(),
        ]);
        Materi::create([
            'kelas' => '11-2',
            'jurusan' => 'Multimedia',
            'mata_pelajaran_id' => $mapelLain->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Optik',
            'deskripsi' => 'Rangkuman optik.',
            'tanggal' => now()->subDay(),
        ]);

        // Tab tugas (default).
        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));
        $res->assertOk();
        $res->assertSee('PR Matematika');
        $res->assertSee('Budi Guru');
        $res->assertDontSee('PR Fisika');

        // Tab materi.
        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', ['tab' => 'materi']));
        $res->assertOk();
        $res->assertSee('Materi Matriks');
        $res->assertDontSee('Materi Optik');
        $res->assertDontSee('PR Matematika');
    }

    public function test_status_tugas_dihitung_dari_pengumpulan_dan_tenggat(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $dikumpulkan = $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Sudah Kumpul', now()->addDays(2)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(4)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Terlambat', now()->subDays(2)->toDateTimeString());

        PengumpulanTugas::create([
            'tugas_id' => $dikumpulkan->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Tugas Sudah Kumpul');
        $res->assertSee('Tugas Menunggu');
        $res->assertSee('Tugas Terlambat');
        $res->assertSee('Dikumpulkan');
        $res->assertSee('Menunggu');
        $res->assertSee('Terlambat');
    }

    public function test_filter_status_hanya_menampilkan_tugas_yang_sesuai(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(4)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Terlambat', now()->subDays(2)->toDateTimeString());

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'terlambat',
        ]));

        $res->assertOk();
        $res->assertSee('Tugas Terlambat');
        $res->assertDontSee('Tugas Menunggu');
    }

    public function test_status_di_luar_daftar_diabaikan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(4)->toDateTimeString());

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'ngawur',
        ]));

        $res->assertOk();
        $res->assertSee('Tugas Menunggu');
    }

    public function test_tugas_tanpa_jurusan_berlaku_untuk_semua_jurusan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, null, '12-1', null, 'Tugas Umum', now()->addDays(3)->toDateTimeString());

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Tugas Umum');
    }

    public function test_siswa_tanpa_data_melihat_keterangan_belum_ada(): void
    {
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Belum Ada Tugas & Materi');
    }

    public function test_siswa_tanpa_kelas_melihat_peringatan_data_kelas(): void
    {
        $siswa = User::factory()->siswa()->create(['kelas' => null, 'jurusan' => null]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        $res->assertSee('Data Kelas Belum Diatur');
    }

    public function test_pencarian_tugas_dan_materi_mempertahankan_query_di_tab_dan_status(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'PR Aljabar', now()->addDays(3)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'PR Geometri', now()->addDays(3)->toDateTimeString());
        $this->buatTugas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Latihan Aljabar', now()->subDay()->toDateTimeString());

        Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Aljabar',
            'deskripsi' => 'Rangkuman aljabar.',
            'tanggal' => now()->subDay(),
        ]);
        Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Materi Optik',
            'deskripsi' => 'Rangkuman optik.',
            'tanggal' => now()->subDay(),
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'q' => 'aljabar',
        ]));

        $res->assertOk();
        $res->assertSee('PR Aljabar');
        $res->assertDontSee('PR Geometri');
        $res->assertSee('value="aljabar"', false);
        $res->assertSee('q=aljabar');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'materi',
            'q' => 'aljabar',
        ]));

        $res->assertOk();
        $res->assertSee('Materi Aljabar');
        $res->assertDontSee('Materi Optik');
        $res->assertSee('value="aljabar"', false);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'menunggu',
            'q' => 'aljabar',
        ]));

        $res->assertOk();
        $res->assertSee('PR Aljabar');
        $res->assertDontSee('Latihan Aljabar');
        $res->assertSee('q=aljabar');
        $res->assertSee('status=menunggu');

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'materi',
            'q' => 'tidakada',
        ]));

        $res->assertOk();
        $res->assertSee('Materi Tidak Ditemukan');
        $res->assertSee('Tidak ada materi yang cocok dengan pencarian', false);
        $res->assertSee('Tampilkan Semua Materi');
    }

    public function test_deskripsi_panjang_tidak_dipotong_di_server(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create(['jurusan' => 'Rekayasa Perangkat Lunak']);

        // Lebih dari 80 karakter supaya potongan lama ("...") sampai akhir teks.
        $deskripsi = str_repeat('Kerjakan dengan teliti. ', 12).'PENUTUP_UNIK';

        Tugas::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'judul' => 'Tugas Deskripsi Panjang',
            'deskripsi' => $deskripsi,
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk();
        // Teks lengkap tetap ada di HTML (hanya dipotong secara visual via clamp; tooltip muncul bila terpotong).
        $res->assertSee('PENUTUP_UNIK');
        $res->assertSee('data-truncate-tip', false);
        $res->assertSee('line-clamp-2', false);
        $res->assertDontSee('Lihat selengkapnya');
    }

    public function test_siswa_dapat_mengumpulkan_tugas_dan_melihat_nilai_serta_umpan_balik(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Pengampu']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $tugas = $this->buatTugas(
            $mapel,
            $guru,
            '12-1',
            'Rekayasa Perangkat Lunak',
            'PR Aljabar',
            now()->addDays(2)->toDateTimeString(),
        );

        $this->actingAs($siswa)
            ->post(route('dashboard.siswa.tugas-materi.pengumpulan.store', $tugas), [
                'jawaban' => 'Jawaban akhir siswa',
            ])
            ->assertRedirect(route('dashboard.siswa.tugas-materi.index', ['tab' => 'tugas']))
            ->assertSessionHas('success', 'Tugas berhasil dikumpulkan.');

        $pengumpulan = PengumpulanTugas::query()->firstOrFail();

        $this->assertSame($tugas->id, $pengumpulan->tugas_id);
        $this->assertSame($siswa->id, $pengumpulan->user_id);
        $this->assertSame('Jawaban akhir siswa', $pengumpulan->jawaban);
        $this->assertSame(PengumpulanTugas::STATUS_DIKUMPULKAN, $pengumpulan->status);

        $pengumpulan->update([
            'nilai' => 92.5,
            'umpan_balik' => 'Kerjakan langkah kedua lebih rapi.',
            'status' => PengumpulanTugas::STATUS_DINILAI,
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk()
            ->assertSee('Dinilai')
            ->assertSee('Jawaban akhir siswa')
            ->assertSee('92,5')
            ->assertSee('Umpan balik: Kerjakan langkah kedua lebih rapi.');
    }

    public function test_siswa_kelas_lain_tidak_dapat_mengumpulkan_tugas(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('11-2')->create([
            'jurusan' => 'Multimedia',
            'status' => User::STATUS_AKTIF,
        ]);
        $tugas = $this->buatTugas(
            $mapel,
            null,
            '12-1',
            'Rekayasa Perangkat Lunak',
            'PR Kelas Lain',
            now()->addDays(2)->toDateTimeString(),
        );

        $this->actingAs($siswa)
            ->post(route('dashboard.siswa.tugas-materi.pengumpulan.store', $tugas), [
                'jawaban' => 'Tidak boleh masuk',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('pengumpulan_tugas', 0);
    }

    public function test_filter_dikumpulkan_menampilkan_tugas_yang_sudah_dikumpulkan(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $sudah = $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Sudah Kumpul', now()->addDays(2)->toDateTimeString());
        $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Menunggu', now()->addDays(3)->toDateTimeString());

        $this->actingAs($siswa)
            ->post(route('dashboard.siswa.tugas-materi.pengumpulan.store', $sudah), [
                'jawaban' => 'Sudah dikumpulkan',
            ])
            ->assertRedirect();

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index', [
            'tab' => 'tugas',
            'status' => 'dikumpulkan',
        ]));

        $res->assertOk()
            ->assertSee('Tugas Sudah Kumpul')
            ->assertDontSee('Tugas Menunggu');
    }

    public function test_mengumpulkan_ulang_mempertahankan_nilai_dan_umpan_balik_guru(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $tugas = $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'PR Revisi', now()->addDays(2)->toDateTimeString());

        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subDay(),
            'jawaban' => 'Jawaban lama',
            'status' => PengumpulanTugas::STATUS_DINILAI,
            'nilai' => 80,
            'umpan_balik' => 'Umpan balik lama',
        ]);

        $this->actingAs($siswa)
            ->post(route('dashboard.siswa.tugas-materi.pengumpulan.store', $tugas), [
                'jawaban' => 'Jawaban revisi',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pengumpulan_tugas', [
            'id' => $pengumpulan->id,
            'jawaban' => 'Jawaban revisi',
            'status' => PengumpulanTugas::STATUS_DINILAI,
            'nilai' => 80,
            'umpan_balik' => 'Umpan balik lama',
        ]);
        $this->assertDatabaseCount('pengumpulan_tugas', 1);
    }

    public function test_siswa_dapat_melihat_detail_tugas_dari_database(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Detail']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $tugas = $this->buatTugas(
            $mapel,
            $guru,
            '12-1',
            'Rekayasa Perangkat Lunak',
            'Tugas Detail',
            now()->addDays(2)->toDateTimeString(),
        );
        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'jawaban' => 'Jawaban detail',
            'status' => PengumpulanTugas::STATUS_DINILAI,
            'nilai' => 87.5,
            'umpan_balik' => 'Umpan balik detail',
        ]);

        $res = $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.tugas.show', $tugas));

        $res->assertOk()
            ->assertJsonPath('detail.id', $tugas->id)
            ->assertJsonPath('detail.judul', 'Tugas Detail')
            ->assertJsonPath('detail.mata_pelajaran', 'Matematika')
            ->assertJsonPath('detail.guru', 'Guru Detail')
            ->assertJsonPath('detail.kelas', 'Kelas 12-1 - Rekayasa Perangkat Lunak')
            ->assertJsonPath('detail.deskripsi', 'Kerjakan dengan rapi.')
            ->assertJsonPath('detail.status', 'Dinilai')
            ->assertJsonPath('detail.status_kode', 'dikumpulkan')
            ->assertJsonPath('detail.sudah_dikumpulkan', true)
            ->assertJsonPath('detail.jawaban', 'Jawaban detail')
            ->assertJsonPath('detail.nilai', '87,5')
            ->assertJsonPath('detail.umpan_balik', 'Umpan balik detail')
            ->assertJsonPath('detail.action', route('dashboard.siswa.tugas-materi.pengumpulan.store', $tugas));

        $this->assertNotNull($pengumpulan->dikumpulkan_at);
    }

    public function test_siswa_kelas_lain_tidak_dapat_melihat_detail_tugas(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('11-2')->create([
            'jurusan' => 'Multimedia',
            'status' => User::STATUS_AKTIF,
        ]);
        $tugas = $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Rahasia');

        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.tugas.show', $tugas))
            ->assertNotFound();
    }

    public function test_halaman_tugas_menyediakan_pemicu_detail_dan_konfirmasi_kumpulkan_ulang(): void
    {
        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $tugas = $this->buatTugas($mapel, null, '12-1', 'Rekayasa Perangkat Lunak', 'Tugas Modal');
        PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'user_id' => $siswa->id,
            'dikumpulkan_at' => now()->subHour(),
            'jawaban' => 'Jawaban lama',
            'status' => PengumpulanTugas::STATUS_DIKUMPULKAN,
        ]);

        $res = $this->actingAs($siswa)->get(route('dashboard.siswa.tugas-materi.index'));

        $res->assertOk()
            ->assertSee('data-detail-trigger', false)
            ->assertSee('data-detail-url="'.route('dashboard.siswa.tugas-materi.tugas.show', $tugas).'"', false)
            ->assertSee('data-submit-trigger', false)
            ->assertSee('data-sudah="1"', false)
            ->assertSee('Kumpulkan Ulang')
            ->assertSee('id="detail-tugas-modal"', false)
            ->assertSee('id="pengumpulan-modal"', false)
            ->assertSee('Batal')
            ->assertSee('Belum ada lampiran dari Guru.');
    }

    public function test_siswa_melihat_informasi_file_materi_pada_daftar(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $this->buatMateriBerkas($mapel, $guru, '12-1', 'Rekayasa Perangkat Lunak', 'Modul Utama.pdf');

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('Materi Berkas')
            ->assertSee('PDF')
            ->assertSee('14 B')
            ->assertSee('data-materi-detail-frame', false)
            ->assertSee('data-detail-materi-trigger', false);
    }

    public function test_siswa_melihat_detail_materi_dengan_informasi_file_lengkap(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $materi = $this->buatMateriBerkas($mapel, $guru);

        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.materi.show', $materi))
            ->assertOk()
            ->assertJsonPath('detail.judul', 'Materi Berkas')
            ->assertJsonPath('detail.mata_pelajaran', 'Matematika')
            ->assertJsonPath('detail.guru', 'Guru Materi')
            ->assertJsonPath('detail.kelas', 'Kelas 12-1 - Rekayasa Perangkat Lunak')
            ->assertJsonPath('detail.deskripsi', 'Materi lengkap dengan berkas.')
            ->assertJsonPath('detail.nama_file', 'Modul.pdf')
            ->assertJsonPath('detail.format', 'PDF')
            ->assertJsonPath('detail.ukuran', '14 B')
            ->assertJsonPath('detail.bisa_dipratinjau', true);
    }

    public function test_siswa_dapat_membuka_pdf_melalui_pdf_viewer(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $materi = $this->buatMateriBerkas($mapel, $guru);

        $response = $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.preview', $materi));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_siswa_dapat_melihat_pratinjau_gambar(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $materi = $this->buatMateriBerkas(
            $mapel,
            $guru,
            '12-1',
            'Rekayasa Perangkat Lunak',
            'Diagram.png',
            'image/png',
            'png',
        );

        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.materi.show', $materi))
            ->assertOk()
            ->assertJsonPath('detail.format', 'PNG')
            ->assertJsonPath('detail.bisa_dipratinjau', true);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.preview', $materi))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_siswa_dapat_mengunduh_file_materi(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $materi = $this->buatMateriBerkas($mapel, $guru);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.unduh', $materi))
            ->assertOk()
            ->assertDownload('Modul.pdf');
    }

    public function test_format_tanpa_pratinjau_langsung_tetap_dapat_diunduh(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $materi = $this->buatMateriBerkas(
            $mapel,
            $guru,
            '12-1',
            'Rekayasa Perangkat Lunak',
            'Kumpulan.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'docx',
        );

        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.materi.show', $materi))
            ->assertOk()
            ->assertJsonPath('detail.format', 'DOCX')
            ->assertJsonPath('detail.bisa_dipratinjau', false);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.preview', $materi))
            ->assertOk()
            ->assertDownload('Kumpulan.docx');
    }

    public function test_siswa_tidak_dapat_mengakses_materi_kelas_lain_melalui_url(): void
    {
        Storage::fake('local');

        $mapel = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);
        $materi = $this->buatMateriBerkas($mapel, $guru, '11-2', 'Multimedia');

        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.materi.show', $materi))
            ->assertNotFound();
        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.preview', $materi))
            ->assertNotFound();
        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.unduh', $materi))
            ->assertNotFound();
    }

    public function test_siswa_hanya_melihat_materi_untuk_mata_pelajaran_dalam_jadwalnya(): void
    {
        Storage::fake('local');

        $mapelDiajar = MataPelajaran::factory()->create(['kode' => 'MTK', 'nama' => 'Matematika']);
        $mapelLain = MataPelajaran::factory()->create(['kode' => 'FIS', 'nama' => 'Fisika']);
        $guru = User::factory()->guru()->create(['name' => 'Guru Materi']);
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'status' => User::STATUS_AKTIF,
        ]);

        JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapelDiajar->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);

        $materiSesuai = $this->buatMateriBerkas(
            $mapelDiajar,
            $guru,
            judul: 'Materi Matematika',
        );
        $materiLuarJadwal = $this->buatMateriBerkas(
            $mapelLain,
            $guru,
            judul: 'Materi Fisika',
        );

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('Materi Matematika')
            ->assertDontSee('Materi Fisika');

        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.materi.show', $materiSesuai))
            ->assertOk();
        $this->actingAs($siswa)
            ->getJson(route('dashboard.siswa.tugas-materi.materi.show', $materiLuarJadwal))
            ->assertNotFound();
    }
}
