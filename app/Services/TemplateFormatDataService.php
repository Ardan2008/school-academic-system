<?php

namespace App\Services;

use App\Models\Jurusan;
use App\Models\KelasRombel;
use App\Models\MataPelajaran;
use App\Support\Kelas;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use RuntimeException;
use ZipArchive;

/**
 * Template Excel untuk fitur Import Data di halaman Manajemen Akun.
 *
 * Struktur kolom mengikuti AccountImportService: NIP untuk Guru, NIS untuk
 * Siswa, Kelas+Jurusan untuk Kelas, Nama Jurusan untuk Jurusan, serta
 * Kode+Nama Mata Pelajaran untuk Mata Pelajaran. Header selalu diletakkan
 * di baris pertama supaya file bisa langsung diimpor tanpa penyesuaian.
 */
class TemplateFormatDataService
{
    public const TYPE_GURU = 'guru';

    public const TYPE_SISWA = 'siswa';

    public const TYPE_KELAS = 'kelas';

    public const TYPE_JURUSAN = 'jurusan';

    public const TYPE_MATA_PELAJARAN = 'mata_pelajaran';

    /** @var array<string, array{label: string, description: string, file: string, icon: string, sheet: string}> */
    public const TYPES = [
        self::TYPE_GURU => [
            'label' => 'Data Guru',
            'description' => 'NIP, nama, email, mata pelajaran, dan status guru.',
            'file' => 'Template_Data_Guru.xlsx',
            'icon' => 'user-round',
            'sheet' => 'Data Guru',
        ],
        self::TYPE_SISWA => [
            'label' => 'Data Siswa',
            'description' => 'NIS, nama, email, kelas, jurusan, dan status siswa.',
            'file' => 'Template_Data_Siswa.xlsx',
            'icon' => 'graduation-cap',
            'sheet' => 'Data Siswa',
        ],
        self::TYPE_KELAS => [
            'label' => 'Data Kelas',
            'description' => 'Nama kelas/rombel beserta jurusannya.',
            'file' => 'Template_Data_Kelas.xlsx',
            'icon' => 'school',
            'sheet' => 'Data Kelas',
        ],
        self::TYPE_JURUSAN => [
            'label' => 'Data Jurusan',
            'description' => 'Nama jurusan dan status aktifnya.',
            'file' => 'Template_Data_Jurusan.xlsx',
            'icon' => 'layers',
            'sheet' => 'Data Jurusan',
        ],
        self::TYPE_MATA_PELAJARAN => [
            'label' => 'Data Mata Pelajaran',
            'description' => 'Kode dan nama mata pelajaran.',
            'file' => 'Template_Data_Mata_Pelajaran.xlsx',
            'icon' => 'book-open',
            'sheet' => 'Data Mata Pelajaran',
        ],
    ];

    /**
     * Pilihan template untuk modal di halaman Manajemen Akun.
     *
     * @return array<int, array{key: string, label: string, description: string, file: string, icon: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::TYPES as $key => $meta) {
            $options[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'file' => $meta['file'],
                'icon' => 'lucide-'.$meta['icon'],
            ];
        }

        return $options;
    }

    public static function isValidType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /**
     * Bangun file unduhan: satu template menjadi .xlsx, beberapa template
     * digabung menjadi satu arsip .zip.
     *
     * @param  array<int, string>  $types
     * @return array{content: string, filename: string, mime: string}
     */
    public function download(array $types): array
    {
        $types = array_values(array_unique($types));

        foreach ($types as $type) {
            if (! self::isValidType($type)) {
                throw new InvalidArgumentException('Jenis template tidak dikenali.');
            }
        }

        if (count($types) === 0) {
            throw new InvalidArgumentException('Pilih minimal satu template.');
        }

        if (count($types) === 1) {
            $type = $types[0];

            return [
                'content' => $this->build($type),
                'filename' => self::TYPES[$type]['file'],
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ];
        }

        return [
            'content' => $this->buildZip($types),
            'filename' => 'Template_Format_Data_SASKANSA.zip',
            'mime' => 'application/zip',
        ];
    }

