<?php

namespace Tests\Feature;

use App\Models\MataPelajaran;
use App\Models\Notification;
use App\Models\Pengumuman;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status "sudah dibaca" notifikasi harus benar-benar persisten di kolom
 * read_at milik barisnya sendiri: tidak ada notifikasi yang terhapus,
 * jumlah badge selalu dihitung ulang dari database, dan tab filter
 * Semua / Belum Dibaca / Sudah Dibaca mengembalikan data yang konsisten.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clear();
    }

    private function siswaDuaPengumuman(): array
    {
        $siswa = User::factory()->siswa('12-1')->create([
            'jurusan' => 'Rekayasa Perangkat Lunak',
        ]);

        $p1 = Pengumuman::create([
            'judul' => 'Pengumuman Pertama',
            'isi' => 'Isi pengumuman pertama.',
            'kelas' => null,
            'jurusan' => null,
            'publish_at' => now()->subHours(2),
        ]);

        $p2 = Pengumuman::create([
            'judul' => 'Pengumuman Kedua',
            'isi' => 'Isi pengumuman kedua.',
            'kelas' => null,
            'jurusan' => null,
            'publish_at' => now()->subHour(),
        ]);

        return [$siswa, $p1, $p2];
    }

    // Filter Semua / Belum Dibaca / Sudah Dibaca

    public function test_daftar_notifikasi_menyertakan_jumlah_per_filter(): void
    {
        [$siswa] = $this->siswaDuaPengumuman();

        $data = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk()
            ->json();

        $this->assertCount(2, $data['notifications']);
        $this->assertSame(2, $data['unread_count']);
        $this->assertSame(['all' => 2, 'unread' => 2, 'read' => 0], $data['counts']);
        $this->assertSame('all', $data['filter']);
    }

    public function test_filter_unread_read_dan_semua_mengembalikan_baris_yang_tepat(): void
    {
        [$siswa, $p1] = $this->siswaDuaPengumuman();

        // Satu pengumuman ditandai dibaca lewat alur klik item.
        $notifikasiPertama = Notification::where('user_id', $siswa->id)
            ->where('reference_key', 'pengumuman:'.$p1->id)
            ->firstOrFail();

        $this->actingAs($siswa)
            ->postJson(route('dashboard.notifications.read', ['role' => 'siswa', 'id' => $notifikasiPertama->id]))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 1]);

        // Filter default (Semua) memuat keduanya.
        $semua = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk()
            ->json();

        $this->assertCount(2, $semua['notifications']);
        $this->assertSame(['all' => 2, 'unread' => 1, 'read' => 1], $semua['counts']);

        // Filter Belum Dibaca hanya baris tanpa read_at.
        $belum = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', ['role' => 'siswa', 'filter' => 'unread']))
            ->assertOk()
            ->json();

        $this->assertSame('unread', $belum['filter']);
        $this->assertCount(1, $belum['notifications']);
        $this->assertFalse($belum['notifications'][0]['is_read']);
        $this->assertNotSame($notifikasiPertama->id, $belum['notifications'][0]['id']);

        // Filter Sudah Dibaca tetap memuat notifikasi yang sudah dibaca.
        $sudah = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', ['role' => 'siswa', 'filter' => 'read']))
            ->assertOk()
            ->json();

        $this->assertSame('read', $sudah['filter']);
        $this->assertCount(1, $sudah['notifications']);
        $this->assertSame($notifikasiPertama->id, $sudah['notifications'][0]['id']);
        $this->assertTrue($sudah['notifications'][0]['is_read']);
        $this->assertNotNull($sudah['notifications'][0]['read_at']);
    }

    public function test_filter_tidak_dikenal_dikembalikan_ke_semua(): void
    {
        [$siswa] = $this->siswaDuaPengumuman();

        $data = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', ['role' => 'siswa', 'filter' => 'tidak-ada']))
            ->assertOk()
            ->json();

        $this->assertSame('all', $data['filter']);
        $this->assertCount(2, $data['notifications']);
    }

    // Tandai satu dibaca

    public function test_tandai_satu_notifikasi_mengurangi_jumlah_belum_dibaca(): void
    {
        [$siswa, $p1, $p2] = $this->siswaDuaPengumuman();

        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 2]);

        $pertama = Notification::where('user_id', $siswa->id)
            ->where('reference_key', 'pengumuman:'.$p1->id)
            ->firstOrFail();

        $this->actingAs($siswa)
            ->postJson(route('dashboard.notifications.read', ['role' => 'siswa', 'id' => $pertama->id]))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 1]);

        $this->assertNotNull($pertama->fresh()->read_at);

        $data = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $data['unread_count']);
        $this->assertSame(['all' => 2, 'unread' => 1, 'read' => 1], $data['counts']);

        $itemDibaca = collect($data['notifications'])->firstWhere('id', $pertama->id);
        $this->assertTrue($itemDibaca['is_read']);

        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);
    }

    public function test_tandai_satu_notifikasi_idempoten_dan_id_lain_tidak_diubah(): void
    {
        [$siswa, $p1, $p2] = $this->siswaDuaPengumuman();

        $pertama = Notification::where('user_id', $siswa->id)
            ->where('reference_key', 'pengumuman:'.$p1->id)
            ->firstOrFail();

        $rute = route('dashboard.notifications.read', ['role' => 'siswa', 'id' => $pertama->id]);

        $this->actingAs($siswa)->postJson($rute)->assertOk();
        $waktuBaca = $pertama->fresh()->read_at;

        // Klik kedua (mis. tab ganda) tidak mengubah waktu baca ataupun error.
        $this->actingAs($siswa)->postJson($rute)->assertOk();

        $this->assertSame(
            $waktuBaca->format('Y-m-d H:i:s'),
            $pertama->fresh()->read_at->format('Y-m-d H:i:s'),
        );

        // Baris milik pengumuman lain tidak ikut terbaca.
        $kedua = Notification::where('user_id', $siswa->id)
            ->where('reference_key', 'pengumuman:'.$p2->id)
            ->firstOrFail();
        $this->assertNull($kedua->fresh()->read_at);
    }

    // Tandai semua dibaca

    public function test_tandai_semua_dibaca_tidak_menghapus_notifikasi(): void
    {
        [$siswa, $p1, $p2] = $this->siswaDuaPengumuman();

        // Sinkronisasi pertama membuat dua baris notifikasi tersimpan.
        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk();

        $this->assertSame(2, Notification::where('user_id', $siswa->id)->count());

        $this->actingAs($siswa)
            ->postJson(route('dashboard.notifications.mark-all', 'siswa'))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        // Semua baris tetap ada, hanya read_at yang terisi.
        $this->assertSame(2, Notification::where('user_id', $siswa->id)->count());
        $this->assertSame(
            0,
            Notification::where('user_id', $siswa->id)->whereNull('read_at')->count(),
        );

        // Badge & daftar mengikuti: tidak ada yang belum dibaca, dua tetap tampil.
        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        $semua = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk()
            ->json();

        $this->assertSame(0, $semua['unread_count']);
        $this->assertSame(['all' => 2, 'unread' => 0, 'read' => 2], $semua['counts']);
        $this->assertCount(2, $semua['notifications']);
        foreach ($semua['notifications'] as $n) {
            $this->assertTrue($n['is_read']);
        }

        // Filter Sudah Dibaca memuat semuanya.
        $sudah = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', ['role' => 'siswa', 'filter' => 'read']))
            ->assertOk()
            ->json();
        $this->assertCount(2, $sudah['notifications']);
    }

    public function test_tandai_semua_dibaca_hanya_menyentuh_notifikasi_user_itu(): void
    {
        [$siswa1] = $this->siswaDuaPengumuman();

        $siswa2 = User::factory()->siswa('11-1')->create([
            'jurusan' => 'Teknik Jaringan Komputer',
        ]);

        // Pengumuman khusus rombel siswa kedua, sehingga jumlah notifikasi
        // kedua akun berbeda (2 vs 3) dan isolasinya benar-benar teruji.
        Pengumuman::create([
            'judul' => 'Pengumuman untuk Rombel Lain',
            'isi' => 'Hanya untuk siswa kelas 11-1.',
            'kelas' => '11-1',
            'jurusan' => null,
            'publish_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($siswa1)->getJson(route('dashboard.notifications.index', 'siswa'))->assertOk();
        $this->actingAs($siswa2)->getJson(route('dashboard.notifications.index', 'siswa'))->assertOk();

        $this->assertSame(2, Notification::where('user_id', $siswa1->id)->count());
        $this->assertSame(3, Notification::where('user_id', $siswa2->id)->count());

        $this->actingAs($siswa1)
            ->postJson(route('dashboard.notifications.mark-all', 'siswa'))
            ->assertOk();

        $this->assertSame(
            0,
            Notification::where('user_id', $siswa1->id)->whereNull('read_at')->count(),
        );
        // Status baca siswa lain tidak tersentuh.
        $this->assertSame(
            3,
            Notification::where('user_id', $siswa2->id)->whereNull('read_at')->count(),
        );

        $this->actingAs($siswa2)
            ->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 3]);
    }

    // Notifikasi dari aktivitas nyata tiap role

    public function test_notifikasi_admin_pengajuan_baru_muncul_kembali_belum_dibaca(): void
    {
        $admin = User::factory()->admin()->create();
        $pemohon1 = User::factory()->siswa('12-1')->create(['nis' => '2400556677']);
        $pemohon2 = User::factory()->siswa('11-1')->create(['nis' => '2400556678']);

        // Pengajuan lewat form nyata di halaman login.
        $this->postJson(route('auth.buat-password'), [
            'role' => 'siswa',
            'nis' => '2400556677',
        ])->assertOk();

        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data['notifications']);
        $this->assertSame(1, $data['unread_count']);

        $this->actingAs($admin)
            ->postJson(route('dashboard.notifications.mark-all', 'admin'))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        // Pengajuan baru dari akun lain membuat notifikasi belum dibaca lagi.
        $this->postJson(route('auth.buat-password'), [
            'role' => 'siswa',
            'nis' => '2400556678',
        ])->assertOk();

        $dataBaru = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame(2, $dataBaru['counts']['all']);
        $this->assertSame(1, $dataBaru['unread_count']);
        $terbaru = collect($dataBaru['notifications'])->firstWhere('is_read', false);
        $this->assertNotNull($terbaru);
        $this->assertStringContainsString('2400556678', $terbaru['message']);

        // Notifikasi lama tetap tersimpan pada filter Sudah Dibaca.
        $sudah = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', ['role' => 'admin', 'filter' => 'read']))
            ->assertOk()
            ->json();
        $this->assertCount(1, $sudah['notifications']);
    }

    public function test_notifikasi_guru_dari_tugas_bisa_ditandai_semua_dibaca(): void
    {
        $guru = User::factory()->guru()->create(['mata_pelajaran' => 'Fisika']);
        $mapel = MataPelajaran::factory()->nama('Fisika')->create();

        Tugas::create([
            'kelas' => '12-1',
            'jurusan' => 'Rekayasa Perangkat Lunak',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => 'Tugas Aljabar',
            'deskripsi' => 'Kerjakan soal berikut.',
            'deadline' => now()->addDays(2),
        ]);

        $this->actingAs($guru)
            ->getJson(route('dashboard.notifications.count', 'guru'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        $this->actingAs($guru)
            ->postJson(route('dashboard.notifications.mark-all', 'guru'))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        $this->actingAs($guru)
            ->getJson(route('dashboard.notifications.count', 'guru'))
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        // Barisnya tetap tersimpan dan tampil pada filter Sudah Dibaca.
        $sudah = $this->actingAs($guru)
            ->getJson(route('dashboard.notifications.index', ['role' => 'guru', 'filter' => 'read']))
            ->assertOk()
            ->json();
        $this->assertCount(1, $sudah['notifications']);
    }

    // Batasan akses & isolasi antar role/pengguna

    public function test_notifikasi_tabel_database_tertandai_dibaca_dan_count_konsisten_dengan_filter_role(): void
    {
        $siswa = User::factory()->siswa('12-1')->create();

        $milikSiswa = Notification::create([
            'user_id' => $siswa->id,
            'role' => null,
            'title' => 'Info Sekolah',
            'message' => 'Pesan untuk seluruh siswa.',
        ]);

        Notification::create([
            'user_id' => $siswa->id,
            'role' => 'guru',
            'title' => 'Untuk Guru',
            'message' => 'Tidak boleh tampil di siswa.',
        ]);

        $data = $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data['notifications']);
        $this->assertSame($milikSiswa->id, $data['notifications'][0]['id']);
        $this->assertSame(1, $data['unread_count']);

        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        $this->actingAs($siswa)
            ->postJson(route('dashboard.notifications.read', ['role' => 'siswa', 'id' => $milikSiswa->id]))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        $this->assertDatabaseHas('notifications', [
            'id' => $milikSiswa->id,
            'user_id' => $siswa->id,
        ]);
        $this->assertNotNull(Notification::find($milikSiswa->id)->read_at);

        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 0]);
    }

    public function test_notifikasi_role_lain_ditolak_403(): void
    {
        $siswa = User::factory()->siswa('12-1')->create();

        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertForbidden();

        $this->actingAs($siswa)
            ->getJson(route('dashboard.notifications.index', 'guru'))
            ->assertForbidden();

        $this->actingAs($siswa)
            ->postJson(route('dashboard.notifications.mark-all', 'admin'))
            ->assertForbidden();
    }

    /**
     * Dua role login pada sesi yang sama (skenario multi-tab): guard `web`
     * berisi role terakhir login. Endpoint notifikasi harus mengikuti segment
     * {role} di URL, bukan guard web, sehingga notifikasi tidak bocor antar role.
     */
    public function test_notifikasi_terpisah_per_role_sa_dua_role_login_bersamaan(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = User::factory()->siswa('12-1')->create(['nis' => '2400556679']);

        Pengumuman::create([
            'judul' => 'Libur Sekolah',
            'isi' => 'Seluruh warga sekolah diliburkan.',
            'kelas' => null,
            'jurusan' => null,
            'publish_at' => now()->subDay(),
        ]);

        // Pengajuan nyata lewat form login menjadi notifikasi admin.
        $this->postJson(route('auth.buat-password'), [
            'role' => 'siswa',
            'nis' => '2400556679',
        ])->assertOk();

        $this->actingAs($siswa, 'siswa');
        $this->actingAs($admin, 'admin');

        $dataAdmin = $this->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $dataAdmin['notifications']);
        $this->assertSame('Pengajuan Bantuan Password', $dataAdmin['notifications'][0]['title']);
        $this->assertIsInt($dataAdmin['notifications'][0]['id']);

        $dataSiswa = $this->getJson(route('dashboard.notifications.index', 'siswa'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $dataSiswa['notifications']);
        $this->assertSame('pengumuman', $dataSiswa['notifications'][0]['type']);
        $this->assertIsInt($dataSiswa['notifications'][0]['id']);

        // Badge count juga harus terpisah per role.
        $this->getJson(route('dashboard.notifications.count', 'admin'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);
        $this->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        // Menandai semua dibaca di dashboard admin tidak menyentuh
        // notifikasi siswa.
        $this->postJson(route('dashboard.notifications.mark-all', 'admin'))
            ->assertOk();

        $this->getJson(route('dashboard.notifications.count', 'admin'))
            ->assertOk()
            ->assertJson(['unread_count' => 0]);
        $this->getJson(route('dashboard.notifications.count', 'siswa'))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);
    }
}
