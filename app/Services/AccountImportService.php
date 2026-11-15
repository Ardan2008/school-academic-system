<?php

namespace App\Services;

use App\Models\User;
use App\Support\Credentials;
use App\Support\Kelas;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AccountImportService
{
    public const MAX_ROWS = 5000;

    protected const HEADER_ALIASES = [
        'role' => ['role', 'peran', 'tipe akun', 'jenis akun', 'tipe_akun', 'jenis_akun'],
        'nama' => ['nama', 'name', 'nama lengkap', 'nama_lengkap', 'full name', 'nama pegawai', 'peserta didik', 'peserta'],
        'email' => ['email', 'surel', 'alamat email', 'alamat_email', 'e-mail', 'mail'],
        'kelas' => ['kelas', 'class', 'kelas/rombel', 'rombel', 'rombongan', 'tingkat'],
    ];

    public function import(string $path, string $extension): array
    {
        $rows = $this->readRows($path, $extension);

        $headerMap = $this->buildHeaderMap($rows[0] ?? []);
        $hasHeader = $headerMap !== null;

        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;

        $truncated = false;
        if (count($dataRows) > self::MAX_ROWS) {
            $truncated = true;
            $dataRows = array_slice($dataRows, 0, self::MAX_ROWS);
        }

        $created = [];
        $skipped = [];
        $errors = [];
        $rowOffset = $hasHeader ? 1 : 0;

        DB::transaction(function () use ($dataRows, $headerMap, $rowOffset, &$created, &$skipped, &$errors) {
            foreach ($dataRows as $index => $raw) {
                $rowNumber = $rowOffset + $index + 1;
                $values = $this->rowValues($raw, $headerMap);

                $name = $this->cell($values['nama'] ?? '');
                if ($name === '') {
                    $errors[] = ['row' => $rowNumber, 'reason' => 'Nama wajib diisi.'];

                    continue;
                }

                $role = $this->normalizeRole($values['role'] ?? '');
                if ($role === null) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'reason' => sprintf('Peran "%s" tidak dikenali (gunakan "Guru" atau "Siswa").', $this->cell($values['role'] ?? '')),
                    ];

                    continue;
                }

                $email = strtolower($this->cell($values['email'] ?? ''));
                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'reason' => 'Format email tidak valid.',
                    ];

                    continue;
                }

                $kelas = Kelas::normalize($this->cell($values['kelas'] ?? ''));

                if ($role === 'siswa' && $kelas !== '' && ! Kelas::isValid($kelas)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'reason' => Kelas::formatMessage(),
                    ];

                    continue;
                }

                if ($email === '') {
                    $email = Credentials::generateEmail($name);
                }

                if (User::where('email', $email)->exists()) {
                    $skipped[] = ['row' => $rowNumber, 'email' => $email, 'reason' => 'Email sudah dipakai.'];

                    continue;
                }

                // Password awal tiap akun harus unik, termasuk dibandingkan
                // akun-akun lain dalam file impor yang sama.
                $password = Credentials::generateUniquePassword();

                User::create([
                    'name' => mb_substr($name, 0, 150),
                    'email' => $email,
                    'password' => $password,
                    'role' => $role,
                    'kelas' => $kelas !== '' ? $kelas : null,
                    'initial_password' => Crypt::encryptString($password),
                    'must_change_password' => true,
                ]);

                $created[] = [
                    'row' => $rowNumber,
                    'role' => $role,
                    'name' => $name,
                    'email' => $email,
                    'kelas' => $kelas,
                    'password' => $password,
                ];
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'truncated' => $truncated,
            'has_header' => $hasHeader,
            'max_rows' => self::MAX_ROWS,
        ];
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

        // Argumen named dipakai karena urutan parameter fgetcsv berubah di PHP 8.4
        // ($enclosure pindah ke posisi #4). $escape dikosongkan agar nama yang
        // mengandung backslash tidak ikut berubah dan perilaku defaultnya stabil.
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
                $rows[] = $row;
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

    protected function buildHeaderMap(array $header): ?array
    {
        $normalized = array_map(fn ($cell) => $this->cleanToken($this->cell($cell)), $header);
        $map = [];

        foreach (self::HEADER_ALIASES as $key => $aliases) {
            $cleanedAliases = array_map(fn ($alias) => $this->cleanToken($alias), $aliases);

            foreach ($normalized as $index => $cell) {
                if (in_array($cell, $cleanedAliases, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }

        return isset($map['nama']) ? $map : null;
    }

    protected function rowValues(array $raw, ?array $headerMap): array
    {
        if ($headerMap === null) {
            return [
                'role' => $raw[0] ?? '',
                'nama' => $raw[1] ?? '',
                'email' => $raw[2] ?? '',
                'kelas' => $raw[3] ?? '',
            ];
        }

        return [
            'role' => $this->rowValue($raw, $headerMap, 'role'),
            'nama' => $this->rowValue($raw, $headerMap, 'nama'),
            'email' => $this->rowValue($raw, $headerMap, 'email'),
            'kelas' => $this->rowValue($raw, $headerMap, 'kelas'),
        ];
    }

    protected function rowValue(array $raw, array $headerMap, string $field): string
    {
        if (! isset($headerMap[$field])) {
            return '';
        }

        return $raw[$headerMap[$field]] ?? '';
    }

    protected function normalizeRole(string $role): ?string
    {
        $role = strtolower(trim($role));

        if ($role === '') {
            return 'siswa';
        }

        if (in_array($role, ['guru', 'pengajar', 'teacher'], true)) {
            return 'guru';
        }

        if (in_array($role, ['siswa', 'murid', 'pelajar', 'student'], true)) {
            return 'siswa';
        }

        return null;
    }

    protected function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $this->stripBom(trim($value));
    }

    protected function cleanToken(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function stripBom(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        $value = str_replace("\u{FEFF}", '', $value);

        return $value;
    }
}
