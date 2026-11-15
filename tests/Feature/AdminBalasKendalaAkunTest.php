<?php

namespace Tests\Feature;

use App\Mail\ItHelpReplyMail;
use App\Models\ItHelpReply;
use App\Models\ItHelpRequest;
use App\Models\Notification;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Mekanisme tombol "Balas" pada halaman Detail Kendala Akun dibedakan
 * dari SUMBER pengajuan yang tersimpan di database, bukan dari teks
 * tampilan:
 *
 * - IT Help Request (tabel it_help_requests)  -> tombol Balas membuka
 *   popup balasan; Admin menulis tanggapan, backend menyimpan riwayat
 *   tindak lanjut, mengirim email ke pengaju, lalu memperbarui status
 *   pengajuan setelah email berhasil terkirim.
 * - Reset Password (tabel password_reset_requests) -> tidak pernah
 *   lewat popup balasan; notifikasinya langsung mengarah Admin ke
 *   halaman Pengajuan Reset Password untuk diproses di sana.
 */
class AdminBalasKendalaAkunTest extends TestCase
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

    /**
     * Kirim permintaan bantuan lewat form Homepage (sama seperti pengguna
     * menekan tombol Kirim Permintaan), lalu ambil barisnya dari database.
     */
    protected function kirimPermintaanIt(): ItHelpRequest
    {
        $siswa = User::factory()->siswa('12-1')->create(['nis' => '99001', 'email' => 'siswa.resmi@saskansa.sch.id']);

        $this->postJson(route('landing.hubungi-tim-it'), $this->payloadIt('siswa'))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        return ItHelpRequest::query()->where('user_id', $siswa->id)->firstOrFail();
    }

    // Skenario 1: IT Help Request membuka halaman khusus balasan

    public function test_halaman_detail_it_help_mengarahkan_tombol_balas_ke_halaman_khusus_bukan_popup(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->kirimPermintaanIt();

        // Tombol Balas mengarahkan ke halaman baru (route balas.create) dan tidak menampilkan popup modal
        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee('Balas', false)
            ->assertSee(route('dashboard.admin.kendala-akun.balas.create', $permintaan), false)
            ->assertDontSee('balas-modal', false)
            ->assertDontSee('data-balas-open', false);
    }

    public function test_halaman_khusus_balas_kendala_akun_menampilkan_informasi_lengkap_dan_form(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->kirimPermintaanIt();

        $res = $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.balas.create', $permintaan))
            ->assertOk()
            ->assertSee('Balas Kendala Akun')
            ->assertSee($permintaan->user?->name)
            ->assertSee(ucfirst($permintaan->role))
            ->assertSee($permintaan->identifier)
            ->assertSee($permintaan->email)
            ->assertSee($permintaan->pesan)
            ->assertSee('Email Penerima')
            ->assertSee('balas-pesan', false)
            ->assertSee('Kirim Balasan')
            ->assertSee('Batal')
            ->assertSee(route('dashboard.admin.kendala-akun.show', $permintaan), false);
    }

    public function test_admin_mengirim_balasan_menyimpan_riwayat_mengirim_email_dan_memperbarui_status(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->kirimPermintaanIt();

        $this->assertTrue($permintaan->refresh()->status === ItHelpRequest::STATUS_BARU);

        $tanggapan = 'Password akun Anda sudah direset. Silakan cek email berikutnya untuk password sementara, lalu ganti di menu Profil.';

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.kendala-akun.balas', $permintaan), ['pesan' => $tanggapan])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Isi balasan tersimpan sebagai riwayat tindak lanjut dengan status_pengiriman terkirim.
        $this->assertDatabaseHas('it_help_replies', [
            'it_help_request_id' => $permintaan->id,
            'user_id' => $admin->id,
            'pesan' => $tanggapan,
            'status_pengiriman' => 'terkirim',
        ]);

        // Email dikirim ke alamat pengaju.
        Mail::assertSent(ItHelpReplyMail::class, fn (ItHelpReplyMail $mail) => $mail->hasTo($permintaan->email));

        // Status pengajuan diperbarui menjadi Sudah Dibalas setelah email terkirim.
        $this->assertDatabaseHas('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_SUDAH_DIBALAS,
        ]);

        // Halaman detail ikut memperbarui status dan menampilkan riwayatnya.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee('Sudah Dibalas')
            ->assertSee('Riwayat Tindak Lanjut')
            ->assertSee($tanggapan)
            ->assertSee($admin->name);
    }

    public function test_balasan_kedua_tercatat_juga_sebagai_riwayat(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->kirimPermintaanIt();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.kendala-akun.balas', $permintaan), ['pesan' => 'Balasan pertama dari tim IT.'])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.kendala-akun.balas', $permintaan), ['pesan' => 'Balasan lanjutan dari tim IT.'])
            ->assertOk();

        $this->assertSame(2, ItHelpReply::where('it_help_request_id', $permintaan->id)->count());

        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee('Balasan pertama dari tim IT.')
            ->assertSee('Balasan lanjutan dari tim IT.');
    }

    public function test_balasan_tidak_valid_ditolak_tanpa_mengubah_apapun(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->kirimPermintaanIt();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.kendala-akun.balas', $permintaan), ['pesan' => 'pendek'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('it_help_replies', ['it_help_request_id' => $permintaan->id]);
        $this->assertCount(0, Mail::sent(ItHelpReplyMail::class));
        $this->assertDatabaseHas('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_BARU,
        ]);
    }

    public function test_kegagalan_email_menyimpan_balasan_tapi_tidak_memperbarui_status(): void
    {
        $admin = User::factory()->admin()->create();

        // Permintaan dibuat langsung agar pengiriman email form tidak ikut
        // diejek; yang diuji hanya balasan Admin.
        $permintaan = ItHelpRequest::create([
            'role' => 'siswa',
            'identifier' => '99001',
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
            'status' => ItHelpRequest::STATUS_BARU,
        ]);

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP tidak tersedia'));

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.kendala-akun.balas', $permintaan), ['pesan' => 'Balasan yang gagal terkirim lewat email.'])
            ->assertStatus(500)
            ->assertJsonPath('status', 'error');

        // Riwayat tetap tersimpan, tetapi status TIDAK diperbarui karena
        // email belum sampai ke pengguna.
        $this->assertDatabaseHas('it_help_replies', [
            'it_help_request_id' => $permintaan->id,
            'pesan' => 'Balasan yang gagal terkirim lewat email.',
        ]);
        $this->assertDatabaseHas('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_BARU,
        ]);
    }

    public function test_endpoint_balas_hanya_untuk_admin(): void
    {
        Mail::fake();

        $permintaan = $this->kirimPermintaanIt();
        $url = route('dashboard.admin.kendala-akun.balas', $permintaan);
        $payload = ['pesan' => 'Tanggapan dari tim IT sekolah.'];

        // Tamu ditolak: permintaan JSON dibalas 401, permintaan biasa
        // akan dialihkan ke halaman login.
        $this->postJson($url, $payload)->assertUnauthorized();

        // Guru dan Siswa ditolak middleware role admin.
        $guru = User::factory()->guru()->create();
        $this->actingAs($guru)->postJson($url, $payload)->assertForbidden();
        $this->actingAs(User::factory()->siswa('10-1')->create())->postJson($url, $payload)->assertForbidden();

        // Admin tetap bisa membalas.
        $this->actingAs(User::factory()->admin()->create())
            ->postJson($url, $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Halaman create juga hanya bisa dibuka oleh Admin
        $halamanUrl = route('dashboard.admin.kendala-akun.balas.create', $permintaan);
        $this->actingAs($guru)->get($halamanUrl)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($halamanUrl)->assertOk();
    }

    // Skenario 2: Reset Password dibawa ke halaman Pengajuan

    public function test_notifikasi_reset_password_mengarah_admin_ke_halaman_pengajuan_bukan_popup(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-9001']);

        $this->postJson(route('auth.buat-password'), ['role' => 'guru', 'nip' => 'GP-9001'])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Jenis pengajuan dibaca dari kolom type di database, bukan dari
        // teks notifikasinya.
        $notifikasi = Notification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'pengajuan_password')
            ->first();

        $this->assertNotNull($notifikasi);
        $this->assertSame(route('dashboard.admin.pengajuan-reset-password.index'), $notifikasi->url);

        // Admin dibawa langsung ke halaman Pengajuan Reset Password dan
        // tidak menemukan popup balasan kendala akun di sana.
        $this->actingAs($admin)
            ->get($notifikasi->url)
            ->assertOk()
            ->assertSee('Pengajuan Reset Password')
            ->assertDontSee('balas-modal', false)
            ->assertDontSee('data-balas-open', false);
    }

    public function test_pengajuan_reset_password_tidak_bisa_dibalas_lewat_endpoint_it_help(): void
    {
        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-9001']);

        $this->postJson(route('auth.buat-password'), ['role' => 'guru', 'nip' => 'GP-9001'])->assertOk();

        $pengajuan = PasswordResetRequest::query()->where('user_id', $guru->id)->firstOrFail();

        // Endpoint balas hanya membaca tabel it_help_requests, jadi
        // pengajuan reset password (tabel lain) tidak tertukar dan tidak
        // bisa diproses lewat sini.
        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.kendala-akun.balas', $pengajuan->id), ['pesan' => 'Tanggapan yang salah alamat.'])
            ->assertNotFound();

        $this->assertDatabaseMissing('it_help_replies', ['pesan' => 'Tanggapan yang salah alamat.']);
    }
}
