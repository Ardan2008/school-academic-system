<?php

namespace Tests\Feature;

use App\Models\JadwalPelajaran;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPenugasanMengajarTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $adminAccount = null;

    protected function admin(): User
    {
        return $this->adminAccount ??= User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'must_change_password' => false,
        ]);
    }

    protected function guru(array $overrides = []): User
    {
        return User::factory()->guru()->withPassword('Guru1234')->create(array_merge([
            'name' => 'Guru Uji',
            'email' => 'guru.uji@example.com',
            'nip' => '9001',
            'mata_pelajaran' => 'Matematika',
        ], $overrides));
    }

    protected function siswa(): User
    {
        return User::factory()->siswa('12-1')->create([
            'name' => 'Siswa Uji',
            'email' => 'siswa.uji@example.com',
            'jurusan' => 'RPL',
            'status' => User::STATUS_AKTIF,
        ]);
    }

    protected function jadwal(User $guru, MataPelajaran $mapel): JadwalPelajaran
    {
        return JadwalPelajaran::create([
            'kelas' => '12-1',
            'jurusan' => 'RPL',
            'hari' => 'Senin',
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:30',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'ruang' => 'R.101',
        ]);
    }

    protected function tugas(User $guru, MataPelajaran $mapel, string $judul): Tugas
    {
        return Tugas::create([
            'kelas' => '12-1',
            'jurusan' => 'RPL',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => $judul,
            'deskripsi' => 'Deskripsi uji.',
            'deadline' => now()->addDays(2),
        ]);
    }

    protected function materi(User $guru, MataPelajaran $mapel, string $judul): Materi
    {
        $path = 'materi/uji/'.Str::random(10).'.pdf';
        Storage::disk('local')->put($path, 'isi materi uji');

        return Materi::create([
            'kelas' => '12-1',
            'jurusan' => 'RPL',
            'mata_pelajaran_id' => $mapel->id,
            'guru_id' => $guru->id,
            'judul' => $judul,
            'deskripsi' => 'Deskripsi uji.',
            'tanggal' => now()->toDateString(),
            'file_path' => $path,
            'nama_file' => $judul.'.pdf',
            'mime_type' => 'application/pdf',
            'ekstensi' => 'pdf',
            'ukuran_bytes' => 14,
        ]);
    }

    protected function tetapkan(User $guru, array $penugasan): void
    {
        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => $penugasan,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_admin_dapat_melihat_daftar_penugasan_guru(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-1']);

        $this->actingAs($admin)
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.guru.id', $guru->id)
            ->assertJsonPath('data.guru.nama', $guru->name)
            ->assertJsonPath('data.mapel.0.id', $mapel->id)
            ->assertJsonCount(1, 'data.mapel')
            ->assertJsonMissing(['nama' => 'Fisika'])
            ->assertJsonStructure(['data' => ['mapel', 'kelas', 'penugasan']]);

        $this->assertTrue($mapel->exists);
        $this->assertTrue($kelas->exists);
    }

    public function test_dropdown_penugasan_hanya_berisi_mapel_yang_tersimpan_di_data_guru(): void
    {
        $guru = $this->guru();
        $matematika = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertOk()
            ->assertJsonCount(1, 'data.mapel')
            ->assertJsonPath('data.mapel.0.id', $matematika->id)
            ->assertJsonPath('data.mapel.0.nama', 'Matematika')
            ->assertJsonMissing(['nama' => 'Fisika']);
    }

    public function test_dropdown_penugasan_mendukung_beberapa_mapel_data_guru(): void
    {
        $guru = $this->guru(['mata_pelajaran' => 'Fisika, Matematika']);
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $matematika = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertOk()
            ->assertJsonCount(2, 'data.mapel')
            ->assertJsonPath('data.mapel.0.nama', 'Fisika')
            ->assertJsonPath('data.mapel.1.nama', 'Matematika');

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $fisika->id, 'kelas_ids' => [$kelas->id]],
            ['mata_pelajaran_id' => $matematika->id, 'kelas_ids' => [$kelas->id]],
        ]);

        $this->assertDatabaseCount('penugasan_mengajar', 2);
    }

    public function test_validasi_menolak_mapel_di_luar_data_guru(): void
    {
        $guru = $this->guru();
        MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => [
                    ['mata_pelajaran_id' => $fisika->id, 'kelas_ids' => [$kelas->id]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['penugasan.0.mata_pelajaran_id']);

        $this->assertDatabaseMissing('penugasan_mengajar', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $fisika->id,
        ]);
    }

    public function test_dropdown_penugasan_mengikuti_perubahan_data_guru(): void
    {
        $guru = $this->guru();
        MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.update', ['user' => $guru]), [
                'nis' => $guru->nip,
                'nama' => $guru->name,
                'email' => $guru->email,
                'mapel' => 'Fisika',
                'status' => User::STATUS_AKTIF,
            ])
            ->assertOk();

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertOk()
            ->assertJsonCount(1, 'data.mapel')
            ->assertJsonPath('data.mapel.0.id', $fisika->id)
            ->assertJsonPath('data.mapel.0.nama', 'Fisika');
    }

    public function test_penugasan_lama_tetap_dimuat_saat_mapel_data_guru_berubah(): void
    {
        $guru = $this->guru(['mata_pelajaran' => 'Fisika']);
        MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $fisika->id, 'kelas_ids' => [$kelas->id]],
        ]);

        $guru->update(['mata_pelajaran' => 'Matematika']);

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertOk()
            ->assertJsonCount(1, 'data.mapel')
            ->assertJsonPath('data.mapel.0.nama', 'Matematika')
            ->assertJsonPath('data.penugasan.0.mata_pelajaran_id', $fisika->id)
            ->assertJsonPath('data.penugasan.0.nama', 'Fisika');
    }

    public function test_field_mapel_kelola_penugasan_readonly_dengan_gembok(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<select data-penugasan-mapel', $html);
        $this->assertStringContainsString('data-penugasan-mapel-tampil', $html);
        $this->assertStringContainsString('readonly aria-readonly="true" disabled tabindex="-1"', $html);
        $this->assertStringContainsString('cursor-not-allowed select-none', $html);
        $this->assertStringContainsString('lucide-lock', $html);
        $this->assertStringContainsString('pointer-events-none absolute right-3 top-1/2', $html);
    }

    public function test_pemilihan_kelas_kelola_penugasan_menggunakan_kartu_selectable(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Tahan Ctrl/Cmd', $html);
        $this->assertStringContainsString('data-penugasan-kelas-card', $html);
        $this->assertStringContainsString('penugasan-kelas-card', $html);
        $this->assertStringContainsString('role="checkbox"', $html);
        $this->assertStringContainsString('aria-checked', $html);
        $this->assertStringContainsString('max-h-52', $html);
        $this->assertStringContainsString('data-penugasan-kelas-count', $html);
        $this->assertStringContainsString('data-penugasan-kelas-all', $html);
        $this->assertStringContainsString('Pilih Semua Kelas', $html);
        $this->assertStringContainsString('Batalkan Semua Pilihan', $html);
        $this->assertStringContainsString('kelas dipilih', $html);
    }

    public function test_halaman_admin_menampilkan_info_saat_data_guru_tanpa_mapel(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee('data-penugasan-tanpa-mapel', false)
            ->assertSee('Mata pelajaran belum tersedia');
    }

    public function test_non_admin_tidak_bisa_mengakses_penugasan_guru(): void
    {
        $guru = $this->guru();
        $user = User::factory()->guru()->withPassword('Guru1234')->create();

        $this->actingAs($user)
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertStatus(403);

        $this->actingAs($user)
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => [],
            ])
            ->assertStatus(403);
    }

    public function test_penugasan_hanya_untuk_akun_guru(): void
    {
        $siswa = $this->siswa();

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $siswa]))
            ->assertNotFound();

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $siswa]), [
                'penugasan' => [],
            ])
            ->assertNotFound();
    }

    public function test_admin_dapat_menyimpan_penugasan_baru(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $kelas = KelasRombel::create(['jurusan' => 'Rekayasa Perangkat Lunak', 'kelas' => '12-1']);

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => [
                    [
                        'mata_pelajaran_id' => $mapel->id,
                        'kelas_ids' => [$kelas->id],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('penugasan_mengajar', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);
        $this->assertTrue($guru->fresh()->penugasan_diatur);
    }

    public function test_validasi_menolak_mapel_atau_kelas_tidak_ditemukan(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => [
                    [
                        'mata_pelajaran_id' => 99999,
                        'kelas_ids' => [99999],
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['penugasan.0.mata_pelajaran_id', 'penugasan.0.kelas_ids.0']);
    }

    public function test_validasi_menolak_mapel_duplikat(): void
    {
        $admin = $this->admin();
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $this->actingAs($admin)
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => [
                    ['mata_pelajaran_id' => $mapel->id, 'kelas_ids' => [$kelas->id]],
                    ['mata_pelajaran_id' => $mapel->id, 'kelas_ids' => [$kelas->id]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['penugasan']);
    }

    public function test_edit_penugasan_tidak_menghapus_data_akademik_lama(): void
    {
        Storage::fake('local');

        $guru = $this->guru(['mata_pelajaran' => 'Matematika, Fisika']);
        $mapelLama = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $mapelBaru = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $jadwal = $this->jadwal($guru, $mapelLama);
        $tugas = $this->tugas($guru, $mapelLama, 'Tugas Lama');
        $materi = $this->materi($guru, $mapelLama, 'Materi Lama');

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $mapelLama->id, 'kelas_ids' => [$kelas->id]],
        ]);
        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $mapelBaru->id, 'kelas_ids' => [$kelas->id]],
        ]);

        $this->assertDatabaseMissing('penugasan_mengajar', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapelLama->id,
        ]);
        $this->assertDatabaseHas('penugasan_mengajar', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapelBaru->id,
            'kelas_id' => $kelas->id,
        ]);
        $this->assertDatabaseHas('jadwal', ['id' => $jadwal->id]);
        $this->assertDatabaseHas('tugas', ['id' => $tugas->id]);
        $this->assertDatabaseHas('materi', ['id' => $materi->id]);
    }

    public function test_form_guru_mengikuti_penugasan_admin(): void
    {
        $guru = $this->guru();
        $mapelDiampu = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $mapelLain = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $this->jadwal($guru, $mapelDiampu);
        $this->jadwal($guru, $mapelLain);

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $mapelDiampu->id, 'kelas_ids' => [$kelas->id]],
        ]);
        $this->siswa();

        $html = $this->actingAs($guru)
            ->get(route('dashboard.guru.tugas-materi.index'))
            ->assertOk()
            ->assertDontSee('<select id="tugas-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="tugas-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="tugas-mapel" name="mata_pelajaran_id" value="'.$mapelDiampu->id.'">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="hidden" id="tugas-mapel" name="mata_pelajaran_id" value="'.$mapelLain->id.'">/', $html);

        $html = $this->actingAs($guru)
            ->get(route('dashboard.guru.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertDontSee('<select id="jadwal-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="jadwal-mapel-tampil"(?=[^>]*value="Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="jadwal-mapel" name="mata_pelajaran_id" value="'.$mapelDiampu->id.'">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="hidden" id="jadwal-mapel" name="mata_pelajaran_id" value="'.$mapelLain->id.'">/', $html);

        $html = $this->actingAs($guru)
            ->get(route('dashboard.guru.input-nilai.index'))
            ->assertOk()
            ->assertDontSee('<select id="nilai-mapel"', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="nilai-mapel-tampil"(?=[^>]*value="MP1 · Matematika")(?=[^>]*disabled)(?=[^>]*tabindex="-1")(?=[^>]*cursor-not-allowed)(?=[^>]*select-none)/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" id="nilai-mapel" name="mata_pelajaran_id" value="'.$mapelDiampu->id.'">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="hidden" id="nilai-mapel" name="mata_pelajaran_id" value="'.$mapelLain->id.'">/', $html);
    }

    public function test_penugasan_admin_mengatur_data_yang_dilihat_siswa(): void
    {
        Storage::fake('local');

        $guruDiampu = $this->guru([
            'name' => 'Guru Matematika',
            'email' => 'guru.matematika@example.com',
            'nip' => '9002',
        ]);
        $guruLain = $this->guru([
            'name' => 'Guru Fisika',
            'email' => 'guru.fisika@example.com',
            'nip' => '9003',
            'mata_pelajaran' => 'Fisika',
        ]);
        $mapelDiampu = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $mapelLain = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $siswa = $this->siswa();
        $this->jadwal($guruDiampu, $mapelDiampu);
        $this->jadwal($guruLain, $mapelLain);
        $tugasDiampu = $this->tugas($guruDiampu, $mapelDiampu, 'Tugas Matematika');
        $tugasLain = $this->tugas($guruLain, $mapelLain, 'Tugas Fisika');
        $materiDiampu = $this->materi($guruDiampu, $mapelDiampu, 'Materi Matematika');
        $materiLain = $this->materi($guruLain, $mapelLain, 'Materi Fisika');

        $this->tetapkan($guruDiampu, [
            ['mata_pelajaran_id' => $mapelDiampu->id, 'kelas_ids' => [$kelas->id]],
        ]);
        $this->tetapkan($guruLain, []);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Tugas Matematika')
            ->assertDontSee('Tugas Fisika');

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.index', ['tab' => 'materi']))
            ->assertOk()
            ->assertSee('Materi Matematika')
            ->assertDontSee('Materi Fisika');

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.jadwal-pelajaran.index'))
            ->assertOk()
            ->assertSee('Matematika')
            ->assertDontSee('Fisika');

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.tugas.show', $tugasDiampu))
            ->assertOk();
        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.tugas.show', $tugasLain))
            ->assertNotFound();
        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.show', $materiDiampu))
            ->assertOk();
        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.materi.show', $materiLain))
            ->assertNotFound();
    }

    public function test_beberapa_mapel_dan_kelas_tersimpan_tanpa_duplikat_dan_dimuat_ulang(): void
    {
        $guru = $this->guru(['mata_pelajaran' => 'Matematika, Fisika']);
        $matematika = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelasA = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $kelasB = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '11-1']);

        $this->tetapkan($guru, [
            [
                'mata_pelajaran_id' => $matematika->id,
                'kelas_ids' => [$kelasA->id, $kelasB->id, $kelasA->id],
            ],
            [
                'mata_pelajaran_id' => $fisika->id,
                'kelas_ids' => [$kelasB->id],
            ],
        ]);

        $this->assertDatabaseCount('penugasan_mengajar', 3);

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.admin.data-guru.penugasan', ['user' => $guru]))
            ->assertOk()
            ->assertJsonCount(2, 'data.penugasan')
            ->assertJsonFragment(['mata_pelajaran_id' => $matematika->id, 'nama' => 'Matematika'])
            ->assertJsonFragment(['mata_pelajaran_id' => $fisika->id, 'nama' => 'Fisika']);
    }

    public function test_validasi_gagal_tidak_menghapus_penugasan_lama(): void
    {
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $mapel->id, 'kelas_ids' => [$kelas->id]],
        ]);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.penugasan.update', ['user' => $guru]), [
                'penugasan' => [
                    ['mata_pelajaran_id' => 99999, 'kelas_ids' => [$kelas->id]],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('penugasan_mengajar', 1);
        $this->assertDatabaseHas('penugasan_mengajar', [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelas->id,
        ]);
    }

    public function test_penugasan_kosong_tetap_menyimpan_baris_guru_di_tabel_admin(): void
    {
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $mapel->id, 'kelas_ids' => [$kelas->id]],
        ]);
        $this->tetapkan($guru, []);

        $this->assertDatabaseCount('penugasan_mengajar', 0);
        $this->assertTrue($guru->fresh()->penugasan_diatur);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertViewHas('guruData', function (array $data) use ($guru): bool {
                $baris = collect($data['guru'])->firstWhere('id', $guru->id);

                return $baris !== null
                    && $baris['mapel'] === null
                    && $baris['mapel_list'] === [];
            });
    }

    public function test_guru_belum_diatur_tetap_terlihat_di_siswa_setelah_guru_lain_diatur(): void
    {
        $guruDiatur = $this->guru([
            'name' => 'Guru Matematika',
            'email' => 'guru.matematika2@example.com',
            'nip' => '9101',
        ]);
        $guruLegacy = $this->guru([
            'name' => 'Guru Fisika Legacy',
            'email' => 'guru.fisika2@example.com',
            'nip' => '9102',
            'mata_pelajaran' => 'Fisika',
        ]);
        $matematika = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $siswa = $this->siswa();

        $this->tugas($guruDiatur, $matematika, 'Tugas Matematika Diatur');
        $this->tugas($guruLegacy, $fisika, 'Tugas Fisika Legacy');

        $this->tetapkan($guruDiatur, [
            ['mata_pelajaran_id' => $matematika->id, 'kelas_ids' => [$kelas->id]],
        ]);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.index'))
            ->assertOk()
            ->assertSee('Tugas Matematika Diatur')
            ->assertSee('Tugas Fisika Legacy');
    }

    public function test_penugasan_kosong_menyembunyikan_data_lama_guru_tersebut(): void
    {
        $guru = $this->guru();
        $mapel = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $kelas = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $siswa = $this->siswa();

        $this->tugas($guru, $mapel, 'Tugas Lama Guru Kosong');
        $this->tetapkan($guru, []);

        $this->actingAs($siswa)
            ->get(route('dashboard.siswa.tugas-materi.index'))
            ->assertOk()
            ->assertDontSee('Tugas Lama Guru Kosong');
    }

    public function test_tabel_data_guru_menampilkan_ringkasan_kelas_diampu(): void
    {
        $guru = $this->guru(['mata_pelajaran' => 'Matematika, Fisika']);
        $matematika = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelasSatu = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $kelasDua = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-2']);
        $kelasTiga = KelasRombel::create(['jurusan' => 'Teknik', 'kelas' => '11-1']);

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $matematika->id, 'kelas_ids' => [$kelasSatu->id, $kelasDua->id]],
            ['mata_pelajaran_id' => $fisika->id, 'kelas_ids' => [$kelasDua->id, $kelasTiga->id]],
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee('Kelas Diampu')
            ->assertSee('Lihat Detail')
            ->assertViewHas('guruData', function (array $data) use ($guru): bool {
                $baris = collect($data['guru'])->firstWhere('id', $guru->id);
                $ids = collect($baris['kelas_diampu'] ?? [])->pluck('id')->all();

                return $baris !== null
                    && count($ids) === 3
                    && count(array_unique($ids)) === 3
                    && collect($baris['kelas_detail'])->pluck('nama')->sort()->values()->all() === ['Fisika', 'Matematika'];
            });
    }

    public function test_popup_detail_kelas_diampu_menggunakan_data_penugasan_existing(): void
    {
        $guru = $this->guru(['mata_pelajaran' => 'Matematika, Fisika']);
        $matematika = MataPelajaran::factory()->nama('Matematika')->kode('MP1')->create();
        $fisika = MataPelajaran::factory()->nama('Fisika')->kode('MP2')->create();
        $kelasSatu = KelasRombel::create(['jurusan' => 'RPL', 'kelas' => '12-1']);
        $kelasDua = KelasRombel::create(['jurusan' => 'Teknik', 'kelas' => '11-1']);

        $this->tetapkan($guru, [
            ['mata_pelajaran_id' => $matematika->id, 'kelas_ids' => [$kelasSatu->id]],
            ['mata_pelajaran_id' => $fisika->id, 'kelas_ids' => [$kelasDua->id]],
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee('Detail Kelas yang Diampu')
            ->assertSee('data-kelas-detail-nama', false)
            ->assertSee('data-kelas-detail-nip', false)
            ->assertSee('data-kelas-detail-email', false)
            ->assertSee('data-kelas-detail-mapel', false)
            ->assertSee('data-kelas-detail-list', false)
            ->assertViewHas('guruData', function (array $data) use ($guru, $kelasSatu, $kelasDua): bool {
                $baris = collect($data['guru'])->firstWhere('id', $guru->id);
                $grup = collect($baris['kelas_detail'] ?? [])->keyBy('nama');

                return $baris !== null
                    && $baris['nama'] === 'Guru Uji'
                    && $baris['nip'] === '9001'
                    && $baris['email'] === 'guru.uji@example.com'
                    && $grup->has('Matematika')
                    && $grup->has('Fisika')
                    && collect($grup->get('Matematika')['kelas'])->pluck('id')->all() === [$kelasSatu->id]
                    && collect($grup->get('Fisika')['kelas'])->pluck('id')->all() === [$kelasDua->id];
            });
    }

    public function test_guru_tanpa_penugasan_tidak_memiliki_kelas_diampu(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertViewHas('guruData', function (array $data) use ($guru): bool {
                $baris = collect($data['guru'])->firstWhere('id', $guru->id);

                return $baris !== null
                    && $baris['kelas_diampu'] === []
                    && $baris['kelas_detail'] === [];
            });
    }
}
