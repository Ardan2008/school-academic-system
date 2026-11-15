<?php

namespace Tests\Feature;

use App\Models\ItHelpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminKendalaAkunFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clear();
    }

    private function buatPermintaan(array $overrides = []): ItHelpRequest
    {
        $this->sequence++;

        return ItHelpRequest::create(array_merge([
            'role' => 'siswa',
            'identifier' => '9900'.$this->sequence,
            'email' => 'pengaju'.$this->sequence.'@saskansa.sch.id',
            'pesan' => 'Kendala akun nomor '.$this->sequence.'.',
            'status' => ItHelpRequest::STATUS_BARU,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_filter_status_menampilkan_hanya_pengajuan_dengan_status_yang_dipilih(): void
    {
        $admin = $this->admin();

        $perStatus = [
            ItHelpRequest::STATUS_BARU => $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU]),
            ItHelpRequest::STATUS_DIPROSES => $this->buatPermintaan(['status' => ItHelpRequest::STATUS_DIPROSES]),
            ItHelpRequest::STATUS_SUDAH_DIBALAS => $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SUDAH_DIBALAS]),
            ItHelpRequest::STATUS_SELESAI => $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SELESAI]),
        ];

        foreach ($perStatus as $status => $expected) {
            $response = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
                'status' => $status,
            ]));

            $response->assertOk()->assertViewHas('status', $status);

            $requests = $response->viewData('requests');

            $this->assertSame(1, $requests->total(), "Total filter {$status} salah.");
            $this->assertSame([$expected->id], $requests->pluck('id')->all(), "Isi filter {$status} salah.");
        }
    }

    public function test_filter_status_dapat_digabung_dengan_role_dan_pencarian(): void
    {
        $admin = $this->admin();

        $guru = User::factory()->guru()->create(['name' => 'Pak Guru Khusus']);
        $siswa = User::factory()->siswa('12-1')->create(['name' => 'Siswa Khusus']);

        $target = $this->buatPermintaan([
            'user_id' => $guru->id,
            'role' => 'guru',
            'identifier' => 'GP-7001',
            'email' => 'guru.target@saskansa.sch.id',
            'pesan' => 'Tidak bisa masuk ke akun guru saya.',
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]);

        $this->buatPermintaan([
            'user_id' => $siswa->id,
            'role' => 'siswa',
            'identifier' => '997001',
            'email' => 'siswa.target@saskansa.sch.id',
            'pesan' => 'Tidak bisa masuk ke akun siswa saya.',
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]);

        $this->buatPermintaan([
            'user_id' => $guru->id,
            'role' => 'guru',
            'identifier' => 'GP-7002',
            'email' => 'guru.selesai@saskansa.sch.id',
            'pesan' => 'Kendala guru yang sudah selesai.',
            'status' => ItHelpRequest::STATUS_SELESAI,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
            'status' => ItHelpRequest::STATUS_DIPROSES,
            'role' => 'guru',
            'search' => 'Pak Guru Khusus',
        ]));

        $response->assertOk()
            ->assertViewHas('status', ItHelpRequest::STATUS_DIPROSES)
            ->assertViewHas('role', 'guru')
            ->assertViewHas('search', 'Pak Guru Khusus');

        $requests = $response->viewData('requests');

        $this->assertSame(1, $requests->total());
        $this->assertSame([$target->id], $requests->pluck('id')->all());
    }

    public function test_jumlah_kartu_status_tetap_menghitung_seluruh_pengajuan_saat_filter_aktif(): void
    {
        $admin = $this->admin();

        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU]);
        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU]);
        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_DIPROSES]);
        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SUDAH_DIBALAS]);
        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SUDAH_DIBALAS]);
        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SELESAI]);

        $response = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]));

        $response->assertOk();

        $this->assertSame([
            ItHelpRequest::STATUS_BARU => 2,
            ItHelpRequest::STATUS_DIPROSES => 1,
            ItHelpRequest::STATUS_SUDAH_DIBALAS => 2,
            ItHelpRequest::STATUS_SELESAI => 1,
        ], $response->viewData('counts'));

        $this->assertSame(1, $response->viewData('requests')->total());
    }

    public function test_pagination_mengikuti_hasil_filter_status(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 16; $i++) {
            $this->buatPermintaan(['status' => ItHelpRequest::STATUS_DIPROSES]);
        }

        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU]);

        $first = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]));

        $first->assertOk();

        $firstPage = $first->viewData('requests');

        $this->assertSame(16, $firstPage->total());
        $this->assertSame(15, $firstPage->count());
        $this->assertSame(2, $firstPage->lastPage());
        $this->assertStringContainsString('status=diproses', $firstPage->nextPageUrl());

        $second = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
            'status' => ItHelpRequest::STATUS_DIPROSES,
            'page' => 2,
        ]));

        $second->assertOk();

        $secondPage = $second->viewData('requests');

        $this->assertSame(16, $secondPage->total());
        $this->assertSame(1, $secondPage->count());
    }

    public function test_status_dan_role_tidak_dikenal_diabaikan(): void
    {
        $admin = $this->admin();

        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU, 'role' => 'siswa']);
        $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SELESAI, 'role' => 'guru']);

        $response = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
            'status' => 'hilang',
            'role' => 'admin',
        ]));

        $response->assertOk()
            ->assertViewHas('status', '')
            ->assertViewHas('role', '');

        $this->assertSame(2, $response->viewData('requests')->total());
    }

    public function test_membuka_detail_menandai_pengajuan_baru_sebagai_diproses(): void
    {
        $admin = $this->admin();
        $permintaan = $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $permintaan))
            ->assertOk()
            ->assertSee('Diproses');

        $this->assertDatabaseHas('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard.admin.kendala-akun.index', [
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]));

        $response->assertOk();

        $this->assertSame([$permintaan->id], $response->viewData('requests')->pluck('id')->all());
    }

    public function test_membuka_halaman_balas_menandai_pengajuan_baru_sebagai_diproses(): void
    {
        $admin = $this->admin();
        $permintaan = $this->buatPermintaan(['status' => ItHelpRequest::STATUS_BARU]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.balas.create', $permintaan))
            ->assertOk()
            ->assertSee('Diproses');

        $this->assertDatabaseHas('it_help_requests', [
            'id' => $permintaan->id,
            'status' => ItHelpRequest::STATUS_DIPROSES,
        ]);
    }

    public function test_membuka_detail_tidak_mengubah_status_yang_sudah_diproses_atau_selesai(): void
    {
        $admin = $this->admin();
        $sudahDibalas = $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SUDAH_DIBALAS]);
        $selesai = $this->buatPermintaan(['status' => ItHelpRequest::STATUS_SELESAI]);

        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $sudahDibalas))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.kendala-akun.show', $selesai))
            ->assertOk();

        $this->assertSame(ItHelpRequest::STATUS_SUDAH_DIBALAS, $sudahDibalas->refresh()->status);
        $this->assertSame(ItHelpRequest::STATUS_SELESAI, $selesai->refresh()->status);
    }

    public function test_halaman_daftar_kendala_akun_hanya_untuk_admin(): void
    {
        $url = route('dashboard.admin.kendala-akun.index');

        $this->get($url)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->guru()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->siswa('10-1')->create())->get($url)->assertForbidden();
        $this->actingAs($this->admin())->get($url)->assertOk();
    }
}
