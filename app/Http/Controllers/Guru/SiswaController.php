<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\KelasRombel;
use App\Models\User;
use App\Services\GuruAkademik;
use App\Support\Kelas;
use App\Support\StrukturKelas;
use App\Support\Waktu;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiswaController extends Controller
{
    public function __construct(private readonly GuruAkademik $akademik) {}

    public function index(Request $request)
    {
        $guru = $request->user();
        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        $filter = $this->validatedFilter($request);
        $allSiswa = $this->queryFiltered($guru, $filter);
        $siswa = $allSiswa->map(fn (User $user) => $this->row($user));

        $count = fn (string $status) => $siswa->where('status', $status)->count();

        return view('dashboard.guru.data-siswa.index', [
            'siswa' => $siswa,
            'stats' => [
                [
                    'key' => 'total',
                    'label' => 'Total Siswa',
                    'value' => $siswa->count(),
                    'icon' => 'lucide-users',
                ],
                [
                    'key' => 'aktif',
                    'label' => 'Siswa Aktif',
                    'value' => $count(User::STATUS_AKTIF),
                    'icon' => 'lucide-user-check',
                ],
                [
                    'key' => 'nonaktif',
                    'label' => 'Siswa Nonaktif',
                    'value' => $count(User::STATUS_NONAKTIF),
                    'icon' => 'lucide-user-x',
                ],
                [
                    'key' => 'lulus',
                    'label' => 'Siswa Lulus',
                    'value' => $count(User::STATUS_LULUS),
                    'icon' => 'lucide-graduation-cap',
                ],
            ],
            'struktur_kelas' => StrukturKelas::dariSiswa(
                $siswa,
                KelasRombel::query()->get(['jurusan', 'kelas'])
            ),
            'siswaData' => [
                'kelas_contoh' => Kelas::CONTOH,
                'kelas_contoh_rombel' => Kelas::CONTOH_ROMBEL,
                'statuses' => User::STATUSES,
                'rows' => $siswa->values(),
            ],
            'filters' => $filter,
        ]);
    }

    public function export(Request $request)
    {
        $guru = $request->user();
        if (! $guru instanceof User || $guru->role !== 'guru') {
            abort(403);
        }

        $filter = $this->validatedFilter($request);
        $siswa = $this->queryFiltered($guru, $filter)->map(fn (User $user) => $this->row($user));

        return $this->streamXlsx($siswa, $filter);
    }

    protected function streamXlsx($siswa, array $filter): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        $sheet->setCellValue('A1', 'Data Siswa');
        $sheet->setCellValue('A2', 'Diunduh pada '.Waktu::tanggalJam(now()));
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E86FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A4:I4')->applyFromArray($headerStyle);

        $headers = ['No', 'NIS', 'Nama Siswa', 'Email', 'Kelas', 'Jurusan', 'Status', 'Password Awal', 'Status Password'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 4], $h);
        }

        $row = 5;
        foreach ($siswa as $index => $s) {
            $sheet->setCellValue('A'.$row, $index + 1);
            // Semua kolom teks ditulis eksplisit sebagai string supaya nilai
            // yang diawali "=" tidak berubah menjadi formula di Excel.
            $sheet->setCellValueExplicit('B'.$row, (string) ($s['nis'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, (string) ($s['nama'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$row, (string) ($s['email'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$row, (string) ($s['kelas'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$row, (string) ($s['jurusan'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G'.$row, (string) ($s['status'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H'.$row, (string) ($s['password_awal'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('I'.$row, (string) ($s['status_password'] ?? ''), DataType::TYPE_STRING);

            $style = [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ];
            $sheet->getStyle('A'.$row.':I'.$row)->applyFromArray($style);
            if (empty($s['password_awal'])) {
                $sheet->getStyle('H'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
            }
            $row++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'data-siswa-guru-'.(Waktu::zona(now())?->format('Ymd_His') ?? now()->format('Ymd_His')).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Total-Siswa' => (string) $siswa->count(),
            'X-Total-Aktif' => (string) $siswa->where('status', User::STATUS_AKTIF)->count(),
            'X-Total-Nonaktif' => (string) $siswa->where('status', User::STATUS_NONAKTIF)->count(),
            'X-Total-Lulus' => (string) $siswa->where('status', User::STATUS_LULUS)->count(),
        ]);
    }

    protected function queryFiltered(User $guru, array $filter)
    {
        $q = mb_strtolower($filter['q']);
        $kelasList = $this->akademik->kelasDiampu($guru);

        // Pengurutan disamakan dengan Data Siswa admin: kelas dulu, lalu nama,
        // supaya baris yang tampil di dua halaman urutannya sama persis.
        return User::query()
            ->where('role', 'siswa')
            ->orderBy('kelas')
            ->orderBy('name')
            ->get()
            ->filter(function (User $user) use ($filter, $q, $kelasList) {
                // Cakupan guru mengikuti jadwal/nilai yang dimilikinya. Kalau
                // belum ada keduanya, semua rombel siswa aktif tetap terlihat.
                if ($kelasList->isNotEmpty() && ! $this->akademik->cocokRombel($user, $kelasList)) {
                    return false;
                }
                if ($filter['status'] !== null && $user->statusLabel() !== $filter['status']) {
                    return false;
                }
                if ($filter['jurusan'] !== null && $user->jurusan !== $filter['jurusan']) {
                    return false;
                }
                if ($filter['tingkat'] !== null && Kelas::tingkat($user->kelas) !== $filter['tingkat']) {
                    return false;
                }
                if ($filter['rombel'] !== null) {
                    $normalizeRombel = Kelas::normalize((string) $filter['rombel']);
                    if ($filter['rombel_match'] === 'kelas') {
                        if (Kelas::normalize((string) $user->kelas) !== $normalizeRombel) {
                            return false;
                        }
                    } else {
                        if (Kelas::normalize((string) $user->kelas) !== $normalizeRombel) {
                            $userTingkat = Kelas::tingkat($user->kelas);
                            if ($userTingkat !== $filter['rombel']) {
                                return false;
                            }
                        }
                    }
                }
                if ($q === '') {
                    return true;
                }

                return str_contains(mb_strtolower((string) $user->name), $q)
                    || str_contains((string) $user->nis, $q)
                    || str_contains(mb_strtolower((string) $user->email), $q);
            })
            ->values();
    }

    protected function validatedFilter(Request $request): array
    {
        $text = fn (?string $value): ?string => ($value === null || $value === '') ? null : $value;

        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => $text($request->query('status')),
            'jurusan' => $text($request->query('jurusan')),
            'tingkat' => $text($request->query('tingkat')),
            'rombel' => $text($request->query('rombel')),
            'rombel_match' => $text($request->query('rombel_match')),
        ];
    }

    protected function row(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->name,
            'nis' => $user->nis,
            'email' => $user->email,
            'kelas' => $user->kelas,
            'tingkat' => Kelas::tingkat($user->kelas),
            'jurusan' => $user->jurusan,
            'status' => $user->statusLabel(),
            'password_awal' => $user->visiblePassword(),
            'status_password' => $user->must_change_password ? 'Belum ubah password' : 'Sudah ubah password',
        ];
    }
}
