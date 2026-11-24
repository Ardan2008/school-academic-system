<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Services\JadwalGuruService;
use App\Services\JamBelajar;
use App\Services\XlsxExportService;
use App\Support\Kelas;
use App\Support\Periode;
use App\Support\StrukturKelas;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class JadwalController extends Controller
{
    public function __construct(
        private readonly GuruAkademik $akademik,
        private readonly JamBelajar $jamBelajar,
        private readonly JadwalGuruService $jadwalGuru,
    ) {}

    public function index(Request $request): View
    {
        $guru = $this->guru($request);
        $periode = Periode::periodeSekarang();
        $konfigurasiJam = $this->jamBelajar->konfigurasi();
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasiJam);

        $mapel = $this->akademik->mapelDiampu($guru)->values();
        $pasangan = $this->akademik->pasanganMapelRombel($guru);
        $rombelOptions = $pasangan
            ->map(function (array $item) use ($mapel): array {
                $namaMapel = $mapel->firstWhere('id', $item['mata_pelajaran_id'])?->nama ?? 'Mata Pelajaran';

                return [
                    'value' => $item['kelas'].'|'.$item['jurusan'],
                    'label' => $namaMapel.' — '.$item['kelas']
                        .($item['jurusan'] !== '' ? ' · '.$item['jurusan'] : ' · Semua Jurusan'),
                    'mapel_id' => (int) $item['mata_pelajaran_id'],
                ];
            })
            ->values();

        $hari = trim((string) $request->query('hari', ''));
        $hari = in_array($hari, $hariAktif, true) ? $hari : null;

        $jadwal = JadwalPelajaran::query()
            ->with(['mataPelajaran', 'sesi'])
            ->where('guru_id', $guru->id)
            ->where('tahun_ajaran', $periode['tahun_ajaran'])
            ->where('semester', $periode['semester'])
            ->when($hari !== null, fn ($query) => $query->where('hari', $hari))
            ->get()
            ->sortBy(fn (JadwalPelajaran $item) => array_search($item->hari, JadwalPelajaran::HARI, true)
                .'|'.$item->infoWaktu($konfigurasiJam)['mulai'])
            ->values();

        $perHari = collect($hariAktif)
            ->mapWithKeys(fn (string $nama) => [
                $nama => $jadwal->where('hari', $nama)
                    ->sortBy(fn (JadwalPelajaran $item) => $item->infoWaktu($konfigurasiJam)['mulai'])
                    ->values(),
            ]);

        $jadwalPerluPerhatian = $jadwal
            ->filter(fn (JadwalPelajaran $item) => $item->infoWaktu($konfigurasiJam)['catatan'] !== [])
            ->values();

        $kelas = $jadwal
            ->map(fn (JadwalPelajaran $item) => [
                'kelas' => Kelas::normalize((string) $item->kelas),
                'jurusan' => trim((string) $item->jurusan),
            ])
            ->unique(fn (array $item) => $item['jurusan'].'|'.$item['kelas'])
            ->sort(fn (array $a, array $b) => strcasecmp($a['jurusan'], $b['jurusan'])
                ?: StrukturKelas::bandingkanKelas($a['kelas'], $b['kelas']))
            ->values();

        $editId = trim((string) $request->query('edit', ''));
        $formJadwal = ctype_digit($editId)
            ? JadwalPelajaran::where('guru_id', $guru->id)->find((int) $editId)
            : null;

        $mapelById = $mapel->keyBy(fn (MataPelajaran $item) => (int) $item->id);

        if ($formJadwal !== null) {
            $mapelForm = $mapelById->get((int) $formJadwal->mata_pelajaran_id);
        } else {
            $mapelForm = $mapel->isNotEmpty() ? $mapel->first() : null;
        }

        $hariIni = JadwalPelajaran::hariIni();
        $sesiOptions = $this->jamBelajar->opsiPelajaran();
        $analysis = $this->jadwalGuru->analisis($guru, $periode, $konfigurasiJam);

        $saran = collect($analysis['saran'])
            ->when($hari !== null, fn ($kumpulan) => $kumpulan->where('hari', $hari))
            ->values()
            ->all();
        $bentrokGroups = collect($analysis['bentrok'])
            ->when($hari !== null, fn ($kumpulan) => $kumpulan->where('hari', $hari))
            ->values()
            ->all();
        $catatanPerhatian = collect($analysis['items'])
            ->when($hari !== null, fn ($kumpulan) => $kumpulan->where('hari', $hari))
            ->flatMap(fn (array $item) => array_merge($item['catatan'], $item['konflik']))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $sekarang = Waktu::zona(now())->locale('id');
        $senin = $sekarang->copy()->startOfWeek(Carbon::MONDAY);
        $tanggalPekan = collect($hariAktif)
            ->mapWithKeys(function (string $nama) use ($senin): array {
                $posisi = (int) array_search($nama, JadwalPelajaran::HARI, true);

                return [$nama => $senin->copy()->addDays($posisi)->translatedFormat('d M')];
            })
            ->all();

        return view('dashboard.guru.jadwal-pelajaran.index', [
            'jadwal' => $jadwal,
            'perHari' => $perHari,
            'hariAktif' => $hariAktif,
            'konfigurasiJam' => $konfigurasiJam,
            'jadwalPerluPerhatian' => $jadwalPerluPerhatian,
            'kelas' => $kelas,
            'mapel' => $mapel,
            'mapelForm' => $mapelForm,
            'rombelOptions' => $rombelOptions,
            'sesiOptions' => $sesiOptions,
            'jamOperasional' => [
                'mulai' => $konfigurasiJam->jamMasukLabel(),
                'selesai' => $konfigurasiJam->jamSelesaiLabel(),
            ],
            'mapelIds' => $mapel->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'periode' => $periode,
            'formJadwal' => $formJadwal,
            'bukaModal' => false,
            'hari' => $hari,
            'hariIni' => $hariIni,
            'analysis' => $analysis,
            'items' => $analysis['items'],
            'saran' => $saran,
            'bentrokGroups' => $bentrokGroups,
            'catatanPerhatian' => $catatanPerhatian,
            'sesiGrid' => $this->jamBelajar->sesi()->values()->all(),
            'pekanLabel' => $this->labelPekan($senin, $hariAktif),
            'tanggalPekan' => $tanggalPekan,
            'todayIso' => $sekarang->toDateString(),
            'pekanStartIso' => $senin->toDateString(),
            'nowLabel' => $sekarang->format('H:i'),
            'bisaKelola' => false,
            'mode' => 'guru',
            'routeNames' => [
                'index' => 'dashboard.guru.jadwal-pelajaran.index',
                'export' => 'dashboard.guru.jadwal-pelajaran.export',
            ],
            'stats' => [
                [
                    'key' => 'total',
                    'label' => 'Total Jadwal',
                    'value' => $analysis['statistik']['total'],
                    'icon' => 'calendar-days',
                    'warna' => '#1A6BDB',
                    'latar' => '#E8F1FF',
                ],
                [
                    'key' => 'kelas',
                    'label' => 'Kelas Diampu',
                    'value' => $analysis['statistik']['kelas'],
                    'icon' => 'school',
                    'warna' => '#0B6554',
                    'latar' => '#DCF4EE',
                ],
                [
                    'key' => 'mapel',
                    'label' => 'Mata Pelajaran',
                    'value' => $analysis['statistik']['mapel'],
                    'icon' => 'book-open',
                    'warna' => '#1B4F9E',
                    'latar' => '#E8F1FF',
                ],
                [
                    'key' => 'hari_ini',
                    'label' => 'Jadwal Hari Ini',
                    'value' => $hariIni !== null && in_array($hariIni, $hariAktif, true)
                        ? collect($analysis['items'])->where('hari', $hariIni)->count()
                        : 0,
                    'icon' => 'clock',
                    'warna' => '#7A4300',
                    'latar' => '#FFF1D6',
                ],
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        $guru = $this->guru($request);
        $periode = Periode::periodeSekarang();
        $konfigurasiJam = $this->jamBelajar->konfigurasi();
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasiJam);

        $hari = trim((string) $request->query('hari', ''));
        $hari = in_array($hari, $hariAktif, true) ? $hari : null;

        $analysis = $this->jadwalGuru->analisis($guru, $periode, $konfigurasiJam);
        $items = collect($analysis['items'])
            ->when($hari !== null, fn ($kumpulan) => $kumpulan->where('hari', $hari))
            ->unique('id')
            ->sort(function (array $a, array $b) use ($hariAktif): int {
                $bandingHari = ((int) array_search($a['hari'], $hariAktif, true))
                    <=> ((int) array_search($b['hari'], $hariAktif, true));
                if ($bandingHari !== 0) {
                    return $bandingHari;
                }

                $bandingMulai = strcmp((string) $a['mulai'], (string) $b['mulai']);
                if ($bandingMulai !== 0) {
                    return $bandingMulai;
                }

                return (int) $a['id'] <=> (int) $b['id'];
            })
            ->values();

        $sekarang = Waktu::zona(now())->locale('id');
        $senin = $sekarang->copy()->startOfWeek(Carbon::MONDAY);
        $tanggalPekan = collect($hariAktif)
            ->mapWithKeys(function (string $nama) use ($senin): array {
                $posisi = (int) array_search($nama, JadwalPelajaran::HARI, true);

                return [$nama => $senin->copy()->addDays($posisi)->translatedFormat('d M')];
            })
            ->all();

        $baris = $items
            ->map(fn (array $item, int $indeks): array => [
                $indeks + 1,
                (string) $item['hari'],
                (string) ($tanggalPekan[$item['hari']] ?? '-'),
                (string) $item['sesi_label'],
                (string) $item['mulai'],
                (string) $item['selesai'],
                (string) $item['mapel'],
                (string) $item['kelas'],
                (string) $item['jurusan'],
                (string) $item['ruang'],
                (string) $item['status_label'],
            ])
            ->all();

        $judulSeri = [
            'No',
            'Hari',
            'Tanggal',
            'Sesi',
            'Jam Mulai',
            'Jam Selesai',
            'Mata Pelajaran',
            'Kelas',
            'Jurusan',
            'Ruang',
            'Status',
        ];

        $keterangan = 'Periode '.Periode::label($periode['tahun_ajaran'], $periode['semester'])
            .' · Diunduh '.$sekarang->format('d M Y H:i')
            .' · '.$items->count().' jadwal'
            .($hari !== null ? ' · '.$hari : '');

        $response = app(XlsxExportService::class)->download(
            'jadwal-mengajar-guru-'.now()->format('Ymd-His').'.xlsx',
            'Jadwal Mengajar',
            $judulSeri,
            $baris,
            [
                'subjudul' => $keterangan,
                'sheet' => 'Jadwal Mengajar',
                'text_columns' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            ]
        );
        $response->headers->set('Cache-Control', 'no-store, no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Total-Jadwal', (string) $items->count());

        return $response;
    }

    /**
     * @param  array<int, string>  $hariAktif
     */
    private function labelPekan(Carbon $senin, array $hariAktif): string
    {
        $posisi = array_map(
            fn (string $nama) => (int) array_search($nama, JadwalPelajaran::HARI, true),
            $hariAktif,
        );
        $awal = $senin->copy()->addDays(min($posisi));
        $akhir = $senin->copy()->addDays(max($posisi));

        if ($awal->month === $akhir->month && $awal->year === $akhir->year) {
            return $awal->day.'–'.$akhir->day.' '.$akhir->translatedFormat('F Y');
        }

        if ($awal->year === $akhir->year) {
            return $awal->translatedFormat('d M').'–'.$akhir->translatedFormat('d M Y');
        }

        return $awal->translatedFormat('d M Y').'–'.$akhir->translatedFormat('d M Y');
    }

    private function guru(Request $request): User
    {
        $guru = $request->user();

        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        return $guru;
    }
}
