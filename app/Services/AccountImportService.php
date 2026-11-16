<?php

namespace App\Services;

use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Models\User;
use App\Support\Credentials;
use App\Support\Kelas;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AccountImportService
{
    public const MAX_ROWS = 5000;

    /** @var array<string, list<string>> */
    protected const HEADER_ALIASES = [
        'nip' => ['nip', 'nomor induk pegawai', 'no nip', 'nip guru'],
        'nis' => ['nis', 'nisn', 'nomor induk siswa', 'nomor induk siswa nasional', 'no nis', 'no nisn'],
        'nama' => ['nama', 'name', 'nama lengkap', 'nama_lengkap', 'full name', 'nama pegawai', 'peserta didik', 'peserta', 'nama siswa', 'nama guru'],
        'email' => ['email', 'surel', 'alamat email', 'alamat_email', 'e-mail', 'mail'],
        'kelas' => ['kelas', 'class', 'kelas/rombel', 'rombel', 'rombongan', 'tingkat', 'nama kelas'],
        'jurusan' => ['jurusan', 'program keahlian', 'paket keahlian', 'kompetensi keahlian', 'nama jurusan'],
        'mata_pelajaran' => ['mata pelajaran', 'mapel', 'pelajaran', 'nama mata pelajaran', 'nama mapel'],
        'kode' => ['kode', 'kode mapel', 'kode mata pelajaran', 'kode pelajaran'],
        'status' => ['status', 'keaktifan', 'kondisi'],
        'wali_kelas' => ['wali kelas', 'wali_kelas', 'wali', 'nama wali kelas'],
    ];

    public function import(string $path, string $extension): array
    {
        $rows = $this->readRows($path, $extension);
        $rows = $this->trimTrailingEmptyRows($rows);

        if (count($rows) === 0) {
            throw new \RuntimeException('File kosong atau tidak dapat dibaca.');
        }

        $headers = array_map(fn ($cell) => $this->cell($cell), $rows[0] ?? []);
        $headerMap = $this->buildHeaderMap($headers);

        if ($headerMap === null) {
            throw new \RuntimeException(
                'Header file tidak dikenali. Pastikan baris pertama berisi header kolom yang sesuai (contoh: NIP/Nama/Email untuk Guru, NIS/Nama/Email/Kelas/Jurusan untuk Siswa, Kelas/Jurusan untuk Kelas, Nama Jurusan/Status untuk Jurusan, atau Kode/Nama Mata Pelajaran untuk Mata Pelajaran).'
            );
        }

        $type = $this->detectType($headerMap);
        if ($type === null) {
            throw new \RuntimeException(
                'Jenis data tidak dapat dikenali atau kolom wajib tidak lengkap/ambigu. Gunakan header yang jelas: NIP untuk Guru; NIS untuk Siswa; Kelas dan Jurusan (dengan opsi Wali Kelas) untuk Kelas; Nama/Jurusan untuk Jurusan; Kode dan Nama Mata Pelajaran untuk Mata Pelajaran.'
            );
        }

        $dataRows = array_slice($rows, 1);
        $truncated = false;
        if (count($dataRows) > self::MAX_ROWS) {
            $truncated = true;
            $dataRows = array_slice($dataRows, 0, self::MAX_ROWS);
        }

        $created = [];
        $skipped = [];
        $errors = [];
        $seen = [];

        foreach ($dataRows as $index => $raw) {
            $rowNumber = $index + 2; // baris 1 = header

            if ($this->isBlankRow($raw)) {
                continue;
            }

            try {
                // Setiap baris diproses dalam transaksi sendiri agar baris valid
                // tetap tersimpan meski baris lain gagal, dan tidak ada data parsial.
                $result = DB::transaction(fn () => match ($type) {
                    'guru' => $this->importGuruRow($raw, $headerMap, $seen),
                    'siswa' => $this->importSiswaRow($raw, $headerMap, $seen),
                    'kelas' => $this->importKelasRow($raw, $headerMap, $seen),
                    'jurusan' => $this->importJurusanRow($raw, $headerMap, $seen),
                    'mata_pelajaran' => $this->importMapelRow($raw, $headerMap, $seen),
                    default => ['status' => 'error', 'reason' => 'Jenis data tidak didukung.'],
                });
            } catch (\Throwable $e) {
                $result = ['status' => 'error', 'reason' => 'Gagal menyimpan baris: '.$e->getMessage()];
            }

            $status = $result['status'] ?? 'error';
            unset($result['status']);

            if ($status === 'created') {
                $created[] = array_merge(['row' => $rowNumber], $result);
            } elseif ($status === 'skipped') {
                $skipped[] = array_merge(['row' => $rowNumber], $result);
            } else {
                $errors[] = array_merge(['row' => $rowNumber], $result);
            }
        }

        $typeLabel = match ($type) {
            'guru' => 'Guru',
            'siswa' => 'Siswa',
            'kelas' => 'Kelas',
            'jurusan' => 'Jurusan',
            'mata_pelajaran' => 'Mata Pelajaran',
            default => $type,
        };

        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'truncated' => $truncated,
            'max_rows' => self::MAX_ROWS,
        ];
    }

    /**
     * Deteksi jenis data dari header. Prioritas identitas kuat: NIP->Guru, NIS->Siswa.
     * Setelah itu cek struktur wajib per master data.
     */
    protected function detectType(array $headerMap): ?string
    {
        $has = fn (string $key) => array_key_exists($key, $headerMap);

        if ($has('nip')) {
            return $has('nama') ? 'guru' : null;
        }

        if ($has('nis')) {
            return ($has('nama') && $has('kelas') && $has('jurusan')) ? 'siswa' : null;
        }

        // Mata Pelajaran: wajib kode + (nama mata pelajaran / mapel). "nama" generik ambigu.
        if ($has('kode') && ($has('mata_pelajaran') || $has('mapel_nama'))) {
            return 'mata_pelajaran';
        }
        if ($has('kode') && $has('nama') && ! $has('kelas') && ! $has('jurusan')) {
            // fallback: kode + nama tanpa konteks kelas/jurusan -> mapel
            return 'mata_pelajaran';
        }

        // Kelas: wajib kelas + jurusan
        if ($has('kelas') && $has('jurusan') && ! $has('nis') && ! $has('nip')) {
            return 'kelas';
        }

        // Jurusan: header spesifik jurusan/nama jurusan (dan bukan kelas/mapel)
        if (($has('jurusan_nama') || ($has('nama') && $has('status') && ! $has('kelas') && ! $has('kode')))
            || ($has('jurusan') && ! $has('kelas') && ! $has('nis') && ! $has('nip') && ! $has('kode') && ! $has('nama'))) {
            return 'jurusan';
        }

        return null;
    }

    /**
     * @param array<string, true> $seen
     * @return array{status: string, reason?: string, ...}
     */
    protected function importGuruRow(array $raw, array $headerMap, array &$seen): array
    {
        $nip = $this->cell($this->val($raw, $headerMap, 'nip'));
        $nama = $this->cell($this->val($raw, $headerMap, 'nama'));
        $email = mb_strtolower($this->cell($this->val($raw, $headerMap, 'email')));
        $mapelInput = $this->cell($this->val($raw, $headerMap, 'mata_pelajaran'));
        $status = $this->normalizeGuruStatus($this->cell($this->val($raw, $headerMap, 'status')));

        if ($nip === '' || $nama === '') {
            return ['status' => 'error', 'reason' => 'NIP dan Nama wajib diisi untuk data Guru.'];
        }
        if (! preg_match('/^[A-Za-z0-9\-\/]+$/', $nip)) {
            return ['status' => 'error', 'reason' => "Format NIP \"{$nip}\" tidak valid (hanya huruf, angka, tanda hubung, atau garis miring)."];
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'reason' => "Format email \"{$email}\" tidak valid."];
        }

        $mapelNama = null;
        if ($mapelInput !== '') {
            $mapelModel = $this->findMataPelajaran($mapelInput);
            if ($mapelModel === null) {
                return [
                    'status' => 'error',
                    'reason' => "Mata pelajaran \"{$mapelInput}\" belum terdaftar di Data Mata Pelajaran. Daftarkan mata pelajaran tersebut terlebih dahulu melalui menu Data Mata Pelajaran, lalu impor kembali.",
                ];
            }
            $mapelNama = $mapelModel->nama;
        }

        if (isset($seen['nip:'.$nip])) {
            return ['status' => 'skipped', 'reason' => "NIP \"{$nip}\" duplikat di dalam file.", 'identifier' => $nip];
        }
        $seen['nip:'.$nip] = true;

        if (User::query()->where('nip', $nip)->exists()) {
            return ['status' => 'skipped', 'reason' => "NIP \"{$nip}\" sudah terdaftar di sistem.", 'identifier' => $nip];
        }

        $finalEmail = $email !== '' ? $email : Credentials::generateEmail($nama);
        $finalEmail = $this->ensureUniqueEmail($finalEmail, $seen);
        if ($finalEmail === null) {
            $target = $email !== '' ? $email : '(otomatis)';
            return ['status' => 'skipped', 'reason' => "Email \"{$target}\" sudah dipakai akun lain atau duplikat dalam file.", 'identifier' => $nip];
        }

        $password = Credentials::generateUniquePassword();
        User::create([
            'name' => mb_substr($nama, 0, 120),
            'email' => $finalEmail,
            'password' => $password,
            'role' => 'guru',
            'nip' => mb_substr($nip, 0, 20),
            'nis' => null,
            'mata_pelajaran' => $mapelNama !== null ? mb_substr($mapelNama, 0, 120) : null,
            'status' => $status,
            'initial_password' => Crypt::encryptString($password),
            'must_change_password' => true,
        ]);

        return [
            'status' => 'created',
            'name' => $nama,
            'identifier' => $nip,
            'email' => $finalEmail,
            'password' => $password,
            'detail' => $mapelNama !== null ? "Mapel: {$mapelNama}" : 'Guru',
        ];
    }

    /**
     * Cari mata pelajaran berdasarkan nama atau kode, toleran huruf besar/kecil dan spasi berlebih.
     */
    protected function findMataPelajaran(string $input): ?MataPelajaran
    {
        $needle = $this->normalizeCompare($input);
        if ($needle === '') {
            return null;
        }

        foreach (MataPelajaran::query()->get(['id', 'kode', 'nama']) as $mapel) {
            if ($this->normalizeCompare((string) $mapel->nama) === $needle) {
                return $mapel;
            }
            if ($mapel->kode !== null && $this->normalizeCompare((string) $mapel->kode) === $needle) {
                return $mapel;
            }
        }

        return null;
    }

    protected function normalizeCompare(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }

    /**
     * @param array<string, true> $seen
     * @return array{status: string, reason?: string, ...}
     */
    protected function importSiswaRow(array $raw, array $headerMap, array &$seen): array
    {
        $nis = $this->cell($this->val($raw, $headerMap, 'nis'));
        $nama = $this->cell($this->val($raw, $headerMap, 'nama'));
        $email = mb_strtolower($this->cell($this->val($raw, $headerMap, 'email')));
        $kelasRaw = $this->cell($this->val($raw, $headerMap, 'kelas'));
        $jurusanInput = $this->cell($this->val($raw, $headerMap, 'jurusan'));
        $status = $this->normalizeSiswaStatus($this->cell($this->val($raw, $headerMap, 'status')));

        if ($nis === '' || $nama === '' || $kelasRaw === '' || $jurusanInput === '') {
            return ['status' => 'error', 'reason' => 'NIS, Nama, Kelas, dan Jurusan wajib diisi untuk data Siswa.'];
        }
        if (! preg_match('/^[A-Za-z0-9\-\/]+$/', $nis)) {
            return ['status' => 'error', 'reason' => "Format NIS \"{$nis}\" tidak valid (hanya huruf, angka, tanda hubung, atau garis miring)."];
        }
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'reason' => "Format email \"{$email}\" tidak valid."];
        }

        $kelas = Kelas::normalize($kelasRaw);
        if (! Kelas::isValid($kelas)) {
            return ['status' => 'error', 'reason' => Kelas::formatMessage()];
        }

        $jurusanModel = $this->findJurusan($jurusanInput);
        if ($jurusanModel === null) {
            return [
                'status' => 'error',
                'reason' => "Jurusan \"{$jurusanInput}\" belum terdaftar di Data Jurusan. Daftarkan jurusan tersebut terlebih dahulu melalui menu Data Jurusan, lalu impor kembali.",
            ];
        }
        $jurusan = $jurusanModel->nama;

        $kelasModel = $this->findKelasRombel($jurusan, $kelas);
        if ($kelasModel === null) {
            return [
                'status' => 'error',
                'reason' => "Kelas \"{$kelas}\" pada jurusan \"{$jurusan}\" belum terdaftar di Data Kelas. Daftarkan kelas tersebut terlebih dahulu melalui menu Data Kelas, lalu impor kembali.",
            ];
        }

        if (isset($seen['nis:'.$nis])) {
            return ['status' => 'skipped', 'reason' => "NIS \"{$nis}\" duplikat di dalam file.", 'identifier' => $nis];
        }
        $seen['nis:'.$nis] = true;

        if (User::query()->where('nis', $nis)->exists()) {
            return ['status' => 'skipped', 'reason' => "NIS \"{$nis}\" sudah terdaftar di sistem.", 'identifier' => $nis];
        }

        $finalEmail = $email !== '' ? $email : Credentials::generateEmail($nama);
        $finalEmail = $this->ensureUniqueEmail($finalEmail, $seen);
        if ($finalEmail === null) {
            $target = $email !== '' ? $email : '(otomatis)';
            return ['status' => 'skipped', 'reason' => "Email \"{$target}\" sudah dipakai akun lain atau duplikat dalam file.", 'identifier' => $nis];
        }

        $password = Credentials::generateUniquePassword();
        User::create([
            'name' => mb_substr($nama, 0, 120),
            'email' => $finalEmail,
            'password' => $password,
            'role' => 'siswa',
            'nis' => mb_substr($nis, 0, 30),
            'nip' => null,
            'kelas' => $kelas,
            'jurusan' => mb_substr($jurusan, 0, 120),
            'mata_pelajaran' => null,
            'status' => $status,
            'initial_password' => Crypt::encryptString($password),
            'must_change_password' => true,
        ]);

        return [
            'status' => 'created',
            'name' => $nama,
            'identifier' => $nis,
            'email' => $finalEmail,
            'password' => $password,
            'detail' => "{$kelas} - {$jurusan}",
        ];
    }

    protected function findJurusan(string $input): ?Jurusan
    {
        $needle = $this->normalizeCompare($input);
        if ($needle === '') {
            return null;
        }

        foreach (Jurusan::query()->get(['id', 'nama']) as $j) {
            if ($this->normalizeCompare((string) $j->nama) === $needle) {
                return $j;
            }
        }

        return null;
    }

    protected function findKelasRombel(string $jurusanNama, string $kelasNormalized): ?KelasRombel
    {
        foreach (KelasRombel::query()->where('jurusan', $jurusanNama)->get(['id', 'jurusan', 'kelas']) as $k) {
            if (Kelas::normalize((string) $k->kelas) === $kelasNormalized) {
                return $k;
            }
        }

        return null;
    }

    /**
     * @param array<string, true> $seen
     * @return array{status: string, reason?: string, ...}
     */
    protected function importKelasRow(array $raw, array $headerMap, array &$seen): array
    {
        $kelasRaw = $this->cell($this->val($raw, $headerMap, 'kelas'));
        $jurusanInput = $this->cell($this->val($raw, $headerMap, 'jurusan'));
        $waliNama = $this->cell($this->val($raw, $headerMap, 'wali_kelas'));

        if ($kelasRaw === '' || $jurusanInput === '') {
            return ['status' => 'error', 'reason' => 'Kelas dan Jurusan wajib diisi untuk data Kelas.'];
        }

        $kelas = Kelas::normalize($kelasRaw);
        if (! Kelas::isValid($kelas)) {
            return ['status' => 'error', 'reason' => Kelas::formatMessage()];
        }

        $jurusanModel = $this->findJurusan($jurusanInput);
        if ($jurusanModel === null) {
            return [
                'status' => 'error',
                'reason' => "Jurusan \"{$jurusanInput}\" belum terdaftar di Data Jurusan. Daftarkan jurusan tersebut terlebih dahulu melalui menu Data Jurusan, lalu impor kembali.",
            ];
        }
        $jurusan = $jurusanModel->nama;

        $waliId = null;
        if ($waliNama !== '') {
            $wali = $this->findGuruByName($waliNama);
            if ($wali === null) {
                return [
                    'status' => 'error',
                    'reason' => "Wali kelas \"{$waliNama}\" tidak ditemukan pada akun Guru. Pastikan guru tersebut sudah terdaftar, lalu impor kembali.",
                ];
            }
            $waliId = $wali->id;
        }

        $key = $jurusan.'|'.$kelas;
        if (isset($seen['kelas:'.$key])) {
            return ['status' => 'skipped', 'reason' => "Kelas \"{$kelas}\" pada jurusan \"{$jurusan}\" duplikat di dalam file.", 'identifier' => $key];
        }
        $seen['kelas:'.$key] = true;

        if (KelasRombel::query()->where('jurusan', $jurusan)->where('kelas', $kelas)->exists()) {
            return ['status' => 'skipped', 'reason' => "Kelas \"{$kelas}\" pada jurusan \"{$jurusan}\" sudah terdaftar.", 'identifier' => $key];
        }

        KelasRombel::create([
            'jurusan' => mb_substr($jurusan, 0, 120),
            'kelas' => $kelas,
            'wali_kelas_id' => $waliId,
        ]);

        return [
            'status' => 'created',
            'name' => $kelas,
            'identifier' => $jurusan,
            'detail' => $waliNama !== '' ? "Wali: {$waliNama}" : 'Tanpa wali kelas',
        ];
    }

    protected function findGuruByName(string $name): ?User
    {
        $needle = $this->normalizeCompare($name);
        if ($needle === '') {
            return null;
        }

        foreach (User::query()->where('role', 'guru')->get(['id', 'name']) as $guru) {
            if ($this->normalizeCompare((string) $guru->name) === $needle) {
                return $guru;
            }
        }

        return null;
    }

    /**
     * @param array<string, true> $seen
     * @return array{status: string, reason?: string, ...}
     */
    protected function importJurusanRow(array $raw, array $headerMap, array &$seen): array
    {
        $nama = $this->cell($this->val($raw, $headerMap, 'jurusan_nama'));
        if ($nama === '') {
            $nama = $this->cell($this->val($raw, $headerMap, 'jurusan'));
        }
        if ($nama === '') {
            $nama = $this->cell($this->val($raw, $headerMap, 'nama'));
        }
        $status = $this->normalizeJurusanStatus($this->cell($this->val($raw, $headerMap, 'status')));

        if ($nama === '') {
            return ['status' => 'error', 'reason' => 'Nama jurusan wajib diisi.'];
        }

        $key = $this->normalizeCompare($nama);
        if (isset($seen['jurusan:'.$key])) {
            return ['status' => 'skipped', 'reason' => "Jurusan \"{$nama}\" duplikat di dalam file.", 'identifier' => $nama];
        }
        $seen['jurusan:'.$key] = true;

        if ($this->findJurusan($nama) !== null) {
            return ['status' => 'skipped', 'reason' => "Jurusan \"{$nama}\" sudah terdaftar di sistem.", 'identifier' => $nama];
        }

        Jurusan::create([
            'nama' => mb_substr($nama, 0, 120),
            'status' => $status,
        ]);

        return [
            'status' => 'created',
            'name' => $nama,
            'identifier' => $nama,
            'detail' => $status === 'aktif' ? 'Aktif' : 'Nonaktif',
        ];
    }

    /**
     * @param array<string, true> $seen
     * @return array{status: string, reason?: string, ...}
     */
    protected function importMapelRow(array $raw, array $headerMap, array &$seen): array
    {
        $kode = mb_strtoupper($this->cell($this->val($raw, $headerMap, 'kode')));
        $nama = $this->cell($this->val($raw, $headerMap, 'mata_pelajaran'));
        if ($nama === '') {
            $nama = $this->cell($this->val($raw, $headerMap, 'nama'));
        }

        if ($kode === '' || $nama === '') {
            return ['status' => 'error', 'reason' => 'Kode dan Nama Mata Pelajaran wajib diisi.'];
        }
        if (! preg_match('/^[A-Za-z0-9\-\.\/]+$/', $kode)) {
            return ['status' => 'error', 'reason' => "Format kode mata pelajaran \"{$kode}\" tidak valid."];
        }

        $kodeKey = $this->normalizeCompare($kode);
        $namaKey = $this->normalizeCompare($nama);

        if (isset($seen['mapel_kode:'.$kodeKey]) || isset($seen['mapel_nama:'.$namaKey])) {
            return ['status' => 'skipped', 'reason' => "Mata pelajaran \"{$nama}\" ({$kode}) duplikat di dalam file.", 'identifier' => $kode];
        }
        $seen['mapel_kode:'.$kodeKey] = true;
        $seen['mapel_nama:'.$namaKey] = true;

        $existingByName = $this->findMataPelajaran($nama);
        $existingByCode = $this->findMataPelajaran($kode);
        if ($existingByCode !== null) {
            return ['status' => 'skipped', 'reason' => "Kode mata pelajaran \"{$kode}\" sudah terdaftar di sistem.", 'identifier' => $kode];
        }
        if ($existingByName !== null) {
            return ['status' => 'skipped', 'reason' => "Nama mata pelajaran \"{$nama}\" sudah terdaftar di sistem.", 'identifier' => $nama];
        }

        MataPelajaran::create([
            'kode' => mb_substr($kode, 0, 20),
            'nama' => mb_substr($nama, 0, 120),
        ]);

        return [
            'status' => 'created',
            'name' => $nama,
            'identifier' => $kode,
            'detail' => $kode,
        ];
    }

    /**
     * Pastikan email unik di DB dan di batch. $seen diisi untuk email baru.
     * @param array<string, true> $seen
     */
    protected function ensureUniqueEmail(string $email, array &$seen): ?string
    {
        $key = mb_strtolower($email);
        if (isset($seen['email:'.$key]) || User::query()->where('email', $key)->exists()) {
            return null;
        }
        $seen['email:'.$key] = true;

        return $key;
    }

    protected function normalizeGuruStatus(string $status): string
    {
        $s = mb_strtolower(trim($status));
        if ($s === '' || $s === 'aktif' || $s === 'active') {
            return 'Aktif';
        }
        if (in_array($s, ['nonaktif', 'non aktif', 'tidak aktif', 'inactive'], true)) {
            return 'Nonaktif';
        }
        if ($s === 'cuti') {
            return 'Cuti';
        }

        return 'Aktif';
    }

    protected function normalizeSiswaStatus(string $status): string
    {
        $s = mb_strtolower(trim($status));
        if ($s === '' || $s === 'aktif' || $s === 'active') {
            return User::STATUS_AKTIF;
        }
        if (in_array($s, ['nonaktif', 'non aktif', 'tidak aktif', 'inactive'], true)) {
            return User::STATUS_NONAKTIF;
        }
        if (in_array($s, ['lulus', 'alumni', 'graduate', 'graduated'], true)) {
            return User::STATUS_LULUS;
        }

        return User::STATUS_AKTIF;
    }

    protected function normalizeJurusanStatus(string $status): string
    {
        $s = mb_strtolower(trim($status));
        if ($s === '' || $s === 'aktif' || $s === 'active') {
            return 'aktif';
        }
        if (in_array($s, ['nonaktif', 'non aktif', 'tidak aktif', 'inactive'], true)) {
            return 'nonaktif';
        }

        return 'aktif';
    }

    /**
     * @return array<string, int>|null
     */
    protected function buildHeaderMap(array $header): ?array
    {
        $normalized = array_map(fn ($cell) => $this->cleanToken($this->cell($cell)), $header);
        $map = [];

        foreach (self::HEADER_ALIASES as $key => $aliases) {
            $cleanedAliases = array_map(fn ($alias) => $this->cleanToken($alias), $aliases);

            foreach ($normalized as $index => $cell) {
                if ($cell !== '' && in_array($cell, $cleanedAliases, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }

        // Penanda spesifik untuk disambiguasi
        foreach ($normalized as $index => $cell) {
            if (in_array($cell, ['nama jurusan', 'jurusan nama'], true)) {
                $map['jurusan_nama'] = $index;
            }
            if (in_array($cell, ['nama mata pelajaran', 'nama mapel'], true)) {
                $map['mapel_nama'] = $index;
            }
        }

        if (count($map) === 0) {
            return null;
        }

        // Minimal harus ada salah satu kunci identitas/struktur yang kita kenal
        $known = ['nip', 'nis', 'kelas', 'jurusan', 'jurusan_nama', 'kode', 'mata_pelajaran', 'mapel_nama', 'nama'];
        foreach ($known as $k) {
            if (isset($map[$k])) {
                return $map;
            }
        }

        return null;
    }

    protected function val(array $raw, array $headerMap, string $field): string
    {
        if (! isset($headerMap[$field])) {
            return '';
        }

        $v = $raw[$headerMap[$field]] ?? '';

        return $this->cell($v);
    }

    protected function readRows(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->readCsv($path);
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->readExcel($path);
        }

        return [];
    }

    protected function readCsv(string $path): array
    {
        $rows = [];

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return $rows;
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);
        $delimiter = $delimiter === '' ? ',' : $delimiter;

        while (($data = fgetcsv($handle, length: 0, separator: $delimiter, escape: '')) !== false) {
            $rows[] = $data;
        }

        fclose($handle);

        return $rows;
    }

    protected function readExcel(string $path): array
    {
        $rows = [];

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getActiveSheet();

            foreach ($sheet->toArray() as $row) {
                $rows[] = is_array($row) ? $row : [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $rows;
    }

    protected function detectDelimiter(?string $line): string
    {
        if ($line === null) {
            return ',';
        }

        $line = $this->stripBom($line);
        $counts = [
            ';' => substr_count($line, ';'),
            ',' => substr_count($line, ','),
            "\t" => substr_count($line, "\t"),
        ];

        arsort($counts);

        return (string) array_key_first($counts);
    }

    protected function trimTrailingEmptyRows(array $rows): array
    {
        while (count($rows) > 0 && $this->isBlankRow($rows[count($rows) - 1])) {
            array_pop($rows);
        }

        return $rows;
    }

    protected function isBlankRow(mixed $row): bool
    {
        if (! is_array($row)) {
            return trim((string) $row) === '';
        }

        foreach ($row as $cell) {
            if ($this->cell($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $this->stripBom(trim($value));
    }

    protected function cleanToken(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function stripBom(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = str_replace("\u{FEFF}", '', $value);

        return $value;
    }
}
