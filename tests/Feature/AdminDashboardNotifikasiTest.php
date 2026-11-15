<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Panel "Notifikasi Sistem" di Dashboard Admin harus mengambil data dari
 * mekanisme notifikasi yang sama dengan lonceng di header (seluruhnya
 * baris tabel notifications yang dibuat oleh aktivitas nyata), bukan dari
 * ringkasan tiruan, sehingga judul, isi pesan, waktu, dan status dibaca
 * yang tampil selalu benar.
 */
class AdminDashboardNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clear();
        config(['services.it_support.email' => 'it@saskansa.sch.id']);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Pengajuan bantuan password lewat form nyata di halaman login. */
    private function ajukanBantuanPassword(User $pemohon): void
    {
        $field = $pemohon->role === 'guru' ? 'nip' : 'nis';

        $this->postJson(route('auth.buat-password'), [
            'role' => $pemohon->role,
            $field => (string) $pemohon->{$field},
        ])->assertOk();
    }

    public function test_dashboard_admin_menampilkan_panel_notifikasi_sistem_yang_terhubung_ke_endpoint_notifikasi(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)
            ->get(route('dashboard.admin.index'))
            ->assertOk()
            ->assertViewIs('dashboard.admin.index');

        // Panel tetap ada dengan struktur yang sama, tapi sumber datanya
        // kini endpoint notifikasi role admin.
        $res->assertSee('Notifikasi Sistem');
        $res->assertSee('data-paginator="notifikasi"', false);

        // @json() meng-escape garis miring, jadi bandingkan dengan bentuk
        // JSON yang benar-benar dirender ke dalam skrip panel.
        $res->assertSee(json_encode(route('dashboard.notifications.index', 'admin')), false);

        // Ringkasan tiruan tidak lagi dikirim ke view.
        $res->assertViewMissing('notifikasi');
        $res->assertViewMissing('pendingReset');
    }

    public function test_panel_mengambil_data_notifikasi_admin_yang_benar(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $pemohon = User::factory()->siswa('12-1')->create(['nis' => '2400556677']);
        $this->ajukanBantuanPassword($pemohon);

        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data['notifications']);
        $this->assertSame(1, $data['unread_count']);

        $item = $data['notifications'][0];
        $this->assertIsInt($item['id']);
        $this->assertSame('Pengajuan Bantuan Password', $item['title']);
        $this->assertSame($pemohon->name.' · Siswa · NIS 2400556677', $item['message']);
        $this->assertNotNull($item['time']);
        $this->assertFalse($item['is_read']);
        $this->assertSame(
            route('dashboard.admin.pengajuan-reset-password.index'),
            $item['url'],
        );
    }

    public function test_panel_tanpa_notifikasi_menerima_daftar_kosong(): void
    {
        $admin = $this->admin();

        // Tidak ada aktivitas nyata maupun baris notifikasi tersimpan.
        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame([], $data['notifications']);
        $this->assertSame(0, $data['unread_count']);
        $this->assertSame(['all' => 0, 'unread' => 0, 'read' => 0], $data['counts']);

        // Halaman dashboard tetap termuat normal saat tidak ada notifikasi.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.index'))
            ->assertOk();
    }

    public function test_panel_menampilkan_notifikasi_tersimpan_dari_tabel_dan_status_dibacanya(): void
    {
        $admin = $this->admin();

        $terbaca = Notification::create([
            'user_id' => $admin->id,
            'role' => 'admin',
            'type' => 'sistem',
            'title' => 'Backup database berhasil',
            'message' => 'Cadangan basis data berhasil dibuat.',
            'icon' => 'bell',
            'url' => route('dashboard.admin.backup-database.index'),
            'read_at' => now()->subDay(),
        ]);

        $belum = Notification::create([
            'user_id' => $admin->id,
            'role' => 'admin',
            'type' => 'sistem',
            'title' => 'Akun baru diimpor',
            'message' => '3 akun siswa berhasil diimpor.',
            'icon' => 'bell',
            'url' => route('dashboard.admin.manajemen-akun.index'),
            'read_at' => null,
        ]);

        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertCount(2, $data['notifications']);
        $this->assertSame(1, $data['unread_count']);
        $this->assertSame(['all' => 2, 'unread' => 1, 'read' => 1], $data['counts']);

        $itemTerbaca = collect($data['notifications'])->firstWhere('id', $terbaca->id);
        $itemBelum = collect($data['notifications'])->firstWhere('id', $belum->id);

        $this->assertTrue($itemTerbaca['is_read']);
        $this->assertNotNull($itemTerbaca['read_at']);
        $this->assertSame('Backup database berhasil', $itemTerbaca['title']);
        $this->assertNotNull($itemTerbaca['time']);

        $this->assertFalse($itemBelum['is_read']);
        $this->assertNull($itemBelum['read_at']);
    }

    public function test_klik_notifikasi_pada_panel_menandai_dibaca_dan_persisten(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $pemohon = User::factory()->siswa('12-1')->create(['nis' => '2400556677']);
        $this->ajukanBantuanPassword($pemohon);

        $notifikasi = Notification::where('user_id', $admin->id)->firstOrFail();

        // Alur yang sama dengan klik baris di panel Notifikasi Sistem.
        $this->actingAs($admin)
            ->postJson(route('dashboard.notifications.read', ['role' => 'admin', 'id' => $notifikasi->id]))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        $this->assertNotNull($notifikasi->fresh()->read_at);

        // Pembaruan berikutnya (polling panel) tidak mengembalikan status belum dibaca.
        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame(0, $data['unread_count']);
        $this->assertTrue($data['notifications'][0]['is_read']);
    }

    public function test_notifikasi_admin_tidak_tercampur_dengan_notifikasi_role_lain(): void
    {
        $admin = $this->admin();

        // Baris milik siswa tidak boleh muncul di panel admin.
        Notification::create([
            'user_id' => User::factory()->siswa('10-1')->create()->id,
            'role' => 'siswa',
            'type' => 'sistem',
            'title' => 'Nilai baru masuk',
            'message' => 'Nilai Matematika sudah tersedia.',
            'icon' => 'award',
            'url' => route('dashboard.siswa.index'),
            'read_at' => null,
        ]);

        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame([], $data['notifications']);
        $this->assertSame(0, $data['unread_count']);
    }

    public function test_status_dibaca_admin_bertahan_setelah_pengajuan_baru(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $pemohon1 = User::factory()->siswa('12-1')->create(['nis' => '2400556677']);
        $this->ajukanBantuanPassword($pemohon1);

        // Tandai sudah dibaca.
        $notifikasi1 = Notification::where('user_id', $admin->id)->firstOrFail();
        $this->actingAs($admin)
            ->postJson(route('dashboard.notifications.read', ['role' => 'admin', 'id' => $notifikasi1->id]))
            ->assertOk();

        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame(0, $data['unread_count']);
        $this->assertTrue($data['notifications'][0]['is_read']);

        // Pengajuan baru membuat notifikasi segar berstatus belum dibaca,
        // sementara notifikasi lama tetap tercatat sudah dibaca.
        $pemohon2 = User::factory()->siswa('11-1')->create(['nis' => '2400556678']);
        $this->ajukanBantuanPassword($pemohon2);

        $dataBaru = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $dataBaru['unread_count']);
        $this->assertSame(['all' => 2, 'unread' => 1, 'read' => 1], $dataBaru['counts']);
        $this->assertFalse(collect($dataBaru['notifications'])->firstWhere('message', $pemohon2->name.' · Siswa · NIS 2400556678')['is_read']);
        $this->assertTrue(collect($dataBaru['notifications'])->firstWhere('message', $pemohon1->name.' · Siswa · NIS 2400556677')['is_read']);
    }
}