    /**
     * Buat satu file template .xlsx dan kembalikan isinya sebagai string.
     */
    public function build(string $type): string
    {
        if (! self::isValidType($type)) {
            throw new InvalidArgumentException('Jenis template tidak dikenali.');
        }

        $definition = $this->definition($type);

        $spreadsheet = new Spreadsheet;
        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle(self::TYPES[$type]['sheet']);

        $this->writeDataSheet($dataSheet, $definition);

        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Petunjuk');
        $this->writeGuideSheet($guideSheet, $definition);

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'tpl');

        try {
            (new XlsxWriter($spreadsheet))->save($path);
            $content = file_get_contents($path);
        } finally {
            @unlink($path);
            $spreadsheet->disconnectWorksheets();
        }

        if ($content === false) {
            throw new RuntimeException('Template gagal dibuat.');
        }

        return $content;
    }

    /**
     * Gabungkan beberapa template .xlsx ke dalam satu file ZIP.
     *
     * @param  array<int, string>  $types
     */
    public function buildZip(array $types): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new RuntimeException('Arsip ZIP gagal dibuat.');
        }

        foreach ($types as $type) {
            if (! self::isValidType($type)) {
                continue;
            }

            $zip->addFromString(self::TYPES[$type]['file'], $this->build($type));
        }

        $zip->close();

        $content = file_get_contents($path);
        @unlink($path);

        if ($content === false) {
            throw new RuntimeException('Arsip ZIP gagal dibaca.');
        }

        return $content;
    }

    /**
     * @return array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}
     */
    protected function definition(string $type): array
    {
        return match ($type) {
            self::TYPE_GURU => $this->guruDefinition(),
            self::TYPE_SISWA => $this->siswaDefinition(),
            self::TYPE_KELAS => $this->kelasDefinition(),
            self::TYPE_JURUSAN => $this->jurusanDefinition(),
            default => $this->mataPelajaranDefinition(),
        };
    }

    /**
     * @return array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}
     */
    protected function guruDefinition(): array
    {
        return [
            'title' => 'Data Guru',
            'headers' => ['NIP', 'Nama', 'Email', 'Mata Pelajaran', 'Status'],
            'example' => [
                '198001012005011001',
                'Contoh Guru Pertama',
                'contoh.guru@saskansa.sch.id',
                $this->contohMataPelajaran(),
                'Aktif',
            ],
            'text_columns' => [1, 2, 3, 4],
            'guide' => [
                'Isi data mulai baris 2. Baris 1 berisi nama kolom dan tidak boleh diubah.',
                'Semua kolom wajib diisi; jangan mengosongkan kolom mana pun.',
                'NIP hanya boleh berisi huruf, angka, tanda hubung (-), atau garis miring (/), maksimal 20 karakter.',
                'Email harus berformat valid dan belum dipakai akun lain.',
                'Mata Pelajaran harus sudah terdaftar di menu Data Mata Pelajaran.',
                'Status yang diperbolehkan: Aktif, Nonaktif, atau Cuti.',
                'Baris contoh hanya panduan. Ganti atau hapus baris contoh sebelum file diimpor.',
                'Simpan file sebagai .xlsx lalu impor melalui tombol Import di halaman Manajemen Akun.',
            ],
        ];
    }

    /**
     * @return array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}
     */
    protected function siswaDefinition(): array
    {
        $jurusan = $this->contohJurusan();

        return [
            'title' => 'Data Siswa',
            'headers' => ['NIS', 'Nama', 'Email', 'Kelas', 'Jurusan', 'Status'],
            'example' => [
                '2024001001',
                'Contoh Siswa Pertama',
                'contoh.siswa@saskansa.sch.id',
                $this->contohKelas($jurusan),
                $jurusan,
                'Aktif',
            ],
            'text_columns' => [1, 2, 3, 4, 5],
            'guide' => [
                'Isi data mulai baris 2. Baris 1 berisi nama kolom dan tidak boleh diubah.',
                'Semua kolom wajib diisi; jangan mengosongkan kolom mana pun.',
                'NIS hanya boleh berisi huruf, angka, tanda hubung (-), atau garis miring (/), maksimal 30 karakter.',
                'Kelas ditulis dengan angka: "12" untuk satu rombel atau "12-1" untuk tingkat dengan nomor rombel.',
                'Jurusan dan Kelas harus sudah terdaftar di menu Data Jurusan dan Data Kelas.',
                'Status yang diperbolehkan: Aktif, Nonaktif, atau Lulus.',
                'Baris contoh hanya panduan. Ganti atau hapus baris contoh sebelum file diimpor.',
                'Simpan file sebagai .xlsx lalu impor melalui tombol Import di halaman Manajemen Akun.',
            ],
        ];
    }

    /**
     * @return array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}
     */
    protected function kelasDefinition(): array
    {
        return [
            'title' => 'Data Kelas',
            'headers' => ['Kelas', 'Jurusan'],
            'example' => ['10-1', $this->contohJurusan()],
            'text_columns' => [1, 2],
            'guide' => [
                'Isi data mulai baris 2. Baris 1 berisi nama kolom dan tidak boleh diubah.',
                'Semua kolom wajib diisi; jangan mengosongkan kolom mana pun.',
                'Kelas ditulis dengan angka: "12" untuk satu rombel atau "12-1" untuk tingkat dengan nomor rombel.',
                'Jurusan harus sudah terdaftar di menu Data Jurusan.',
                'Satu kombinasi Kelas dan Jurusan hanya boleh didaftarkan satu kali.',
                'Baris contoh hanya panduan. Ganti atau hapus baris contoh sebelum file diimpor.',
                'Simpan file sebagai .xlsx lalu impor melalui tombol Import di halaman Manajemen Akun.',
            ],
        ];
    }

    /**
     * @return array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}
     */
    protected function jurusanDefinition(): array
    {
        return [
            'title' => 'Data Jurusan',
            'headers' => ['Nama Jurusan', 'Status'],
            'example' => ['Teknik Komputer dan Jaringan', 'aktif'],
            'text_columns' => [1, 2],
            'guide' => [
                'Isi data mulai baris 2. Baris 1 berisi nama kolom dan tidak boleh diubah.',
                'Semua kolom wajib diisi; jangan mengosongkan kolom mana pun.',
                'Nama Jurusan maksimal 120 karakter dan tidak boleh sama dengan jurusan yang sudah terdaftar.',
                'Status yang diperbolehkan: aktif atau nonaktif.',
                'Baris contoh hanya panduan. Ganti atau hapus baris contoh sebelum file diimpor.',
                'Simpan file sebagai .xlsx lalu impor melalui tombol Import di halaman Manajemen Akun.',
            ],
        ];
    }

    /**
     * @return array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}
     */
    protected function mataPelajaranDefinition(): array
    {
        return [
            'title' => 'Data Mata Pelajaran',
            'headers' => ['Kode', 'Nama Mata Pelajaran'],
            'example' => ['MAT', 'Matematika'],
            'text_columns' => [1, 2],
            'guide' => [
                'Isi data mulai baris 2. Baris 1 berisi nama kolom dan tidak boleh diubah.',
                'Semua kolom wajib diisi; jangan mengosongkan kolom mana pun.',
                'Kode maksimal 20 karakter dan hanya boleh berisi huruf, angka, tanda hubung (-), titik (.), atau garis miring (/).',
                'Nama Mata Pelajaran maksimal 120 karakter dan tidak boleh sama dengan mata pelajaran yang sudah terdaftar.',
                'Baris contoh hanya panduan. Ganti atau hapus baris contoh sebelum file diimpor.',
                'Simpan file sebagai .xlsx lalu impor melalui tombol Import di halaman Manajemen Akun.',
            ],
        ];
    }

    /**
     * Isi sheet data: header di baris 1 dan satu baris contoh di baris 2.
     *
     * @param  array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}  $definition
     */
    protected function writeDataSheet(Worksheet $ws, array $definition): void
    {
        $headers = $definition['headers'];
        $example = $definition['example'];
        $textColumns = array_flip($definition['text_columns']);
        $jumlahKolom = count($headers);
        $lastColumn = Coordinate::stringFromColumnIndex($jumlahKolom);

        foreach ($headers as $index => $header) {
            $ws->setCellValueExplicit([$index + 1, 1], (string) $header, DataType::TYPE_STRING);
        }

        $ws->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => XlsxExportService::WARNA_PRIMARY],
            ],
            'font' => [
                'name' => 'Calibri',
                'size' => 11,
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $ws->getRowDimension(1)->setRowHeight(26);

        foreach ($example as $index => $value) {
            $column = $index + 1;
            $cell = $ws->getCell([$column, 2]);

            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            if (isset($textColumns[$column])) {
                $cell->getStyle()->getNumberFormat()->setFormatCode('@');
            }

            $cell->getStyle()->applyFromArray([
                'font' => [
                    'name' => 'Calibri',
                    'size' => 10,
                    'color' => ['argb' => XlsxExportService::WARNA_TULISAN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => XlsxExportService::WARNA_BARIS_GENAP],
                ],
            ]);
        }
        $ws->getRowDimension(2)->setRowHeight(22);

        $ws->getStyle("A1:{$lastColumn}2")->getBorders()->applyFromArray([
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => XlsxExportService::WARNA_GARIS],
            ],
            'outline' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => XlsxExportService::WARNA_GARIS_TEBAL],
            ],
        ]);

        foreach ($headers as $index => $header) {
            $terpanjang = max(mb_strlen($header), mb_strlen((string) ($example[$index] ?? '')));

            $ws->getColumnDimensionByColumn($index + 1)
                ->setWidth(min(max($terpanjang + 4, 14), 46));
        }

        $ws->freezePane('A2');
        $ws->setAutoFilter("A1:{$lastColumn}2");
        $ws->getPageSetup()->setOrientation('landscape');
        $ws->getPageSetup()->setFitToWidth(1);
        $ws->getPageSetup()->setFitToHeight(0);
    }

    /**
     * Sheet petunjuk pengisian, dipisah dari sheet data supaya header data
     * tetap berada di baris pertama saat diimpor.
     *
     * @param  array{title: string, headers: array<int, string>, example: array<int, string>, text_columns: array<int, int>, guide: array<int, string>}  $definition
     */
    protected function writeGuideSheet(Worksheet $ws, array $definition): void
    {
        $ws->mergeCells('A1:B1');
        $ws->setCellValueExplicit('A1', 'Petunjuk Pengisian Template '.$definition['title'], DataType::TYPE_STRING);
        $ws->getStyle('A1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => XlsxExportService::WARNA_NAVY],
            ],
            'font' => [
                'name' => 'Calibri',
                'size' => 14,
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $ws->getRowDimension(1)->setRowHeight(30);

        $ws->mergeCells('A2:B2');
        $ws->setCellValueExplicit(
            'A2',
            'Kolom wajib: '.implode(', ', $definition['headers']).'.',
            DataType::TYPE_STRING
        );
        $ws->getStyle('A2')->applyFromArray([
            'font' => [
                'name' => 'Calibri',
                'size' => 10,
                'italic' => true,
                'color' => ['argb' => XlsxExportService::WARNA_TULISAN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $ws->getRowDimension(2)->setRowHeight(20);

        $row = 4;

        foreach ($definition['guide'] as $index => $instruction) {
            $ws->setCellValueExplicit([1, $row], ($index + 1).'. '.$instruction, DataType::TYPE_STRING);
            $ws->getStyle([1, $row])->applyFromArray([
                'font' => [
                    'name' => 'Calibri',
                    'size' => 10,
                    'color' => ['argb' => XlsxExportService::WARNA_TULISAN],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true,
                ],
            ]);
            $ws->getRowDimension($row)->setRowHeight(30);

            $row++;
        }

        $ws->getColumnDimension('A')->setWidth(105);
        $ws->getColumnDimension('B')->setWidth(20);
    }

    protected function contohMataPelajaran(): string
    {
        $nama = MataPelajaran::query()->orderBy('nama')->value('nama');

        return $nama !== null && trim((string) $nama) !== ''
            ? trim((string) $nama)
            : 'Matematika';
    }

    protected function contohJurusan(): string
    {
        $nama = Jurusan::query()->orderBy('nama')->value('nama');

        if ($nama === null || trim((string) $nama) === '') {
            $nama = KelasRombel::query()->orderBy('jurusan')->value('jurusan');
        }

        return $nama !== null && trim((string) $nama) !== ''
            ? trim((string) $nama)
            : 'Rekayasa Perangkat Lunak';
    }

    protected function contohKelas(string $jurusan): string
    {
        $kelas = KelasRombel::query()
            ->where('jurusan', $jurusan)
            ->orderBy('kelas')
            ->value('kelas');

        return $kelas !== null && trim((string) $kelas) !== ''
            ? Kelas::normalize((string) $kelas)
            : '12-1';
    }
}
