<?php

namespace Tests\Feature;

use App\Mail\ItHelpRequestMail;
use App\Models\ItHelpRequest;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ItHelpRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rate limiter memakai cache, jadi cache dibersihkan tiap test agar
     * hitungan throttle tidak bocor antar test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        cache()->clear();
        config(['services.it_support.email' => 'it@saskansa.sch.id']);
    }

    /**
     * Payload valid sesuai role yang dipilih (Guru memakai NIP, Siswa memakai NIS).
     */
    protected function payload(string $role, array $overrides = []): array
    {
        $field = LoginIdentifier::field($role);

        return array_merge([
            'role' => $role,
            $field => $role === 'guru' ? 'GP-9001' : '99001',
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIP saya, sudah coba dua kali.',
        ], $overrides);
    }

    public function test_landing_page_shows_it_help_form(): void
    {
        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Hubungi Tim IT Sekolah')
            ->assertSee('faq-it-form', false)
            ->assertSee('hubungi-tim-it', false)
            ->assertSee('csrf-token', false)
            // Dropdown role hanya memuat Guru dan Siswa.
            ->assertSee('<option value="guru">Guru</option>', false)
            ->assertSee('<option value="siswa">Siswa</option>', false)
            ->assertDontSee('<option value="admin">Admin</option>', false)
            // Kolom identitas mengikuti role, kategori kendala dihapus.
            ->assertSee('faq-it-identifier', false)
            ->assertDontSee('faq-it-kategori', false)
            // Ikon tombol harus meneruskan atribut supaya bisa dipakai untuk status "Mengirim...".
            ->assertSee('data-faq-submit-icon', false)
            ->assertSee('data-faq-submit-text', false);
    }

    public function test_guru_request_is_stored(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Permintaan bantuan sudah kami terima. Tim IT Sekolah akan menghubungi kamu melalui email tersebut pada hari dan jam kerja.');

        $this->assertDatabaseHas('it_help_requests', [
            'role' => 'guru',
            'identifier' => 'GP-9001',
            'email' => 'pengaju@saskansa.sch.id',
            'status' => ItHelpRequest::STATUS_BARU,
        ]);

        $this->assertSame(
            'Saya tidak bisa masuk memakai NIP saya, sudah coba dua kali.',
            ItHelpRequest::first()->pesan
        );
    }

    public function test_siswa_request_is_stored_with_nis(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('siswa'))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('it_help_requests', [
            'role' => 'siswa',
            'identifier' => '99001',
        ]);
    }

    public function test_nip_sent_by_siswa_is_rejected(): void
    {
        Mail::fake();

        // Field "nip" tidak relevan kalau role-nya siswa, jadi identifier
        // siswa dianggap kosong.
        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'siswa',
            'nip' => 'GP-9001',
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIS saya, sudah coba dua kali.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors('nis');

        $this->assertDatabaseCount('it_help_requests', 0);
    }

    public function test_it_team_receives_notification_email(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))->assertOk();

        Mail::assertSent(ItHelpRequestMail::class, function (ItHelpRequestMail $mail) {
            return $mail->hasTo('it@saskansa.sch.id')
                && $mail->envelope()->subject === 'Permintaan Bantuan Baru dari Guru';
        });
    }

    public function test_no_notification_email_when_it_email_not_configured(): void
    {
        Mail::fake();
        config(['services.it_support.email' => null]);

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Permintaan tetap tersimpan walau email notifikasi tidak dikonfigurasi.
        $this->assertDatabaseCount('it_help_requests', 1);
        Mail::assertNothingSent();
    }

    public function test_request_is_linked_via_role_and_identifier(): void
    {
        Mail::fake();

        $guru = User::factory()->guru()->create([
            'nip' => 'GP-9001',
            // Email sengaja dibuat berbeda supaya pencocokan yang dipakai
            // adalah role + NIP, bukan email.
            'email' => 'guru.resmi@saskansa.sch.id',
        ]);

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))
            ->assertOk();

        $this->assertDatabaseHas('it_help_requests', [
            'user_id' => $guru->id,
            'role' => 'guru',
            'identifier' => 'GP-9001',
        ]);
    }

    public function test_request_falls_back_to_email_matching(): void
    {
        Mail::fake();

        $siswa = User::factory()->siswa()->create([
            'nis' => '99002',
            'email' => 'siswa.baru@saskansa.sch.id',
        ]);

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('siswa', [
            'nis' => '99002',
            'email' => 'Siswa.Baru@Saskansa.Sch.Id',
        ]))->assertOk();

        $this->assertDatabaseHas('it_help_requests', [
            'user_id' => $siswa->id,
            'email' => 'siswa.baru@saskansa.sch.id',
        ]);
    }

    public function test_same_identifier_in_other_role_is_not_matched(): void
    {
        Mail::fake();

        $guru = User::factory()->guru()->create([
            'nip' => '99001',
            'email' => 'guru.99@saskansa.sch.id',
        ]);

        // Identifier 99001 milik Guru, tapi pengaju memilih role Siswa.
        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('siswa', [
            'email' => 'tidak-terdaftar@contoh.test',
        ]))->assertOk();

        $this->assertDatabaseHas('it_help_requests', [
            'user_id' => null,
            'identifier' => '99001',
        ]);

        $this->assertNotSame($guru->id, ItHelpRequest::first()->user_id);
    }

    public function test_admin_role_is_not_accepted(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'admin',
            'email' => 'admin@saskansa.sch.id',
            'pesan' => 'Saya admin sekolah dan butuh bantuan teknis.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseCount('it_help_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_missing_role_returns_validation_error(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), [
            'email' => 'pengaju@saskansa.sch.id',
            'pesan' => 'Saya tidak bisa masuk memakai NIP saya, sudah coba dua kali.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseCount('it_help_requests', 0);
    }

    public function test_invalid_payload_returns_field_errors(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), [
            'role' => 'guru',
            'nip' => 'GP 9001!!',
            'email' => 'bukan-email',
            'pesan' => 'pendek',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonValidationErrors(['nip', 'email', 'pesan']);

        $this->assertDatabaseCount('it_help_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_honeypot_submission_is_silently_accepted_but_not_stored(): void
    {
        Mail::fake();

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru', [
            'website' => 'https://spam.example.test',
        ]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('it_help_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_endpoint_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))->assertOk();
        }

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))->assertStatus(429);
    }

    public function test_request_is_not_accepted_while_throttled_even_with_valid_payload(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson(route('landing.hubungi-tim-it'), $this->payload('guru'))->assertOk();
        }

        $this->postJson(route('landing.hubungi-tim-it'), $this->payload('siswa', ['nis' => '99099']))
            ->assertStatus(429);

        $this->assertDatabaseCount('it_help_requests', 5);
    }
}
