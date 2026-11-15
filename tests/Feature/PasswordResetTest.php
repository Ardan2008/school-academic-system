<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Mail\ResetPasswordMail;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // Login role + identifier

    public function test_admin_logs_in_with_email(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'Admin123',
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'Admin123',
        ])->assertRedirect(route('dashboard.admin.index'));

        $this->assertAuthenticated();
    }

    public function test_guru_logs_in_with_nip(): void
    {
        User::factory()->guru()->create([
            'nip' => 'GP-1000',
            'password' => 'Guru123',
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => 'GP-1000',
            'password' => 'Guru123',
        ])->assertRedirect(route('dashboard.guru.index'));

        $this->assertAuthenticated();
    }

    public function test_siswa_logs_in_with_nis(): void
    {
        User::factory()->siswa()->create([
            'nis' => '2400112233',
            'password' => 'Siswa123',
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'siswa',
            'nis' => '2400112233',
            'password' => 'Siswa123',
        ])->assertRedirect(route('dashboard.siswa.index'));

        $this->assertAuthenticated();
    }

    public function test_guru_cannot_login_using_email_instead_of_nip(): void
    {
        $guru = User::factory()->guru()->create([
            'email' => 'guru@example.com',
            'password' => 'Guru123',
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => $guru->email,
            'password' => 'Guru123',
        ])->assertSessionHasErrors('nip');

        $this->assertGuest();
    }

    public function test_same_identifier_in_different_roles_does_not_cross_match(): void
    {
        User::factory()->guru()->create([
            'nip' => '9999',
            'password' => 'Guru123',
            'must_change_password' => false,
        ]);
        User::factory()->siswa()->create([
            'nis' => '9999',
            'password' => 'Siswa123',
            'must_change_password' => false,
        ]);

        // Password siswa tidak boleh bisa dipakai untuk login sebagai guru.
        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => '9999',
            'password' => 'Siswa123',
        ])->assertSessionHasErrors('nip');

        $this->assertGuest();

        $this->post(route('login.store'), [
            'role' => 'siswa',
            'nis' => '9999',
            'password' => 'Siswa123',
        ])->assertRedirect(route('dashboard.siswa.index'));

        $this->assertAuthenticated();
    }

    public function test_login_rejects_unknown_role(): void
    {
        $this->post(route('login.store'), [
            'role' => 'operator',
            'email' => 'a@example.com',
            'password' => 'Rahasia123',
        ])->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public function test_login_lockout_key_is_separated_per_role(): void
    {
        User::factory()->guru()->create(['nip' => '9999', 'password' => 'Guru123', 'must_change_password' => false]);
        User::factory()->siswa()->create(['nis' => '9999', 'password' => 'Siswa123', 'must_change_password' => false]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => '9999',
            'password' => 'salah',
        ])->assertSessionHasErrors('nip');

        // NIP siswa dengan nilai sama tidak ikut terkunci.
        $this->assertDatabaseHas('login_attempts', ['email' => 'guru:9999', 'attempts' => 1]);
        $this->assertDatabaseMissing('login_attempts', ['email' => 'siswa:9999']);
    }

    public function test_inactive_account_cannot_login(): void
    {
        User::factory()->guru()->create([
            'nip' => 'GP-2000',
            'password' => 'Guru123',
            'must_change_password' => false,
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'role' => 'guru',
            'nip' => 'GP-2000',
            'password' => 'Guru123',
        ])->assertSessionHasErrors('nip');

        $this->assertGuest();
    }

    // Pengajuan

    public function test_guru_can_submit_reset_request(): void
    {
        Mail::fake();

        $guru = User::factory()->guru()->create(['nip' => 'GP-3000']);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'GP-3000',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $guru->id,
            'role' => 'guru',
            'identifier' => 'GP-3000',
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        // Belum ada token dan password belum berubah.
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertTrue(Hash::check('password', $guru->fresh()->password));
        Mail::assertNothingSent();
    }

    public function test_siswa_can_submit_reset_request(): void
    {
        $siswa = User::factory()->siswa()->create(['nis' => '2400556677']);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'siswa',
            'nis' => '2400556677',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $siswa->id,
            'role' => 'siswa',
            'identifier' => '2400556677',
        ]);
    }

    public function test_admin_cannot_submit_reset_request(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'admin',
            'email' => 'admin@example.com',
        ])->assertStatus(422)->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('password_reset_requests', 0);
    }

    public function test_reset_request_response_is_generic_for_unknown_identifier(): void
    {
        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'TIDAK-ADA',
        ])->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath(
                'message',
                ForgotPasswordController::GENERIC_MESSAGE
            );

        $this->assertDatabaseCount('password_reset_requests', 0);
    }

    public function test_inactive_account_cannot_submit_reset_request(): void
    {
        User::factory()->guru()->create(['nip' => 'GP-4000', 'is_active' => false]);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'GP-4000',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('password_reset_requests', 0);
    }

    public function test_duplicate_pending_request_is_not_created(): void
    {
        $guru = User::factory()->guru()->create(['nip' => 'GP-5000']);

        app(PasswordResetService::class)->submit('guru', 'GP-5000');

        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'GP-5000',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertSame(1, PasswordResetRequest::where('user_id', $guru->id)->count());
    }

    public function test_account_without_email_cannot_submit_reset_request(): void
    {
        User::factory()->guru()->create(['nip' => 'GP-5100', 'email' => null]);

        $this->postJson(route('auth.buat-password'), [
            'role' => 'guru',
            'nip' => 'GP-5100',
        ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseCount('password_reset_requests', 0);
    }

    // Persetujuan admin

    public function test_admin_can_approve_and_token_is_created_and_hashed(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-6000', 'email' => 'guru6@saskansa.sch.id']);

        $request = app(PasswordResetService::class)->submit('guru', 'GP-6000');

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.pengajuan-reset-password.setujui', $request))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $request->id,
            'status' => PasswordResetRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseCount('password_reset_tokens', 1);

        $token = PasswordResetToken::first();
        $this->assertNotEmpty($token->token_hash);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertNull($token->used_at);

        // Nilai mentah token tidak pernah tersimpan di database.
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($guru) {
            return $mail->hasTo($guru->email)
                && str_contains($mail->resetUrl, '/reset-password/');
        });
    }

    public function test_admin_cannot_approve_when_account_has_no_email(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-6100', 'email' => null]);

        $request = PasswordResetRequest::create([
            'user_id' => $guru->id,
            'role' => 'guru',
            'identifier' => 'GP-6100',
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.pengajuan-reset-password.setujui', $request))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $request->id,
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        $this->assertDatabaseCount('password_reset_tokens', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_can_reject_with_reason(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['nip' => 'GP-7000']);

        $request = app(PasswordResetService::class)->submit('guru', 'GP-7000');

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.pengajuan-reset-password.tolak', $request), [
                'rejection_reason' => 'Data akun tidak sesuai, hubungi sekolah.',
            ])->assertOk()->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $request->id,
            'status' => PasswordResetRequest::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'rejection_reason' => 'Data akun tidak sesuai, hubungi sekolah.',
        ]);

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['nip' => 'GP-8000']);

        $request = app(PasswordResetService::class)->submit('guru', 'GP-8000');

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.pengajuan-reset-password.tolak', $request), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');

        $this->assertDatabaseHas('password_reset_requests', [
            'id' => $request->id,
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);
    }

    public function test_request_cannot_be_approved_twice(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['nip' => 'GP-9000']);

        $request = app(PasswordResetService::class)->submit('guru', 'GP-9000');

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.pengajuan-reset-password.setujui', $request))
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(route('dashboard.admin.pengajuan-reset-password.setujui', $request))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_non_admin_cannot_access_request_list(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('dashboard.admin.pengajuan-reset-password.index'))
            ->assertForbidden();
    }

    public function test_approval_invalidates_previous_token(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['nip' => 'GP-9100']);

        $service = app(PasswordResetService::class);
        $first = $service->submit('guru', 'GP-9100');
        $service->approve($first, $admin);

        $this->assertDatabaseCount('password_reset_tokens', 1);

        $second = PasswordResetRequest::create([
            'user_id' => $first->user_id,
            'role' => 'guru',
            'identifier' => 'GP-9100',
            'status' => PasswordResetRequest::STATUS_PENDING,
        ]);

        $service->approve($second, $admin);

        // Token lama dicabut, hanya token terbaru yang tersisa.
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $this->assertNull(PasswordResetToken::first()->used_at);
    }

    public function test_admin_can_filter_request_list_by_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['name' => 'Guru Satu', 'nip' => 'GP-8888']);
        User::factory()->siswa()->create(['name' => 'Siswa Satu', 'nis' => 'SN-9999']);

        $service = app(PasswordResetService::class);
        $service->submit('guru', 'GP-8888');
        $service->submit('siswa', 'SN-9999');

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.pengajuan-reset-password.index', ['role' => 'guru']));

        $response->assertOk()
            ->assertSee('Guru Satu')
            ->assertDontSee('Siswa Satu');

        $responseSiswa = $this->actingAs($admin)
            ->get(route('dashboard.admin.pengajuan-reset-password.index', ['role' => 'siswa']));

        $responseSiswa->assertOk()
            ->assertSee('Siswa Satu')
            ->assertDontSee('Guru Satu');
    }

    public function test_admin_can_search_request_list_by_name_or_identifier(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['name' => 'Budi Santoso', 'nip' => 'GP-1111']);
        User::factory()->guru()->create(['name' => 'Dewi Sartika', 'nip' => 'GP-2222']);

        $service = app(PasswordResetService::class);
        $service->submit('guru', 'GP-1111');
        $service->submit('guru', 'GP-2222');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.pengajuan-reset-password.index', ['search' => 'Budi']))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertDontSee('Dewi Sartika');

        $this->actingAs($admin)
            ->get(route('dashboard.admin.pengajuan-reset-password.index', ['search' => 'GP-2222']))
            ->assertOk()
            ->assertSee('Dewi Sartika')
            ->assertDontSee('Budi Santoso');
    }

    public function test_approved_tab_computes_correct_token_states(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $guruActive = User::factory()->guru()->create(['name' => 'Guru Aktif', 'nip' => 'GP-3331']);
        $guruUsed = User::factory()->guru()->create(['name' => 'Guru Terpakai', 'nip' => 'GP-3332']);
        $guruExpired = User::factory()->guru()->create(['name' => 'Guru Kadaluarsa', 'nip' => 'GP-3333']);

        $service = app(PasswordResetService::class);

        // 1. Token aktif
        $req1 = $service->submit('guru', 'GP-3331');
        $service->approve($req1, $admin);

        // 2. Token sudah dipakai
        $req2 = $service->submit('guru', 'GP-3332');
        $service->approve($req2, $admin);
        $token2 = PasswordResetToken::where('user_id', $guruUsed->id)->first();
        $token2->update([
            'used_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5), // Kadaluwarsa secara waktu, tapi sudah terpakai
        ]);

        // 3. Token kedaluwarsa (belum pernah dipakai)
        $req3 = $service->submit('guru', 'GP-3333');
        $service->approve($req3, $admin);
        $token3 = PasswordResetToken::where('user_id', $guruExpired->id)->first();
        $token3->update([
            'expires_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('dashboard.admin.pengajuan-reset-password.index', ['status' => 'approved']));

        $response->assertOk()
            ->assertSee('Guru Aktif')
            ->assertSee('Tautan Aktif')
            ->assertSee('Guru Terpakai')
            ->assertSee('Password Berhasil Direset')
            ->assertSee('Guru Kadaluarsa')
            ->assertSee('Tautan Kedaluwarsa');
    }

    // Reset via token

    public function test_reset_password_page_is_rendered_for_valid_token(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->siswa()->create(['nis' => '2400123456']);

        $service = app(PasswordResetService::class);
        $request = $service->submit('siswa', '2400123456');
        $service->approve($request, $admin);

        $plain = $this->plainTokenFromMail();

        $this->get(route('password.reset', ['token' => $plain]))
            ->assertOk()
            ->assertSee('Buat Password Baru');
    }

    public function test_reset_password_updates_password_and_marks_token_used(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $siswa = User::factory()->siswa()->create(['nis' => '2400223344']);

        $service = app(PasswordResetService::class);
        $request = $service->submit('siswa', '2400223344');
        $service->approve($request, $admin);

        $plain = $this->plainTokenFromMail();

        $this->postJson(route('password.reset.store', ['token' => $plain]), [
            'password' => 'SiswaBaru123',
            'password_confirmation' => 'SiswaBaru123',
        ])->assertOk()->assertJsonPath('status', 'success');

        $siswa->refresh();

        $this->assertTrue(Hash::check('SiswaBaru123', $siswa->password));
        $this->assertFalse($siswa->must_change_password);
        $this->assertNotNull(PasswordResetToken::first()->used_at);

        // Password baru langsung bisa dipakai untuk login lewat NIS.
        $this->post(route('login.store'), [
            'role' => 'siswa',
            'nis' => '2400223344',
            'password' => 'SiswaBaru123',
        ])->assertRedirect(route('dashboard.siswa.index'));
    }

    public function test_token_is_single_use(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->guru()->create(['nip' => 'GP-9200']);

        $service = app(PasswordResetService::class);
        $request = $service->submit('guru', 'GP-9200');
        $service->approve($request, $admin);

        $plain = $this->plainTokenFromMail();

        $this->postJson(route('password.reset.store', ['token' => $plain]), [
            'password' => 'GuruBaru123',
            'password_confirmation' => 'GuruBaru123',
        ])->assertOk();

        $this->postJson(route('password.reset.store', ['token' => $plain]), [
            'password' => 'GuruLain123',
            'password_confirmation' => 'GuruLain123',
        ])->assertStatus(410);

        $this->assertTrue(Hash::check('GuruBaru123', User::where('nip', 'GP-9200')->first()->password));
    }

    public function test_expired_token_is_rejected(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $guru = User::factory()->guru()->create(['nip' => 'GP-9300']);

        $service = app(PasswordResetService::class);
        $request = $service->submit('guru', 'GP-9300');
        $service->approve($request, $admin);

        $plain = $this->plainTokenFromMail();

        Carbon::setTestNow(now()->addMinutes(PasswordResetService::TOKEN_TTL_MINUTES + 5));

        $this->postJson(route('password.reset.store', ['token' => $plain]), [
            'password' => 'GuruKadaluarsa1',
            'password_confirmation' => 'GuruKadaluarsa1',
        ])->assertStatus(410);

        $this->assertTrue(Hash::check('password', $guru->fresh()->password));
    }

    public function test_unknown_token_page_shows_expired_state(): void
    {
        $this->get(route('password.reset', ['token' => 'token-tidak-ada']))
            ->assertOk()
            ->assertSee('Tautan Tidak Berlaku');
    }

    public function test_reset_password_enforces_password_policy(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        User::factory()->siswa()->create(['nis' => '2400334455']);

        $service = app(PasswordResetService::class);
        $request = $service->submit('siswa', '2400334455');
        $service->approve($request, $admin);

        $plain = $this->plainTokenFromMail();

        $this->postJson(route('password.reset.store', ['token' => $plain]), [
            'password' => 'lemah',
            'password_confirmation' => 'lemah',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_authenticated_user_is_logged_out_after_reset(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $siswa = User::factory()->siswa()->create(['nis' => '2400445566']);

        $service = app(PasswordResetService::class);
        $request = $service->submit('siswa', '2400445566');
        $service->approve($request, $admin);

        $plain = $this->plainTokenFromMail();

        $this->actingAs($siswa)
            ->postJson(route('password.reset.store', ['token' => $plain]), [
                'password' => 'SiswaBaru123',
                'password_confirmation' => 'SiswaBaru123',
            ])->assertOk();

        $this->assertGuest();
    }

    // Helper

    /**
     * Ambil token mentah dari tautan pada email yang dikirim.
     */
    protected function plainTokenFromMail(): string
    {
        $mailable = null;

        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use (&$mailable) {
            $mailable = $mail;

            return true;
        });

        preg_match('#/reset-password/([^/?\s]+)#', $mailable->resetUrl, $matches);

        return $matches[1] ?? '';
    }
}
