<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\KelasRombel as KelasModel;
use App\Models\SesiJamBelajar;
use App\Models\User;
use App\Support\Periode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

class JadwalManajemenService
{
    public function __construct(
        private readonly JadwalGuruService $jadwal,
        private readonly GuruAkademik $akademik,
        private readonly JamBelajar $jamBelajar,
    ) {}

    /**
     * @param  array{tahun_ajaran: string, semester: string}  $periode
     */
    public function kunciHari(array $periode, string $hari): void
    {
        JadwalPelajaran::query()
            ->where('tahun_ajaran', $periode['tahun_ajaran'])
            ->where('semester', $periode['semester'])
            ->where('hari', $hari)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * @param  array{tahun_ajaran: string, semester: string}|null  $periode
     */
    public function validator(Request $request, User $guru, ?JadwalPelajaran $jadwal = null, ?array $periode = null): Validator
    {
        $konfigurasi = $this->jamBelajar->konfigurasi();
        $hariAktif = $this->jamBelajar->hariAktif($konfigurasi);
        $periode ??= Periode::periodeSekarang();

        $validator = ValidatorFacade::make($request->all(), [
            'guru_id' => ['required', 'integer', 'exists:users,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'rombel' => ['required', 'string', 'max:80'],
            'hari' => ['required', 'string', 'in:'.implode(',', $hariAktif)],
            'sesi_id' => ['required', 'integer', 'exists:sesi_jam_belajar,id'],
            'ruang' => ['nullable', 'string', 'max:40', 'regex:/^[\pL\pN\s\-\.\/]+$/u'],
        ], [
            'guru_id.required' => 'Guru wajib dipilih.',
            'guru_id.exists' => 'Guru tidak ditemukan.',
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'rombel.required' => 'Rombel wajib dipilih.',
            'hari.required' => 'Hari wajib dipilih.',
            'hari.in' => 'Pilih hari yang aktif pada pengaturan jam belajar.',
            'sesi_id.required' => 'Sesi jam pelajaran wajib dipilih.',
            'sesi_id.exists' => 'Sesi jam pelajaran tidak ditemukan.',
            'ruang.max' => 'Ruang maksimal 40 karakter.',
            'ruang.regex' => 'Ruang hanya boleh berisi huruf, angka, spasi, tanda hubung, titik, dan garis miring.',
        ]);

        $validator->after(function (Validator $validator) use ($request, $guru, $jadwal, $konfigurasi, $periode): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $request->input('guru_id') !== (int) $guru->id) {
                $validator->errors()->add('guru_id', 'Guru jadwal tidak sesuai.');

                return;
            }

            if ($guru->status !== 'Aktif') {
                $validator->errors()->add('guru_id', 'Guru tidak aktif. Aktifkan akun guru sebelum membuat atau mengubah jadwal.');

                return;
            }

            [$kelas, $jurusan] = $this->jadwal->rombel((string) $request->input('rombel'));
            $mapelId = (int) $request->input('mata_pelajaran_id');

            $rombelAda = KelasModel::query()
                ->where('kelas', $kelas)
                ->when($jurusan !== '', fn (Builder $query) => $query->where('jurusan', $jurusan))
                ->exists();

            if (! $rombelAda) {
                $validator->errors()->add('rombel', 'Rombel tidak ditemukan.');

                return;
            }

            if (! $this->akademik->bolehMengajar($guru, $mapelId, $kelas, $jurusan)) {
                $validator->errors()->add('mata_pelajaran_id', 'Mata pelajaran ini tidak ditugaskan ke guru pada rombel tersebut.');

                return;
            }

            $sesiId = (int) $request->input('sesi_id');
            $sesi = SesiJamBelajar::query()->find($sesiId);

            if ($sesi === null
                || $sesi->jenis !== SesiJamBelajar::JENIS_PELAJARAN
                || (int) $sesi->jam_belajar_konfigurasi_id !== (int) $konfigurasi->id) {
                $validator->errors()->add('sesi_id', 'Pilih sesi jam pelajaran yang tersedia.');

                return;
            }

            $jamMulai = substr((string) $sesi->jam_mulai, 0, 5);
            $jamSelesai = substr((string) $sesi->jam_selesai, 0, 5);

            $duplikat = JadwalPelajaran::query()
                ->where('tahun_ajaran', $periode['tahun_ajaran'])
                ->where('semester', $periode['semester'])
                ->where('guru_id', $guru->id)
                ->where('mata_pelajaran_id', $mapelId)
                ->where('kelas', $kelas)
                ->where('jurusan', $jurusan)
                ->where('hari', $request->input('hari'))
                ->where('jam_mulai', $jamMulai)
                ->where('jam_selesai', $jamSelesai)
                ->when($jadwal !== null, fn (Builder $query) => $query->whereKeyNot($jadwal->id))
                ->exists();

            if ($duplikat) {
                $validator->errors()->add('hari', 'Jadwal yang sama sudah tersimpan.');

                return;
            }

            $cek = $this->jadwal->cekBentrok([
                'periode' => $periode,
                'hari' => $request->input('hari'),
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'rombel' => $request->input('rombel'),
                'ruang' => $request->input('ruang'),
                'sesi_label' => 'Sesi '.$this->jamBelajar->nomorPelajaran($sesi),
            ], $guru, $jadwal?->id);

            if (! $cek['aman']) {
                $validator->errors()->add('hari', $cek['pesan']);
            }
        });

        return $validator;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{tahun_ajaran: string, semester: string}|null  $periode
     */
    public function dataJadwal(array $data, User $guru, ?array $periode = null): array
    {
        $periode ??= Periode::periodeSekarang();
        [$kelas, $jurusan] = $this->jadwal->rombel((string) ($data['rombel'] ?? ''));
        $sesiId = (int) ($data['sesi_id'] ?? 0);
        $sesi = SesiJamBelajar::query()->findOrFail($sesiId);

        return [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => (int) ($data['mata_pelajaran_id'] ?? 0),
            'kelas' => $kelas,
            'jurusan' => $jurusan,
            'hari' => (string) ($data['hari'] ?? ''),
            'sesi_id' => $sesiId,
            'jam_mulai' => substr((string) $sesi->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $sesi->jam_selesai, 0, 5),
            'ruang' => trim((string) ($data['ruang'] ?? '')),
            'tahun_ajaran' => $periode['tahun_ajaran'],
            'semester' => $periode['semester'],
        ];
    }

    /**
     * @param  array{tahun_ajaran: string, semester: string}|null  $periode
     */
    public function simpan(Request $request, User $guru, User $actor, ?array $periode = null): JadwalPelajaran
    {
        $data = $this->dataJadwal($request->all(), $guru, $periode);

        return DB::transaction(function () use ($data, $actor): JadwalPelajaran {
            $jadwal = JadwalPelajaran::query()->create($data);
            $jadwal->load(['guru', 'mataPelajaran']);

            $this->jadwal->catatPerubahanCrud(
                $actor,
                'tambah',
                [],
                $this->snapshot($jadwal),
                $jadwal,
            );

            return $jadwal;
        });
    }

    /**
     * @param  array{tahun_ajaran: string, semester: string}|null  $periode
     */
    public function ubah(Request $request, User $guru, JadwalPelajaran $jadwal, User $actor, ?array $periode = null): JadwalPelajaran
    {
        $sebelum = $this->snapshot($jadwal->loadMissing(['guru', 'mataPelajaran']));
        $data = $this->dataJadwal($request->all(), $guru, $periode);

        return DB::transaction(function () use ($jadwal, $data, $sebelum, $actor): JadwalPelajaran {
            $jadwal->update($data);
            $jadwal->load(['guru', 'mataPelajaran']);

            $this->jadwal->catatPerubahanCrud(
                $actor,
                'ubah',
                $sebelum,
                $this->snapshot($jadwal),
                $jadwal,
            );

            return $jadwal;
        });
    }

    public function hapus(JadwalPelajaran $jadwal, User $actor): void
    {
        $sebelum = $this->snapshot($jadwal->loadMissing(['guru', 'mataPelajaran']));

        DB::transaction(function () use ($jadwal, $sebelum, $actor): void {
            $this->jadwal->catatPerubahanCrud(
                $actor,
                'hapus',
                $sebelum,
                [],
                $jadwal,
            );

            $jadwal->delete();
        });
    }

    /** @return array<string, string> */
    public function snapshot(JadwalPelajaran $jadwal): array
    {
        return [
            'guru' => (string) ($jadwal->guru?->name ?? '-'),
            'mapel' => (string) ($jadwal->mataPelajaran?->nama ?? '-'),
            'rombel' => trim((string) $jadwal->kelas.($jadwal->jurusan ? ' '.$jadwal->jurusan : '')),
            'hari' => (string) $jadwal->hari,
            'jam' => substr((string) $jadwal->jam_mulai, 0, 5).'–'.substr((string) $jadwal->jam_selesai, 0, 5),
            'ruang' => trim((string) $jadwal->ruang) !== '' ? (string) $jadwal->ruang : '-',
        ];
    }
}
