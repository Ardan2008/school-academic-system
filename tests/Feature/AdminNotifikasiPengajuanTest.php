<?php

namespace Tests\Feature;

use App\Models\ItHelpRequest;
use App\Models\Notification;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Pengajuan dari halaman publik — pop-up "Atur Ulang Password" di
 * halaman login dan pop-up "Hubungi Tim IT Sekolah" di landing page —
 * harus otomatis menjadi notifikasi Notifikasi Sistem milik tiap admin
 * aktif, lengkap dengan data pengirim, tautan detail pengajuan, dan
 * status "belum dibaca" yang bisa ditindaklanjuti dari panel.
 */
class AdminNotifikasiPengajuanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rate limiter form IT memakai cache, jadi cache dibersihkan tiap
     * test agar hitungan throttle tidak bocor antar test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        cache()->clear();
        config(['services.it_support.email' => 'it@saskansa.sch.id']);
    }

    /**
     * Payload valid untuk form "Hubungi Tim IT Sekolah".
     */
    protected function payloadIt(string $role, array $overrides = []): array
    {
        $field = LoginIdentifier::field($role);

        return array_merge([
            'role' => $role,
            $field => $role === 'guru' ? 'GP-9001' : '99001',
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
        ], $overrides);
    }

    // Form bantuan password (halaman login)

    public function test_pengajuan_bantuan_password_membuat_notifikasi_untuk_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-9001']);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'GP-9001',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $guru->id,
            'role' => 'guru',
            'identifier' => 'GP-9001',
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        // Notifikasi berisi data yang dikumpulkan form login: nama akun,
        // role, dan NIP/NIS pengajuan.
        $notifikasi = Notification::where('user_id', $admin->id)->first();
        $this->assertNotNull($notifikasi);
        $this->assertSame('pengajuan_password', $notifikasi->type);
        $this->assertSame('Pengajuan Bantuan Password', $notifikasi->title);
        $this->assertSame($guru->name.' · Guru · NIP GP-9001', $notifikasi->message);
        $this->assertSame('inbox', $notifikasi->icon);
        $this->assertSame(route('dashboard.admin.pengajuan-reset-password.index'), $notifikasi->url);
        $this->assertNull($notifikasi->read_at);

        // Pengaju tidak menerima notifikasi pengajuannya sendiri.
        $this->assertDatabaseMissing('notifications', ['user_id' => $guru->id]);
    }

    public function test_notifikasi_pengajuan_password_muncul_di_endpoint_notifikasi_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $siswa = User::factory()->siswa('12-1')->create(['nis' => '2400556677']);

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

        $item = $data['notifications'][0];
        $this->assertSame('Pengajuan Bantuan Password', $item['title']);
        $this->assertSame($siswa->name.' · Siswa · NIS 2400556677', $item['message']);
        $this->assertSame(route('dashboard.admin.pengajuan-reset-password.index'), $item['url']);
        $this->assertFalse($item['is_read']);
        $this->assertNull($item['read_at']);
        $this->assertNotNull($item['time']);
    }

    public function test_pengajuan_password_gagal_tidak_membuat_notifikasi(): void
    {
        $admin = User::factory()->admin()->create();

        // Identifier tidak terdaftar: jawaban tetap generik-sukses, tapi
        // tidak ada pengajuan maupun notifikasi yang dibuat.
        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'TIDAK-ADA',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('password_reset_requests', 0);
        $this->assertDatabaseCount('notifications', 0);

        // Identifier terdaftar tapi akun nonaktif juga tidak membuat notifikasi.
        User::factory()->guru()->create(['nip' => 'GP-4000', 'is_active' => false]);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'GP-4000',
        ])->assertOk();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_pengajuan_password_kedua_tidak_menggandakan_notifikasi(): void
    {
        User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-9001']);

        // Pengajuan pending sudah ada, submit ulang tidak membuat
        // pengajuan (dan notifikasi) baru.
        $this->postJson(route('auth.buat-password'), ['role' => 'guru', 'nip' => 'GP-9001'])->assertOk();
        $this->postJson(route('auth.buat-password'), ['role' => 'guru', 'nip' => 'GP-9001'])->assertOk();

        $this->assertSame(1, PasswordResetRequest::where('user_id', $guru->id)->count());
        $this->assertSame(1, Notification::count());
    }

    // Form Hubungi Tim IT (landing page)

    public function test_form_hubungi_tim_it_membuat_notifikasi_untuk_admin(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create([
            'nip' => 'GP-9001',
            'email' => 'guru.resmi@saskansa.sch.id',
        ]);

        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'guru',
            'nip' => 'GP-9001',
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('it_help_requests', 1);

        // Notifikasi berisi seluruh isi form: nama (NIP terdaftar), role,
        // NIP/NIS, email pengirim, dan ringkasan pesan.
        $notifikasi = Notification::where('user_id', $admin->id)->first();
        $this->assertNotNull($notifikasi);
        $this->assertSame('kendala_akun', $notifikasi->type);
        $this->assertSame('Kendala Akun · Hubungi Tim IT', $notifikasi->title);
        $this->assertSame(
            $guru->name.' · Guru · NIP GP-9001 · pengaju@saskansa.sch.id — Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
            $notifikasi->message,
        );
        $this->assertSame('megaphone', $notifikasi->icon);
        $this->assertSame(route('dashboard.admin.kendala-akun.show', ItHelpRequest::first()), $notifikasi->url);
        $this->assertNull($notifikasi->read_at);
    }

    public function test_form_hubungi_tim_it_tanpa_akun_terdaftar_tetap_membuat_notifikasi(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('siswa'))
            ->assertOk();

        // NIS tidak terdaftar: nama tidak tersedia, tapi data lain tetap
        // informatif untuk admin.
        $notifikasi = Notification::where('user_id', $admin->id)->firstOrFail();

        $this->assertStringContainsString('Nama tidak tersedia', $notifikasi->message);
        $this->assertStringContainsString('Siswa · NIS 99001', $notifikasi->message);
        $this->assertStringContainsString('pengaju@saskansa.sch.id', $notifikasi->message);
        $this->assertStringContainsString('Saya tidak bisa masuk', $notifikasi->message);
    }

    public function test_notifikasi_kendala_akun_muncul_di_endpoint_notifikasi_admin(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('siswa'))
            ->assertOk();

        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data['notifications']);
        $this->assertSame(1, $data['unread_count']);

        $item = $data['notifications'][0];
        $this->assertSame('Kendala Akun · Hubungi Tim IT', $item['title']);
        $this->assertStringContainsString('Siswa · NIS 99001', $item['message']);
        $this->assertStringContainsString('pengaju@saskansa.sch.id', $item['message']);
        $this->assertSame(route('dashboard.admin.kendala-akun.show', ItHelpRequest::first()), $item['url']);
        $this->assertFalse($item['is_read']);
        $this->assertNotNull($item['time']);
    }

    public function test_form_hubungi_tim_it_invalid_tidak_membuat_notifikasi(): void
    {
        Mail::fake();

        User::factory()->admin()->create();

        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'guru',
            'nip' => 'GP-9001',
            'email' => 'bukan-email',
            'pesan' => 'pendek',
        ])->assertStatus(422);

        $this->assertDatabaseCount('it_help_requests', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_honeypot_tidak_membuat_notifikasi(): void
    {
        Mail::fake();

        User::factory()->admin()->create();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('guru', [
            'website' => 'https://spam.example.test',
        ]))->assertOk();

        $this->assertDatabaseCount('it_help_requests', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    // Status dibaca & multi admin

    public function test_klik_notifikasi_pengajuan_menandai_dibaca_dan_persisten(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('siswa'))
            ->assertOk();

        $notifikasi = Notification::first();

        // Alur yang sama dengan klik item di panel Notifikasi Sistem.
        $this->actingAs($admin)
            ->postJson(route('dashboard.notifications.read', ['role' => 'admin', 'id' => $notifikasi->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notifikasi->fresh()->read_at);

        // Pembaruan berikutnya (polling panel) tidak mengembalikan status belum dibaca.
        $data = $this->actingAs($admin)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame(0, $data['unread_count']);
        $this->assertTrue($data['notifications'][0]['is_read']);
    }

    public function test_notifikasi_dibuat_untuk_semua_admin_aktif_dan_status_dibacanya_mandiri(): void
    {
        $admin1 = User::factory()->admin()->create();
        $admin2 = User::factory()->admin()->create();
        $adminNonaktif = User::factory()->admin()->create(['is_active' => false]);

        User::factory()->guru()->create(['nip' => 'GP-9001']);

        $this->postJson(route('auth.buat-password'), ['role' => 'guru', 'nip' => 'GP-9001'])
            ->assertOk();

        // Hanya admin aktif yang menerima notifikasi.
        $this->assertSame(2, Notification::count());
        $this->assertTrue(Notification::where('user_id', $admin1->id)->exists());
        $this->assertTrue(Notification::where('user_id', $admin2->id)->exists());
        $this->assertFalse(Notification::where('user_id', $adminNonaktif->id)->exists());

        // Admin pertama menandai sudah dibaca...
        $id = Notification::where('user_id', $admin1->id)->first()->id;

        $this->actingAs($admin1)
            ->postJson(route('dashboard.notifications.read', ['role' => 'admin', 'id' => $id]))
            ->assertOk();

        // ...tanpa memengaruhi status milik admin kedua.
        $this->assertNotNull(Notification::where('user_id', $admin1->id)->first()->read_at);
        $this->assertNull(Notification::where('user_id', $admin2->id)->first()->read_at);

        $dataAdmin2 = $this->actingAs($admin2)
            ->getJson(route('dashboard.notifications.index', 'admin'))
            ->assertOk()
            ->json();

        $this->assertSame(1, $dataAdmin2['unread_count']);
        $this->assertFalse($dataAdmin2['notifications'][0]['is_read']);
    }

    // Halaman detail kendala akun

    public function test_admin_dapat_melihat_detail_kendala_akun_dari_notifikasi(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $siswa = User::factory()->siswa('12-1')->create([
            'nis' => '99001',
            'email' => 'siswa.resmi@saskansa.sch.id',
        ]);

        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'siswa',
            'nis' => '99001',
            'email' => 'pengaju@contoh.test',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
        ])->assertOk();

        $permintaan = ItHelpRequest::first();

        $res = $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertViewIs('dashboard.admin.kendala-akun.show');

        // Data pengirim lengkap dan sesuai isi form: nama (NIS terdaftar),
        // role, NIS, email form, email terdaftar yang berbeda, isi kendala,
        // waktu pengajuan, serta tombol tindak lanjut.
        $res->assertSee($siswa->name);
        $res->assertSee('NIS', false);
        $res->assertSee('99001');
        $res->assertSee('pengaju@contoh.test');
        $res->assertSee('Email Terdaftar', false);
        $res->assertSee('siswa.resmi@saskansa.sch.id');
        $res->assertSee('Isi Kendala', false);
        $res->assertSee('Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.');
        $res->assertSee('Waktu Pengajuan', false);
        // Tombol tindak lanjut: Balas membuka halaman khusus balasan dan
        // Selesai mengirim email pemberitahuan ke pengaju (bukan popup).
        $res->assertSee(route('dashboard.admin.kendala-akun.balas.create', $permintaan), false);
        $res->assertSee(route('dashboard.admin.kendala-akun.selesai', $permintaan), false);
        $res->assertDontSee('balas-modal', false);
    }

    public function test_detail_kendala_akun_tanpa_akun_terdaftar_tetap_terbaca_admin(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('siswa'))
            ->assertOk();

        $permintaan = ItHelpRequest::first();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee('Tidak terdaftar')
            ->assertSee('NIS', false)
            ->assertSee('99001')
            ->assertSee('pengaju@saskansa.sch.id');
    }

    public function test_halaman_detail_kendala_akun_hanya_untuk_admin(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('siswa'))
            ->assertOk();

        $permintaan = ItHelpRequest::first();
        $url = route('dashboard.admin.kendala-akun.show', $permintaan);

        // Tamu dialihkan ke halaman login.
        $this->get($url)->assertRedirect(route('login'));

        // Guru dan Siswa ditolak middleware role admin.
        $guru = User::factory()->guru()->create();
        $this->actingAs($guru)->get($url)->assertForbidden();
        $this->actingAs(User::factory()->siswa('10-1')->create())->get($url)->assertForbidden();

        // Admin tetap bisa membuka.
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    }
}
