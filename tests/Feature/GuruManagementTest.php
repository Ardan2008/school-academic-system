<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSpreadsheet;
use Tests\TestCase;

class GuruManagementTest extends TestCase
{
    use InteractsWithSpreadsheet;
    use RefreshDatabase;

    protected ?User $adminAccount = null;

    protected function admin(): User
    {
        // Disimpan sekali per test supaya tidak bentrok dengan akun admin yang
        // sudah dibuat sebelumnya.
        return $this->adminAccount ??= User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'must_change_password' => false,
        ]);
    }

    protected function guru(array $overrides = []): User
    {
        return User::factory()->guru()->withPassword('Guru1234')->create(array_merge([
            'name' => 'Dewi Lestari',
            'email' => fake()->unique()->safeEmail(),
            'nip' => '1001',
            'mata_pelajaran' => 'Fisika',
        ], $overrides));
    }

    public function test_halaman_data_guru_tampil(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee($guru->name)
            ->assertSee('Data Guru');
    }

    public function test_export_mengunduh_xlsx_berisi_password_awal(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.export'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString('Nama Guru', $isi);
        $this->assertStringContainsString('Password Awal', $isi);
        $this->assertStringContainsString('Status Password', $isi);
        $this->assertStringContainsString($guru->name, $isi);
        $this->assertStringContainsString('Guru1234', $isi);
        $this->assertStringContainsString('Fisika', $isi);
    }

    public function test_export_mengikuti_filter_status(): void
    {
        $this->guru();
        $cuti = $this->guru(['name' => 'Budi Susanti', 'status' => 'Cuti']);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.export', ['status' => 'Cuti']))
            ->assertOk();

        $isi = $this->gabungBukuKerja($this->bacaBukuKerja($response));

        $this->assertStringContainsString($cuti->name, $isi);
        $this->assertStringNotContainsString('Dewi Lestari', $isi);
    }

    public function test_tambah_guru_membuat_akun_dengan_password_otomatis(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.guru.nip', '2001')
            ->assertJsonPath('data.guru.nama', 'Ahmad Fauzi');

        $password = $response->json('data.password');
        $this->assertNotEmpty($password);
        $this->assertSame(8, strlen($password));

        $this->assertDatabaseHas('users', [
            'nip' => '2001',
            'name' => 'Ahmad Fauzi',
            'role' => 'guru',
            'mata_pelajaran' => 'Matematika',
            'must_change_password' => true,
        ]);
    }

    public function test_password_otomatis_berbeda_antar_guru(): void
    {
        $admin = $this->admin();

        $guruAkbar = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Akbar Pratama',
                'email' => 'akbar.pratama@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->json('data.password');

        $guruRina = $this->actingAs($admin)
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2002',
                'nama' => 'Rina Wulandari',
                'email' => 'rina.wulandari@example.com',
                'mapel' => 'Fisika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertOk()
            ->json('data.password');

        $this->assertNotSame($guruAkbar, $guruRina);
        $this->assertNotSame($guruAkbar, $this->guru()->fresh()->visiblePassword());
    }

    public function test_password_penentu_yang_bentrok_diganti_otomatis(): void
    {
        $this->guru(['nip' => '1001']);

        $response = $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '2001',
                'nama' => 'Akbar Pratama',
                'email' => 'akbar.pratama@example.com',
                'mapel' => 'Matematika',
                'status' => 'Aktif',
                // Nilai ini sama dengan password awal guru pertama, jadi harus
                // ditolak dan diganti oleh sistem.
                'password' => 'Guru1234',
            ])
            ->assertOk();

        $this->assertNotSame('Guru1234', $response->json('data.password'));
    }

    public function test_ubah_guru_mengganti_data_tanpa_mengubah_password(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.update', ['user' => $guru]), [
                'nis' => '1001',
                'nama' => 'Dewi Lestari, S.Pd.',
                'email' => 'dewi.lestari@example.com',
                'mapel' => 'Kimia',
                'status' => 'Cuti',
                'password' => '',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.guru.nama', 'Dewi Lestari, S.Pd.')
            ->assertJsonPath('data.guru.mapel', 'Kimia')
            ->assertJsonPath('data.guru.status', 'Cuti')
            ->assertJsonPath('data.password', null);

        $this->assertDatabaseHas('users', [
            'id' => $guru->id,
            'name' => 'Dewi Lestari, S.Pd.',
            'status' => 'Cuti',
            'must_change_password' => true,
        ]);

        $this->assertSame('Guru1234', $guru->fresh()->visiblePassword());
    }

    public function test_hapus_guru_menghapus_akun(): void
    {
        $guru = $this->guru();

        $this->actingAs($this->admin())
            ->deleteJson(route('dashboard.admin.data-guru.destroy', ['user' => $guru]))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
    }

    public function test_nip_duplikat_ditolak(): void
    {
        $this->guru(['email' => 'dewi.lestari@example.com', 'nip' => '1001']);

        $this->actingAs($this->admin())
            ->postJson(route('dashboard.admin.data-guru.store'), [
                'nis' => '1001',
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lain@example.com',
                'mapel' => 'Fisika',
                'status' => 'Aktif',
                'password' => 'Guru1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nis');
    }

    public function test_ubah_data_bukan_guru_ditolak(): void
    {
        $siswa = User::factory()->siswa()->create(['email' => 'siswa@example.com']);

        $this->actingAs($this->admin())
            ->putJson(route('dashboard.admin.data-guru.update', ['user' => $siswa]), [
                'nis' => '1001',
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lestari@example.com',
                'mapel' => 'Fisika',
                'status' => 'Aktif',
                'password' => '',
            ])
            ->assertNotFound();
    }

    public function test_seluruh_data_guru_tersedia_pada_pemuatan_awal(): void
    {
        $pertama = $this->guru([
            'name' => 'Guru Awal Pertama',
            'nip' => '1101',
            'email' => 'guru.pertama@example.com',
        ]);
        $kedua = $this->guru([
            'name' => 'Guru Awal Kedua',
            'nip' => '1102',
            'email' => 'guru.kedua@example.com',
        ]);
        $tanpaPenugasan = $this->guru([
            'name' => 'Guru Awal Tanpa Penugasan',
            'nip' => '1103',
            'email' => 'guru.tanpa@example.com',
            'penugasan_diatur' => true,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee($pertama->name)
            ->assertSee($kedua->name)
            ->assertSee($tanpaPenugasan->name)
            ->assertViewHas('guruData', function (array $data) use ($pertama, $kedua, $tanpaPenugasan): bool {
                $nama = collect($data['guru'])->pluck('nama')->all();
                $baris = collect($data['guru'])->firstWhere('id', $tanpaPenugasan->id);

                return count($nama) === 3
                    && in_array($pertama->name, $nama, true)
                    && in_array($kedua->name, $nama, true)
                    && $baris !== null
                    && $baris['mapel'] === null
                    && $baris['mapel_list'] === [];
            })
            ->getContent();

        $this->assertStringContainsString('"nama":"'.$pertama->name.'"', $html);
        $this->assertStringContainsString('"nama":"'.$kedua->name.'"', $html);
    }

    public function test_kontrol_modal_penugasan_diikat_dari_root_modal(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString("document.getElementById('penugasan-modal')", $sumber);
        $this->assertStringContainsString("document.getElementById('penugasan-form')", $sumber);
        $this->assertStringNotContainsString("$('#penugasan-modal')", $sumber);
        $this->assertStringNotContainsString("$('#penugasan-form')", $sumber);

        foreach (['simpan', 'tutup', 'batal'] as $kontrol) {
            $this->assertStringContainsString(
                "penugasanModal.querySelector('[data-penugasan-{$kontrol}]')",
                $sumber
            );
            $this->assertStringNotContainsString(
                "penugasanForm.querySelector('[data-penugasan-{$kontrol}]')",
                $sumber
            );
        }
    }

    public function test_pencarian_modal_penugasan_hanya_menyaring_daftar_modal(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString('id="penugasan-overview-search"', $sumber);
        $this->assertStringContainsString('data-penugasan-overview-search-clear', $sumber);
        $this->assertStringContainsString('Cari nama, NIP, email, mapel, atau kelas', $sumber);
        $this->assertStringContainsString("let penugasanOverviewQuery = '';", $sumber);
        $this->assertStringContainsString('rows.filter((g) => cocokPenugasan(g, penugasanOverviewQuery))', $sumber);
        $this->assertStringContainsString('...mapel,', $sumber);
        $this->assertStringContainsString('...kelas.flatMap((k) => [k.nama, k.kelas, k.jurusan])', $sumber);
        $this->assertStringContainsString("penugasanOverviewSearch?.addEventListener('input'", $sumber);
        $this->assertStringContainsString('data-penugasan-overview-reset', $sumber);
        $this->assertStringContainsString('Guru tidak ditemukan', $sumber);
        $this->assertStringContainsString('ICON_SEARCH_X', $sumber);
        $this->assertStringNotContainsString('state.q = penugasanOverviewQuery', $sumber);
    }

    public function test_indikator_kelas_modal_penugasan_memakai_popover_portal(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString('data-overview-kelas-popover="${g.id}"', $sumber);
        $this->assertStringContainsString('aria-expanded="false"', $sumber);
        $this->assertStringContainsString('const tutupPopoverKelas = () => {', $sumber);
        $this->assertStringContainsString('const bukaPopoverKelas = (button) => {', $sumber);
        $this->assertStringContainsString('penugasan-kelas-popover fixed z-[90]', $sumber);
        $this->assertStringContainsString('overflow-hidden rounded-xl border border-slate-200 bg-white', $sumber);
        $this->assertStringContainsString('class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 bg-white px-3 py-2"', $sumber);
        $this->assertStringContainsString('>SEMUA KELAS YANG DIAJAR</p>', $sumber);
        $this->assertStringContainsString('class="space-y-1.5 px-3 py-2 ${kelasScrollClass(daftar.length)}"', $sumber);
        $this->assertStringContainsString("document.addEventListener('pointerdown'", $sumber);
        $this->assertStringContainsString('if (tutupPopoverKelas()) return;', $sumber);
    }

    public function test_tooltip_clipboard_modal_penugasan_menggunakan_portal(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString('data-overview-tooltip="Kelola Penugasan Mengajar"', $sumber);
        $this->assertStringContainsString("tip.id = 'penugasan-tooltip'", $sumber);
        $this->assertStringContainsString('document.body.appendChild(tip)', $sumber);
        $this->assertStringContainsString('background: #1e293b', $sumber);
        $this->assertStringContainsString('transition: opacity .18s ease-out', $sumber);
        $this->assertStringContainsString('z-index: 90', $sumber);
        $this->assertStringContainsString("penugasanOverview.addEventListener('pointerover'", $sumber);
        $this->assertStringContainsString("penugasanOverview.addEventListener('focusin'", $sumber);
        $this->assertStringNotContainsString('title="Kelola penugasan"', $sumber);
        $this->assertStringContainsString('data-overview-kelola="${g.id}"', $sumber);
        $this->assertStringContainsString('await bukaPenugasan(g)', $sumber);
    }

    public function test_pemisah_nip_dan_email_kartu_modal_penugasan_titik_tengah(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertTrue(mb_check_encoding($sumber, 'UTF-8'));
        $this->assertStringContainsString('${esc(g.nip || \'-\')} · ${esc(g.email || \'-\')}', $sumber);
        $this->assertStringNotContainsString('${esc(g.nip || \'-\')} ? ${esc(g.email || \'-\')}', $sumber);
    }

    public function test_layout_modal_penugasan_memiliki_area_scroll_khusus(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertMatchesRegularExpression('/id="penugasan-overview-modal"[\s\S]{0,500}overflow-hidden/', $sumber);
        $this->assertStringContainsString('class="gm-panel relative z-10 flex max-h-[92dvh] w-full max-w-4xl flex-col overflow-hidden', $sumber);
        $this->assertStringContainsString('class="shrink-0 border-b border-slate-200 bg-[#FAFAFA] px-6 pb-3 pt-1 sm:px-8"', $sumber);
        $this->assertStringContainsString('data-penugasan-overview-scroll', $sumber);
        $this->assertStringContainsString('class="penugasan-overview-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 pb-5 pt-4 sm:px-8"', $sumber);
        $this->assertStringContainsString('class="mx-6 flex shrink-0 justify-end border-t border-slate-300 bg-[#FAFAFA] py-4 sm:mx-8"', $sumber);
        $this->assertStringNotContainsString('sticky top-0 z-10 -mx-6', $sumber);
        $this->assertStringContainsString('const resetPenugasanOverviewScroll = () => {', $sumber);
        $this->assertStringContainsString('penugasanOverviewScroll?.addEventListener(\'scroll\'', $sumber);
    }

    public function test_tombol_detail_guru_berada_di_awal_kolom_aksi(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        preg_match('/const actions = \(g\) => `([\s\S]*?)`;/', $sumber, $cocok);
        $this->assertNotEmpty($cocok[1], 'Template tombol aksi tidak ditemukan.');

        $aksi = $cocok[1];
        $sebelumnya = -1;
        foreach (['data-action="detail-kelas"', 'data-action="penugasan"', 'data-action="edit"', 'data-action="delete"'] as $penanda) {
            $posisi = strpos($aksi, $penanda);
            $this->assertNotFalse($posisi, "Tombol {$penanda} tidak ditemukan.");
            $this->assertGreaterThan($sebelumnya, $posisi, "Urutan tombol {$penanda} salah.");
            $sebelumnya = $posisi;
        }

        $this->assertStringContainsString('${ICON_EYE}', $aksi);
        $this->assertStringContainsString('title="Lihat Detail Guru"', $aksi);
    }

    public function test_kolom_kelas_diampu_tidak_lagi_memiliki_tombol_lihat_detail(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringNotContainsString('title="Lihat Detail Kelas yang Diampu"', $sumber);
        $this->assertStringNotContainsString('>Lihat Detail</button>', $sumber);
        $this->assertStringContainsString('${ICON_LAYERS}<span class="font-semibold">${daftar.length}</span> kelas', $sumber);
        $this->assertStringContainsString('+${sisa} lainnya', $sumber);
    }

    public function test_tooltip_tombol_aksi_menggunakan_title_native(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        preg_match('/const actions = \(g\) => `([\s\S]*?)`;/', $sumber, $cocok);
        $this->assertNotEmpty($cocok[1], 'Template tombol aksi tidak ditemukan.');

        $aksi = $cocok[1];
        $this->assertStringContainsString('title="Lihat Detail Guru"', $aksi);
        $this->assertStringContainsString('title="Penugasan Mengajar"', $aksi);
        $this->assertStringContainsString('title="Ubah ${esc(g.nama)}"', $aksi);
        $this->assertStringContainsString('title="Hapus ${esc(g.nama)}"', $aksi);
        $this->assertStringNotContainsString('data-guru-tooltip', $sumber);
        $this->assertStringNotContainsString('guruTooltipTarget', $sumber);
    }

    public function test_halaman_data_guru_menyediakan_label_detail_guru(): void
    {
        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.data-guru.index'))
            ->assertOk()
            ->assertSee('Lihat Detail Guru')
            ->assertDontSee('>Lihat Detail</button>', false);
    }

    public function test_mini_badge_jumlah_kelas_menggunakan_lucide_layers3(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString("const ICON_LAYERS = '<svg", $sumber);
        $this->assertStringContainsString('class="h-3.5 w-3.5 shrink-0 text-primary-60"', $sumber);
        $this->assertStringContainsString('m12.83 2.18a2 2 0 0 0-1.66 0', $sumber);
        $this->assertStringContainsString('class="inline-flex items-center gap-1 rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-primary-70">${ICON_LAYERS}<span class="font-semibold">${daftar.length}</span> kelas</span>', $sumber);
    }

    public function test_badge_lainnya_membuka_popup_kelas_dengan_data_dinamis(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString('<button type="button" data-kelas-popover="${g.id}"', $sumber);
        $this->assertStringContainsString('aria-haspopup="dialog" aria-expanded="false"', $sumber);
        $this->assertStringContainsString('const bukaPopoverRingkas = (button) => {', $sumber);
        $this->assertStringContainsString('penugasan-kelas-popover fixed z-[90] w-72', $sumber);
        $this->assertStringContainsString('SEMUA KELAS YANG DIAJAR', $sumber);
        $this->assertStringContainsString('rounded-full bg-blue-50 px-1.5 text-[11px] font-semibold text-primary-60">${daftar.length}', $sumber);
        $this->assertStringContainsString('class="space-y-1.5 p-3 ${kelasScrollClass(daftar.length)}"', $sumber);
        $this->assertStringContainsString('const label = jurusan ? `${nama} · ${jurusan}` : nama;', $sumber);
        $this->assertStringContainsString('document.body.appendChild(pop)', $sumber);
        $this->assertStringContainsString('if (kiri + lebar > window.innerWidth - 8) kiri = window.innerWidth - lebar - 8;', $sumber);
    }

    public function test_popup_kelas_dapat_ditutup_dan_tidak_menghalangi_scroll(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString('const tutupPopoverRingkas = () => {', $sumber);
        $this->assertStringContainsString("element.dataset.visible = 'false';", $sumber);
        $this->assertStringContainsString('setTimeout(() => element.remove(), 180)', $sumber);
        $this->assertStringContainsString("document.addEventListener('pointerdown'", $sumber);
        $this->assertStringContainsString('popoverRingkas.button.contains(e.target)', $sumber);
        $this->assertStringContainsString("window.addEventListener('resize'", $sumber);
        $this->assertStringContainsString("window.addEventListener('scroll'", $sumber);
        $this->assertStringContainsString('if (tutupPopoverRingkas()) return;', $sumber);
        $this->assertStringContainsString('tutupPopoverRingkas();', $sumber);
    }

    public function test_scroll_popup_kelas_hanya_aktif_untuk_lebih_dari_sepuluh_kelas(): void
    {
        $sumber = (string) file_get_contents(resource_path('views/dashboard/admin/data-guru/index.blade.php'));

        $this->assertStringContainsString('const kelasScrollClass = (jumlah) => jumlah > 10', $sumber);
        $this->assertStringContainsString("? 'kelas-popover-scroll max-h-64 overflow-y-auto overscroll-contain'", $sumber);
        $this->assertSame(2, substr_count($sumber, '${kelasScrollClass(daftar.length)}'));
        $this->assertStringNotContainsString('kelas-popover-scroll max-h-64 space-y-1.5', $sumber);
    }
}
