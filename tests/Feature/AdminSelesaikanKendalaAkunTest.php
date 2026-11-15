<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\KendalaAkunController;
use App\Mail\ItHelpResolvedMail;
use App\Models\ItHelpReply;
use App\Models\ItHelpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Mekanisme tombol "Selesai" pada halaman Detail Kendala Akun:
 *
 * - Status pengajuan diubah menjadi Selesai.
 * - Email pemberitahuan dikirim ke alamat pengaju berisi kabar bahwa
 *   kendala yang dialaminya sudah diperbaiki dan sudah bisa
 *   difungsikan kembali dengan normal.
 * - Pemberitahuan tercatat sebagai riwayat tindak lanjut.
 * - Status hanya berubah setelah email benar-benar terkirim, jadi
 *   kegagalan email tidak meninggalkan pengguna tanpa kabar.
 */
class AdminSelesaikanKendalaAkunTest extends TestCase
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
    }

    /**
     * Buat satu permintaan bantuan langsung di database supaya email
     * notifikasi Tim IT dari form landing tidak ikut diejek.
     */
    protected function buatPermintaan(string $status = ItHelpRequest::STATUS_BARU): ItHelpRequest
    {
        $siswa = User::factory()->siswa('12-1')->create(['nis' => '99001', 'email' => 'siswa.resmi@saskansa.sch.id']);

        return ItHelpRequest::create([
            'user_id' => $siswa->id,
            'role' => 'siswa',
            'identifier' => '99001',
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
            'status' => $status,
        ]);
    }

    public function test_admin_menandai_selesai_mengirim_email_pemberitahuan_dan_mencatat_riwayat(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->buatPermintaan();

        $this->actingAs($admin)
            ->patchJson(route('dashboard.admin.kendala-akun.selesai', $permintaan))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Email pemberitahuan dikirim ke alamat pengaju.
        Mail::assertSent(ItHelpResolvedMail::class, fn (ItHelpResolvedMail $mail) => $mail->hasTo($permintaan->email));

        // Pemberitahuan tercatat sebagai riwayat tindak lanjut.
        $this->assertDatabaseHas('it_help_replies', [
            'it_help_request_id' => $permintaan->id,
            'user_id' => $admin->id,
            'pesan' => KendalaAkunController::PESAN_SELESAI,
            'status_pengiriman' => 'terkirim',
        ]);

        // Status pengajuan diperbarui menjadi Selesai.
        $this->assertDatabaseHas('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_SELESAI,
        ]);
    }

    public function test_email_pemberitahuan_memuat_isi_kendala_dan_kabar_sudah_diperbaiki(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['name' => 'Bu Admin IT']);
        $permintaan = $this->buatPermintaan();

        $this->actingAs($admin)
            ->patchJson(route('dashboard.admin.kendala-akun.selesai', $permintaan))
            ->assertOk();

        // Render isi email untuk memastikan pesan intinya benar:
        // kendala sudah diperbaiki dan bisa difungsikan dengan normal.
        $mail = Mail::sent(ItHelpResolvedMail::class)->firstOrFail();
        $rendered = $mail->render();

        $this->assertStringContainsString('Kendala Anda Sudah Diperbaiki', $rendered);
        $this->assertStringContainsString('sudah diperbaiki dan sudah bisa', $rendered);
        $this->assertStringContainsString($permintaan->pesan, $rendered);
        $this->assertStringContainsString('99001', $rendered);
    }

    public function test_halaman_detail_tidak_menampilkan_tombol_selesai_bila_sudah_selesai(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->buatPermintaan();

        // Status belum selesai: tombol Selesai tampil di halaman detail.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee(route('dashboard.admin.kendala-akun.selesai', $permintaan), false);

        $this->actingAs($admin)
            ->patchJson(route('dashboard.admin.kendala-akun.selesai', $permintaan))
            ->assertOk();

        // Setelah selesai: tombol hilang dan riwayat pemberitahuan tampil.
        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee('Selesai')
            ->assertDontSee(route('dashboard.admin.kendala-akun.selesai', $permintaan), false)
            ->assertSee(KendalaAkunController::PESAN_SELESAI);
    }

    public function test_selesai_kedua_ditolak_tanpa_mengirim_email_baru(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $permintaan = $this->buatPermintaan();

        $this->actingAs($admin)
            ->patchJson(route('dashboard.admin.kendala-akun.selesai', $permintaan))
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson(route('dashboard.admin.kendala-akun.selesai', $permintaan))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        // Hanya satu pemberitahuan dan satu email yang terkirim.
        $this->assertSame(1, ItHelpReply::where('it_help_request_id', $permintaan->id)->count());
        Mail::assertSent(ItHelpResolvedMail::class, 1);
    }

    public function test_kegagalan_email_tidak_mengubah_status_menjadi_selesai(): void
    {
        $admin = User::factory()->admin()->create();
        $permintaan = $this->buatPermintaan();

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('SMTP tidak tersedia'));

        $this->actingAs($admin)
            ->patchJson(route('dashboard.admin.kendala-akun.selesai', $permintaan))
            ->assertStatus(500)
            ->assertJsonPath('status', 'error');

        // Riwayat tetap tercatat (sebagai gagal), tetapi status pengajuan
        // TIDAK diubah karena pengguna belum diberitahu.
        $this->assertDatabaseHas('it_help_replies', [
            'it_help_request_id' => $permintaan->id,
            'status_pengiriman' => 'gagal',
        ]);
        $this->assertDatabaseMissing('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_SELESAI,
        ]);
    }

    public function test_endpoint_selesai_hanya_untuk_admin(): void
    {
        Mail::fake();

        $permintaan = $this->buatPermintaan();
        $url = route('dashboard.admin.kendala-akun.selesai', $permintaan);

        // Tamu ditolak: permintaan JSON dibalas 401.
        $this->patchJson($url)->assertUnauthorized();

        // Guru dan Siswa ditolak middleware role admin.
        $guru = User::factory()->guru()->create();
        $this->actingAs($guru)->patchJson($url)->assertForbidden();
        $this->actingAs(User::factory()->siswa('10-1')->create())->patchJson($url)->assertForbidden();

        // Admin tetap bisa menandai selesai.
        $this->actingAs(User::factory()->admin()->create())
            ->patchJson($url)
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
