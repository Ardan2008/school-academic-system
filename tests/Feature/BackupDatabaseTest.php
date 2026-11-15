<?php

namespace Tests\Feature;

use App\Models\BackupDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $adminAccount = null;

    protected function admin(): User
    {
        // Disimpan sekali per test supaya tidak bentrok dengan akun admin lain.
        return $this->adminAccount ??= User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'must_change_password' => false,
        ]);
    }

    /**
     * Panggil endpoint pembuatan backup dan kembalikan isinya.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function buatBackup(array $payload = []): TestResponse
    {
        return $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.backup-database.store'), $payload);
    }

    protected function isiSql(BackupDatabase $backup): string
    {
        return Storage::disk((string) config('backup.disk'))->get($backup->nama_file);
    }

    /**
     * Ambil data yang benar-benar dikirim ke browser, supaya yang diuji adalah
     * isi halaman yang dilihat admin, bukan hanya nilai di dalam controller.
     *
     * @return array<string, mixed>
     */
    protected function backupData(): array
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.backup-database.index'));

        $response->assertOk();

        $this->assertSame(1, preg_match(
            '/^\s*const data = (.+);$/m',
            (string) $response->getContent(),
            $match
        ), 'Payload backup tidak ditemukan di halaman Backup Database.');

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get(route('dashboard.admin.backup-database.index'))
            ->assertRedirect(route('login'));
    }

    public function test_pengguna_bukan_admin_ditolak(): void
    {
        $siswa = User::factory()->siswa('12-1')->create();

        $this->actingAs($siswa)
            ->get(route('dashboard.admin.backup-database.index'))
            ->assertForbidden();

        $this->actingAs($siswa)
            ->postJson(route('dashboard.admin.backup-database.store'))
            ->assertForbidden();
    }

    public function test_halaman_backup_bisa_diakses_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.backup-database.index'));

        $response->assertOk();
        $response->assertSee('Backup Database');
        $response->assertSee('Buat Backup Sekarang');
        $response->assertSee('Belum ada backup');
    }

    public function test_halaman_backup_tidak_berjanji_ada_fitur_pemulihan(): void
    {
        // Pemulihan dikerjakan lewat phpMyAdmin atau terminal, bukan lewat
        // aplikasi ini, jadi tidak boleh ada routenya maupun tombolnya.
        $this->assertFalse(Route::has('dashboard.admin.backup-database.restore'));

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.backup-database.index'));

        $response->assertOk();
        $response->assertDontSee('Pulihkan Database');
        $response->assertDontSee('restore');
        $response->assertDontSee('phpMyAdmin');
    }

    public function test_backup_baru_membuat_file_dan_metadata(): void
    {
        Storage::fake((string) config('backup.disk'));

        $response = $this->buatBackup(['catatan' => 'sebelum rapor genap']);

        $response->assertCreated();
        $response->assertJsonPath('status', 'success');

        $backup = BackupDatabase::query()->sole();

        $this->assertSame('sebelum rapor genap', $backup->catatan);
        $this->assertSame($this->admin()->id, $backup->dibuat_oleh);
        $this->assertSame('sqlite', $backup->driver);
        $this->assertGreaterThanOrEqual(0, $backup->durasi_detik);
        $this->assertGreaterThan(0, $backup->jumlah_tabel);
        $this->assertStringStartsWith('backups/backup-', $backup->nama_file);
        $this->assertStringEndsWith('.sql', $backup->nama_file);

        Storage::disk((string) config('backup.disk'))->assertExists($backup->nama_file);
        $this->assertSame(
            Storage::disk((string) config('backup.disk'))->size($backup->nama_file),
            $backup->ukuran_bytes
        );

        // Balasan untuk browser harus memuat baris yang sama dengan yang
        // tersimpan, supaya daftar di halaman tidak perlu dimuat ulang.
        $response->assertJsonPath('data.backup.nama', basename($backup->nama_file));
        $response->assertJsonPath('data.backup.catatan', 'sebelum rapor genap');
        $response->assertJsonPath('data.backup.pembuat', $this->admin()->name);
        $response->assertJsonPath('data.backup.ada_file', true);
    }

    public function test_catatan_kosong_disimpan_sebagai_null(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup(['catatan' => '   '])
            ->assertCreated()
            ->assertJsonPath('data.backup.catatan', null);

        $this->assertNull(BackupDatabase::query()->sole()->catatan);
    }

    public function test_catatan_terlalu_panjang_ditolak(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup(['catatan' => str_repeat('a', 192)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('catatan');

        $this->assertSame(0, BackupDatabase::query()->count());
    }

    public function test_file_dump_memuat_struktur_dan_data_seluruh_tabel(): void
    {
        Storage::fake((string) config('backup.disk'));

        User::factory()->admin()->create([
            'email' => 'kepsek@example.com',
            'name' => 'Okeiskeun Ngalupaeuk',
            'must_change_password' => false,
        ]);

        $this->buatBackup()->assertCreated();

        $isi = $this->isiSql(BackupDatabase::query()->sole());

        // Setiap tabel punya CREATE dan INSERT-nya sendiri.
        $this->assertStringContainsString('CREATE TABLE "users"', $isi);
        $this->assertStringContainsString('INSERT INTO "users" VALUES', $isi);
        $this->assertStringContainsString('CREATE TABLE "nilai"', $isi);

        // Nilai teks harus ikut tersimpan apa adanya, termasuk yang punya
        // tanda kutip di dalamnya.
        $this->assertStringContainsString('Okeiskeun Ngalupaeuk', $isi);
        $this->assertStringContainsString('kepsek@example.com', $isi);

        // Tabel sementara dan tabel riwayat backup tidak perlu ikut
        // dicadangkan. Riwayat backup sengaja dikecualikan supaya file yang
        // baru dibuat tidak memuat daftar file backup sebelumnya.
        $this->assertStringNotContainsString('CREATE TABLE "sessions"', $isi);
        $this->assertStringNotContainsString('CREATE TABLE "cache"', $isi);
        $this->assertStringNotContainsString('CREATE TABLE "backup_database"', $isi);

        // Perintah session harus sesuai driver yang dipakai.
        $this->assertStringContainsString('PRAGMA foreign_keys = OFF;', $isi);
        $this->assertStringContainsString('PRAGMA foreign_keys = ON;', $isi);
        $this->assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', $isi);
        $this->assertStringNotContainsString('SET NAMES', $isi);

        // Penanda selesai supaya file yang terpotong mudah dikenali.
        $this->assertStringContainsString('-- Selesai:', $isi);
    }

    public function test_dump_memuat_view_dan_trigger(): void
    {
        Storage::fake((string) config('backup.disk'));

        DB::statement('CREATE VIEW pengguna_aktif AS SELECT id, name FROM users WHERE deleted_at IS NULL');
        DB::statement(<<<'SQL'
            CREATE TRIGGER pengguna_terhapus AFTER UPDATE ON users
            FOR EACH ROW WHEN NEW.deleted_at IS NOT NULL
            BEGIN
                UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
            END
        SQL);

        $this->buatBackup()->assertCreated();

        $isi = $this->isiSql(BackupDatabase::query()->sole());

        $this->assertStringContainsString('CREATE VIEW pengguna_aktif', $isi);
        $this->assertStringContainsString('CREATE TRIGGER pengguna_terhapus', $isi);

        // Jumlah tabel tetap hanya menghitung tabel. View dan trigger punya
        // baris jumlah_tabel sendiri supaya angkanya tidak ikut bertambah.
        $dilewati = [
            'backup_database', 'cache', 'cache_locks',
            'jobs', 'failed_jobs', 'job_batches', 'sessions',
        ];

        $expected = collect(DB::select(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        ))
            ->pluck('name')
            ->reject(fn ($nama) => in_array($nama, $dilewati, true))
            ->count();

        $this->assertSame($expected, BackupDatabase::query()->sole()->jumlah_tabel);
        $this->assertGreaterThan(0, $expected);
    }

    public function test_dump_memuat_semua_tabel_bukan_hanya_yang_terakhir(): void
    {
        Storage::fake((string) config('backup.disk'));

        // Sengaja membuat banyak baris supaya isinya melewati batas ukuran
        // potongan yang dikirim ke disk, sekaligus mengisi tabel yang
        // namanya berada di bagian paling akhir daftar.
        User::factory()->count(600)->create();

        $this->buatBackup()->assertCreated();

        $isi = $this->isiSql(BackupDatabase::query()->sole());

        $this->assertStringContainsString('CREATE TABLE "users"', $isi);
        $this->assertStringContainsString('CREATE TABLE "nilai"', $isi);
        $this->assertSame(User::query()->count(), substr_count($isi, '@example'));
        $this->assertStringContainsString('-- Selesai:', $isi);
        $this->assertTrue(
            strrpos($isi, 'CREATE TABLE "users"') < strrpos($isi, '-- Selesai:'),
            'Isi file backup terpotong di tengah jalan.'
        );
    }

    public function test_backup_lama_dihapus_saat_jumlahnya_melebihi_batas(): void
    {
        Storage::fake((string) config('backup.disk'));
        config(['backup.keep' => 2]);

        for ($i = 1; $i <= 4; $i++) {
            $this->buatBackup(['catatan' => "ke-{$i}"])->assertCreated();

            // Nama file hanya unik sampai tingkat detik, jadi waktu pembuatan
            // dimajukan supaya setiap backup benar-benar terpisah.
            $this->travel(1)->seconds();
        }

        $tersisa = BackupDatabase::query()->orderBy('id')->pluck('catatan')->all();

        $this->assertSame(['ke-3', 'ke-4'], $tersisa);
        $this->assertCount(2, Storage::disk((string) config('backup.disk'))->allFiles(config('backup.folder')));
    }

    public function test_backup_yang_sedang_berjalan_menolak_permintaan_berikutnya(): void
    {
        Storage::fake((string) config('backup.disk'));
        config(['backup.lock_wait' => 0]);

        // Kunci dibuat dengan nama yang sama persis seperti yang dipakai
        // service, supaya benar-benar meniru permintaan yang sedang berjalan.
        Cache::lock('backup-database:sqlite:'.DB::connection()->getDatabaseName(), 60)->get();

        $this->buatBackup()
            ->assertStatus(409)
            ->assertJsonPath('status', 'error')
            ->assertJsonFragment(['Backup lain sedang berjalan. Tunggu sampai selesai, lalu coba lagi.']);

        $this->assertSame(0, BackupDatabase::query()->count());
    }

    public function test_kunci_backup_dilepas_setelah_backup_selesai(): void
    {
        Storage::fake((string) config('backup.disk'));
        config(['backup.lock_wait' => 0]);

        $this->buatBackup()->assertCreated();

        // Kalau kunci tidak dilepas, permintaan kedua akan ditolak.
        $this->buatBackup()->assertCreated();

        $this->assertSame(2, BackupDatabase::query()->count());
    }

    public function test_file_backup_bisa_diunduh(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.backup-database.download', $backup));

        $response->assertOk();
        $response->assertDownload(basename($backup->nama_file));
        $this->assertSame($this->isiSql($backup), $response->streamedContent());
    }

    public function test_unduh_backup_tidak_bisa_diakses_pengguna_lain(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();
        $siswa = User::factory()->siswa('12-1')->create();

        $this->actingAs($siswa)
            ->get(route('dashboard.admin.backup-database.download', $backup))
            ->assertForbidden();
    }

    public function test_unduh_memberi_pesan_jika_filenya_sudah_hilang(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();
        Storage::disk((string) config('backup.disk'))->delete($backup->nama_file);

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.backup-database.download', $backup))
            ->assertNotFound()
            ->assertJsonPath('status', 'error');
    }

    public function test_hapus_backup_menghapus_file_dan_riwayatnya(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();
        $path = $backup->nama_file;

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.backup-database.destroy', $backup))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $backup->id);

        $this->assertSame(0, BackupDatabase::query()->count());
        Storage::disk((string) config('backup.disk'))->assertMissing($path);
    }

    public function test_hapus_backup_tidak_bisa_diakses_pengguna_lain(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();
        $siswa = User::factory()->siswa('12-1')->create();

        $this->actingAs($siswa)
            ->deleteJson(route('dashboard.admin.backup-database.destroy', $backup))
            ->assertForbidden();

        $this->assertSame(1, BackupDatabase::query()->count());
    }

    public function test_riwayat_dengan_file_hilang_tetap_ditampilkan(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();
        Storage::disk((string) config('backup.disk'))->delete($backup->nama_file);

        // Barisnya tidak hilang sendiri, tapi ditandai supaya tombol unduhnya
        // bisa dimatikan.
        $data = $this->backupData();

        $this->assertCount(1, $data['backup']);
        $this->assertFalse($data['backup'][0]['ada_file']);
    }

    public function test_riwayat_yang_filenya_hilang_tetik_bisa_dihapus(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup()->assertCreated();

        $backup = BackupDatabase::query()->sole();
        Storage::disk((string) config('backup.disk'))->delete($backup->nama_file);

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.backup-database.destroy', $backup))
            ->assertOk();

        $this->assertSame(0, BackupDatabase::query()->count());
    }

    public function test_halaman_menampilkan_ringkasan_yang_cukup_untuk_admin(): void
    {
        Storage::fake((string) config('backup.disk'));
        config(['backup.keep' => 7]);

        $this->buatBackup(['catatan' => 'catatan uji'])->assertCreated();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.backup-database.index'));

        $response->assertOk();
        $response->assertSee('Total Backup');
        $response->assertSee('Total Ukuran');
        $response->assertSee('Backup Terakhir');
        $response->assertSee('Kapasitas Tersedia');
        $response->assertSee('catatan uji');
        $response->assertSee('data-info="maks">7<', false);

        $data = $this->backupData();

        // Nilai yang tampil di halaman harus sama dengan isi tabelnya.
        $this->assertSame(7, $data['maks']);
        $this->assertSame('sqlite', $data['driver']);
        $this->assertSame(':memory:', $data['database']);
        $this->assertCount(1, $data['backup']);
        $this->assertSame('catatan uji', $data['backup'][0]['catatan']);
        $this->assertGreaterThan(0, $data['backup'][0]['ukuran']);
    }

    public function test_waktu_backup_dikirim_dengan_penanda_zona_waktu(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup(['catatan' => 'cek zona waktu'])->assertCreated();

        $tanggal = $this->backupData()['backup'][0]['tanggal'];

        // Tanpa penanda zona waktu, browser mengira jam ini waktu lokal dan
        // selisih "x jam lalu" meleset sebesar selisih zona waktu.
        $this->assertNotFalse(
            Carbon::parse($tanggal)->getLastErrors() === false
                || Carbon::parse($tanggal)->getLastErrors()['warning_count'] === 0,
            "Waktu backup tidak bisa dibaca sebagai tanggal: {$tanggal}",
        );
        $this->assertMatchesRegularExpression(
            '/(Z|[+-]\d{2}:?\d{2})$/',
            $tanggal,
            "Waktu backup harus membawa penanda zona waktu, dapat: {$tanggal}",
        );

    }

    public function test_nama_file_berbeda_waktu_backup_berbeda(): void
    {
        Storage::fake((string) config('backup.disk'));

        $this->buatBackup(['catatan' => 'pertama'])->assertCreated();
        $this->travel(1)->seconds();
        $this->buatBackup(['catatan' => 'kedua'])->assertCreated();

        $nama = BackupDatabase::query()->orderBy('id')->pluck('nama_file')->all();

        $this->assertCount(2, array_unique($nama));
        $this->assertNotSame($nama[0], $nama[1]);
    }
}
