<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\Nilai;
use App\Models\PasswordResetRequest;
use App\Models\Pengumuman;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GlobalSearchService
{
    /**
     * Menu bawaan untuk saran awal (ketika input pencarian masih kosong / baru fokus).
     */
    public function initial(User $user): array
    {
        $role = $user->role;
        $menus = [
            'admin' => [
                ['label' => 'Dashboard Admin', 'url' => route('dashboard.admin.index'), 'group' => 'Menu', 'icon' => 'layout-dashboard'],
                ['label' => 'Data Guru', 'url' => route('dashboard.admin.data-guru.index'), 'group' => 'Menu', 'icon' => 'users'],
                ['label' => 'Data Siswa', 'url' => route('dashboard.admin.data-siswa.index'), 'group' => 'Menu', 'icon' => 'graduation-cap'],
                ['label' => 'Data Kelas', 'url' => route('dashboard.admin.data-kelas.index'), 'group' => 'Menu', 'icon' => 'school'],
                ['label' => 'Data Jurusan', 'url' => route('dashboard.admin.data-jurusan.index'), 'group' => 'Menu', 'icon' => 'layers'],
                ['label' => 'Data Mata Pelajaran', 'url' => route('dashboard.admin.data-mata-pelajaran.index'), 'group' => 'Menu', 'icon' => 'book-open'],
                ['label' => 'Manajemen Akun', 'url' => route('dashboard.admin.manajemen-akun.index'), 'group' => 'Menu', 'icon' => 'key-round'],
                ['label' => 'Pengajuan Reset Password', 'url' => route('dashboard.admin.pengajuan-reset-password.index'), 'group' => 'Menu', 'icon' => 'inbox'],
                ['label' => 'Laporan Akademik', 'url' => route('dashboard.admin.laporan-akademik.index'), 'group' => 'Menu', 'icon' => 'bar-chart-3'],
                ['label' => 'Backup Database', 'url' => route('dashboard.admin.backup-database.index'), 'group' => 'Menu', 'icon' => 'database-backup'],
                ['label' => 'Profil Admin', 'url' => route('dashboard.admin.profile.index'), 'group' => 'Menu', 'icon' => 'user-round'],
            ],
            'guru' => [
                ['label' => 'Dashboard Guru', 'url' => route('dashboard.guru.index'), 'group' => 'Menu', 'icon' => 'layout-dashboard'],
                ['label' => 'Data Siswa', 'url' => route('dashboard.guru.data-siswa.index'), 'group' => 'Menu', 'icon' => 'users'],
                ['label' => 'Mata Pelajaran', 'url' => route('dashboard.guru.mata-pelajaran.index'), 'group' => 'Menu', 'icon' => 'book-open'],
                ['label' => 'Input Nilai', 'url' => route('dashboard.guru.input-nilai.index'), 'group' => 'Menu', 'icon' => 'square-pen'],
                ['label' => 'Rekap Nilai', 'url' => route('dashboard.guru.rekap-nilai.index'), 'group' => 'Menu', 'icon' => 'file-text'],
                ['label' => 'Laporan', 'url' => route('dashboard.guru.laporan.index'), 'group' => 'Menu', 'icon' => 'bar-chart-3'],
                ['label' => 'Profil Guru', 'url' => route('dashboard.guru.profile.index'), 'group' => 'Menu', 'icon' => 'user-round'],
            ],
            'siswa' => [
                ['label' => 'Dashboard Siswa', 'url' => route('dashboard.siswa.index'), 'group' => 'Menu', 'icon' => 'layout-dashboard'],
                ['label' => 'Jadwal Pelajaran', 'url' => route('dashboard.siswa.jadwal-pelajaran.index'), 'group' => 'Menu', 'icon' => 'calendar-days'],
                ['label' => 'Tugas & Materi', 'url' => route('dashboard.siswa.tugas-materi.index'), 'group' => 'Menu', 'icon' => 'book-marked'],
                ['label' => 'Riwayat Akademik', 'url' => route('dashboard.siswa.riwayat-akademik.index'), 'group' => 'Menu', 'icon' => 'history'],
                ['label' => 'Riwayat Aktivitas', 'url' => route('dashboard.siswa.riwayat-aktivitas.index'), 'group' => 'Menu', 'icon' => 'activity'],
                ['label' => 'Rapor', 'url' => route('dashboard.siswa.rapor.index'), 'group' => 'Menu', 'icon' => 'award'],
                ['label' => 'Profil Siswa', 'url' => route('dashboard.siswa.profile.index'), 'group' => 'Menu', 'icon' => 'user-round'],
            ],
        ];

        return $menus[$role] ?? [];
    }

    /**
     * Cari menu dan data dinamis sesuai hak akses role masing-masing.
     */
    public function search(User $user, string $q, int $limit = 10): array
    {
        $rawQ = trim($q);
        $q = mb_strtolower($rawQ);
        if ($q === '') {
            return [];
        }

        $role = $user->role;
        $results = [];

        // 1. Menu Navigasi
        $menus = $this->initial($user);
        foreach ($menus as $m) {
            if (Str::contains(mb_strtolower($m['label']), $q)) {
                $results[] = [
                    'label' => $m['label'],
                    'url' => $m['url'],
                    'group' => 'Menu',
                    'icon' => $m['icon'] ?? 'layout-dashboard',
                ];
            }
        }

        // 2. Data spesifik tiap role
        if ($role === 'admin') {
            $this->searchAdmin($q, $results);
        } elseif ($role === 'guru') {
            $this->searchGuru($user, $q, $results);
        } elseif ($role === 'siswa') {
            $this->searchSiswa($user, $q, $results);
        }

        // Deduplikasi berdasarkan URL + label
        $seen = [];
        $out = [];
        foreach ($results as $r) {
            $key = ($r['url'] ?? '').'|'.($r['label'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $r;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    protected function searchAdmin(string $q, array &$results): void
    {
        // Guru
        User::query()
            ->where('role', 'guru')
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(nip) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(mata_pelajaran) LIKE ?', ["%{$q}%"]);
            })
            ->limit(6)
            ->get()
            ->each(function (User $g) use (&$results) {
                $detail = $g->nip ? " (NIP: {$g->nip})" : '';
                if ($g->mata_pelajaran) {
                    $detail .= " - {$g->mata_pelajaran}";
                }
                $results[] = [
                    'label' => 'Guru: '.$g->name.$detail,
                    'url' => route('dashboard.admin.data-guru.index').'?q='.urlencode($g->name),
                    'group' => 'Data Guru',
                    'icon' => 'users',
                ];
            });

        // Siswa
        User::query()
            ->where('role', 'siswa')
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(nis) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(kelas) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(jurusan) LIKE ?', ["%{$q}%"]);
            })
            ->limit(6)
            ->get()
            ->each(function (User $s) use (&$results) {
                $detail = $s->nis ? " ({$s->nis})" : '';
                if ($s->kelas) {
                    $detail .= " - Kelas {$s->kelas}";
                }
                $results[] = [
                    'label' => 'Siswa: '.$s->name.$detail,
                    'url' => route('dashboard.admin.data-siswa.index').'?q='.urlencode($s->name),
                    'group' => 'Data Siswa',
                    'icon' => 'graduation-cap',
                ];
            });

        // Kelas
        KelasRombel::query()
            ->with('waliKelas')
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(kelas) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(jurusan) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('waliKelas', fn (Builder $w) => $w->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"]));
            })
            ->limit(5)
            ->get()
            ->each(function (KelasRombel $k) use (&$results) {
                $wali = $k->waliKelas ? " (Wali: {$k->waliKelas->name})" : '';
                $results[] = [
                    'label' => "Kelas: {$k->kelas} {$k->jurusan}{$wali}",
                    'url' => route('dashboard.admin.data-kelas.index').'?q='.urlencode($k->kelas),
                    'group' => 'Data Kelas',
                    'icon' => 'school',
                ];
            });

        // Jurusan
        Jurusan::query()
            ->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"])
            ->limit(4)
            ->get()
            ->each(function (Jurusan $j) use (&$results) {
                $results[] = [
                    'label' => 'Jurusan: '.$j->nama,
                    'url' => route('dashboard.admin.data-jurusan.index').'?q='.urlencode($j->nama),
                    'group' => 'Data Jurusan',
                    'icon' => 'layers',
                ];
            });

        // Mata Pelajaran
        MataPelajaran::query()
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(kode) LIKE ?', ["%{$q}%"]);
            })
            ->limit(5)
            ->get()
            ->each(function (MataPelajaran $m) use (&$results) {
                $kode = $m->kode ? " ({$m->kode})" : '';
                $results[] = [
                    'label' => "Mapel: {$m->nama}{$kode}",
                    'url' => route('dashboard.admin.data-mata-pelajaran.index').'?q='.urlencode($m->nama),
                    'group' => 'Mata Pelajaran',
                    'icon' => 'book-open',
                ];
            });

        // Manajemen Akun
        User::query()
            ->whereIn('role', ['admin', 'guru', 'siswa'])
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(nip) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(nis) LIKE ?', ["%{$q}%"]);
            })
            ->limit(5)
            ->get()
            ->each(function (User $u) use (&$results) {
                $roleLabel = ucfirst($u->role);
                $results[] = [
                    'label' => "Akun: {$u->name} ({$roleLabel})",
                    'url' => route('dashboard.admin.manajemen-akun.index').'?search='.urlencode($u->name),
                    'group' => 'Manajemen Akun',
                    'icon' => 'key-round',
                ];
            });

        // Pengajuan Reset Password
        PasswordResetRequest::query()
            ->with('user')
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(identifier) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('user', fn (Builder $u) => $u->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"]));
            })
            ->limit(4)
            ->get()
            ->each(function (PasswordResetRequest $pr) use (&$results) {
                $nama = $pr->user->name ?? $pr->identifier;
                $results[] = [
                    'label' => "Pengajuan Reset: {$nama} ({$pr->statusLabel()})",
                    'url' => route('dashboard.admin.pengajuan-reset-password.index').'?status='.urlencode($pr->status).'&search='.urlencode($nama),
                    'group' => 'Reset Password',
                    'icon' => 'inbox',
                ];
            });
    }

    protected function searchGuru(User $user, string $q, array &$results): void
    {
        // Siswa
        User::query()
            ->where('role', 'siswa')
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(nis) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(email) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(kelas) LIKE ?', ["%{$q}%"]);
            })
            ->limit(6)
            ->get()
            ->each(function (User $s) use (&$results) {
                $detail = $s->nis ? " ({$s->nis})" : '';
                if ($s->kelas) {
                    $detail .= " - Kelas {$s->kelas}";
                }
                $results[] = [
                    'label' => 'Siswa: '.$s->name.$detail,
                    'url' => route('dashboard.guru.data-siswa.index').'?q='.urlencode($s->name),
                    'group' => 'Data Siswa',
                    'icon' => 'users',
                ];
            });

        // Mata Pelajaran (khususnya yang diampu atau terkait)
        $namaMapelGuru = trim((string) $user->mata_pelajaran);
        MataPelajaran::query()
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(kode) LIKE ?', ["%{$q}%"]);
            })
            ->limit(4)
            ->get()
            ->each(function (MataPelajaran $m) use (&$results, $namaMapelGuru) {
                $diampu = $namaMapelGuru !== '' && strcasecmp($namaMapelGuru, trim($m->nama)) === 0;
                $group = $diampu ? 'Mapel Diampu' : 'Mata Pelajaran';
                $prefix = $diampu ? 'Mapel Diampu: ' : 'Mapel: ';
                $kode = $m->kode ? " ({$m->kode})" : '';
                $results[] = [
                    'label' => $prefix.$m->nama.$kode,
                    'url' => route('dashboard.guru.mata-pelajaran.index').'?q='.urlencode($m->nama),
                    'group' => $group,
                    'icon' => 'book-open',
                ];
            });

        // Tugas yang dibuat guru ini
        Tugas::query()
            ->where('guru_id', $user->id)
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(judul) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', ["%{$q}%"]);
            })
            ->with('mataPelajaran')
            ->limit(4)
            ->get()
            ->each(function (Tugas $t) use (&$results) {
                $mapel = $t->mataPelajaran ? " ({$t->mataPelajaran->nama})" : '';
                $kelas = $t->kelas ? " - {$t->kelas}" : '';
                $results[] = [
                    'label' => "Tugas: {$t->judul}{$mapel}{$kelas}",
                    'url' => route('dashboard.guru.rekap-nilai.index').'?cari='.urlencode($t->judul),
                    'group' => 'Tugas',
                    'icon' => 'file-text',
                ];
            });

        // Materi yang diunggah guru ini
        Materi::query()
            ->where('guru_id', $user->id)
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(judul) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', ["%{$q}%"]);
            })
            ->with('mataPelajaran')
            ->limit(4)
            ->get()
            ->each(function (Materi $m) use (&$results) {
                $mapel = $m->mataPelajaran ? " ({$m->mataPelajaran->nama})" : '';
                $results[] = [
                    'label' => "Materi: {$m->judul}{$mapel}",
                    'url' => route('dashboard.guru.mata-pelajaran.index').'?q='.urlencode($m->judul),
                    'group' => 'Materi',
                    'icon' => 'book-marked',
                ];
            });

        // Kelas Bimbingan (jika wali kelas)
        KelasRombel::query()
            ->where('wali_kelas_id', $user->id)
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(kelas) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(jurusan) LIKE ?', ["%{$q}%"]);
            })
            ->limit(3)
            ->get()
            ->each(function (KelasRombel $k) use (&$results) {
                $results[] = [
                    'label' => "Kelas Bimbingan: {$k->kelas} {$k->jurusan}",
                    'url' => route('dashboard.guru.data-siswa.index').'?q='.urlencode($k->kelas),
                    'group' => 'Kelas Bimbingan',
                    'icon' => 'school',
                ];
            });
    }

    protected function searchSiswa(User $user, string $q, array &$results): void
    {
        // 1. Jadwal Pelajaran
        JadwalPelajaran::query()
            ->untukRombel($user->kelas, $user->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(hari) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(ruang) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('mataPelajaran', function (Builder $mb) use ($q) {
                        $mb->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"])
                            ->orWhereRaw('LOWER(kode) LIKE ?', ["%{$q}%"]);
                    })
                    ->orWhereHas('guru', function (Builder $gb) use ($q) {
                        $gb->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"]);
                    });
            })
            ->limit(4)
            ->get()
            ->each(function (JadwalPelajaran $j) use (&$results) {
                $namaMapel = $j->mataPelajaran->nama ?? 'Pelajaran';
                $guru = $j->guru ? " - {$j->guru->name}" : '';
                $results[] = [
                    'label' => "Jadwal: {$namaMapel} ({$j->hari}, {$j->jamLabel()}){$guru}",
                    'url' => route('dashboard.siswa.jadwal-pelajaran.index').'?hari='.urlencode($j->hari),
                    'group' => 'Jadwal Pelajaran',
                    'icon' => 'calendar-days',
                ];
            });

        // 2. Tugas
        Tugas::query()
            ->untukRombel($user->kelas, $user->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(judul) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('mataPelajaran', function (Builder $mb) use ($q) {
                        $mb->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"]);
                    });
            })
            ->limit(4)
            ->get()
            ->each(function (Tugas $t) use (&$results) {
                $mapel = $t->mataPelajaran ? " ({$t->mataPelajaran->nama})" : '';
                $tenggat = $t->deadline ? ' - '.$t->labelDeadline() : '';
                $results[] = [
                    'label' => "Tugas: {$t->judul}{$mapel}{$tenggat}",
                    'url' => route('dashboard.siswa.tugas-materi.index').'?tab=tugas&q='.urlencode($t->judul),
                    'group' => 'Tugas',
                    'icon' => 'book-marked',
                ];
            });

        // 3. Materi
        Materi::query()
            ->untukRombel($user->kelas, $user->jurusan)
            ->with(['mataPelajaran', 'guru'])
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(judul) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('mataPelajaran', function (Builder $mb) use ($q) {
                        $mb->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"]);
                    });
            })
            ->limit(4)
            ->get()
            ->each(function (Materi $m) use (&$results) {
                $mapel = $m->mataPelajaran ? " ({$m->mataPelajaran->nama})" : '';
                $results[] = [
                    'label' => "Materi: {$m->judul}{$mapel}",
                    'url' => route('dashboard.siswa.tugas-materi.index').'?tab=materi&q='.urlencode($m->judul),
                    'group' => 'Materi',
                    'icon' => 'book-open',
                ];
            });

        // 4. Nilai & Rapor
        Nilai::query()
            ->with('mataPelajaran')
            ->where('user_id', $user->id)
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(semester) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tahun_ajaran) LIKE ?', ["%{$q}%"])
                    ->orWhereHas('mataPelajaran', function (Builder $mb) use ($q) {
                        $mb->whereRaw('LOWER(nama) LIKE ?', ["%{$q}%"])
                            ->orWhereRaw('LOWER(kode) LIKE ?', ["%{$q}%"]);
                    });
            })
            ->limit(4)
            ->get()
            ->each(function (Nilai $n) use (&$results) {
                $namaMapel = $n->mataPelajaran->nama ?? 'Mata Pelajaran';
                $results[] = [
                    'label' => "Nilai: {$namaMapel} (Nilai: {$n->nilai}, Sem. {$n->semester})",
                    'url' => route('dashboard.siswa.rapor.index').'?semester='.urlencode($n->semester).'&tahun_ajaran='.urlencode($n->tahun_ajaran),
                    'group' => 'Rapor & Nilai',
                    'icon' => 'award',
                ];
            });

        // 5. Pengumuman
        Pengumuman::query()
            ->untukSiswa($user)
            ->where(function (Builder $b) use ($q) {
                $b->whereRaw('LOWER(judul) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(isi) LIKE ?', ["%{$q}%"]);
            })
            ->limit(3)
            ->get()
            ->each(function (Pengumuman $p) use (&$results) {
                $results[] = [
                    'label' => 'Pengumuman: '.$p->judul,
                    'url' => route('dashboard.siswa.index'),
                    'group' => 'Pengumuman',
                    'icon' => 'bell',
                ];
            });
    }
}
