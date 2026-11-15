<?php

namespace App\Services;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Export data menjadi file .xlsx yang dirapikan dan diberi warna senada dengan
 * tampilan web. Semua export (Data Siswa, Data Kelas, Data Guru, Manajemen
 * Akun) memakai layanan ini supaya gayanya seragam.
 *
 * Warna diambil dari design token web (resources/css/design-tokens.css):
 * biru primary #1E86FF dan navy #0F3D73 yang dipakai di heading serta kartu.
 */
class XlsxExportService
{
    /** Biru primary web (primary-60). */
    public const WARNA_PRIMARY = 'FF1E86FF';

    /** Biru primary lebih gelap (primary-70) untuk guratan biar tegas. */
    public const WARNA_PRIMARY_GELAP = 'FF1970D4';

    /** Biru navy untuk judul sheet. */
    public const WARNA_NAVY = 'FF0F3D73';

    /** Abu-abu tulisan badan (secondary-100). */
    public const WARNA_TULISAN = 'FF595959';

    /** Biru muda (blue-50) untuk baris selang-seling. */
    public const WARNA_BARIS_GENAP = 'FFEFF6FF';

    /** Biru muda (blue-200) untuk kisi antar sel. */
    public const WARNA_GARIS = 'FFBFDBFE';

    /**
     * Biru primary gelap untuk tepi luar tabel. Kisi antar sel sengaja
     * dibiarkan tipis dan muda, sedangkan tepi luar ditebalkan supaya batas
     * area data langsung terbaca begitu file dibuka di Excel.
     */
    public const WARNA_GARIS_TEBAL = 'FF1970D4';

    /**
     * @param  array<int, string>  $judulSeri  judul kolom (baris header)
     * @param  array<int, array<int, mixed>>  $baris  isi tabel per baris
     * @param  array{subjudul?: string, text_columns?: array<int, int>, sheet?: string}  $opsi
     *                                                                                          subjudul: baris keterangan di bawah judul,
     *                                                                                          text_columns: nomor kolom (1-based) yang
     *                                                                                          dipaksa tetap teks (misal "12-1"), sheet:
     *                                                                                          nama tab workbook.
     */
    public function download(string $namaFile, string $judul, array $judulSeri, array $baris, array $opsi = []): Response
    {
        $subjudul = $opsi['subjudul'] ?? null;
        $textColumns = array_flip($opsi['text_columns'] ?? []);

        $spreadsheet = new Spreadsheet;
        $ws = $spreadsheet->getActiveSheet();
        $ws->setTitle($opsi['sheet'] ?? 'Data');

        $jumlahKolom = count($judulSeri);
        $lastColumnLetter = $this->namaKolom($jumlahKolom);

        $this->isiJudul($ws, $judul, $subjudul, $jumlahKolom);

        $headerRow = $subjudul === null ? 2 : 3;
        $rowPertama = $headerRow + 1;
        $rowTerakhir = $headerRow + max(count($baris), 1);

        $this->gayaHeader($ws, $headerRow, $jumlahKolom);

        foreach ($judulSeri as $i => $judul) {
            $ws->setCellValueExplicit([$i + 1, $headerRow], (string) $judul, DataType::TYPE_STRING);
        }

        foreach ($baris as $nomor => $row) {
            $this->isiBaris($ws, $headerRow + 1 + $nomor, $row, $jumlahKolom, $textColumns);
        }

        // Satu baris kosong di bawah tabel kalau tidak ada data, supaya area
        // auto-filter dan border tetap rapi.
        if ($baris === []) {
            foreach (range(1, $jumlahKolom) as $kolom) {
                $ws->getCell([$kolom, $headerRow + 1])->setValue('');
            }
        }

        $this->gayaKisi($ws, $rowPertama, $rowTerakhir, $jumlahKolom);
        $this->gayaTepiTabel($ws, $headerRow, $rowTerakhir, $jumlahKolom);
        $this->aturLebarKolom($ws, $judulSeri, $baris);
        $this->aturCetak($ws, $headerRow);

        $ws->freezePane('A'.($headerRow + 1));
        $ws->setAutoFilter($this->selRange(1, $headerRow, $jumlahKolom, $rowTerakhir));

        $writer = new XlsxWriter($spreadsheet);

        $pathTemp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer->save($pathTemp);
        $isi = file_get_contents($pathTemp);
        @unlink($pathTemp);

        return response($isi, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
        ]);
    }

    /**
     * Baris pertama judul bersatu di semua kolom, warna navy seperti heading
     * di web. Kalau ada subjudul, ditaruh di baris kedua dengan warna abu.
     */
    protected function isiJudul(Worksheet $ws, string $judul, ?string $subjudul, int $jumlahKolom): void
    {
        $lastColumnLetter = $this->namaKolom($jumlahKolom);

        $ws->mergeCells("A1:{$lastColumnLetter}1");
        $ws->setCellValue('A1', $judul);
        $ws->getStyle('A1')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::WARNA_NAVY],
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

        if ($subjudul === null) {
            return;
        }

        $ws->mergeCells("A2:{$lastColumnLetter}2");
        $ws->setCellValue('A2', $subjudul);
        $ws->getStyle('A2')->applyFromArray([
            'font' => [
                'name' => 'Calibri',
                'size' => 10,
                'italic' => true,
                'color' => ['argb' => self::WARNA_TULISAN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $ws->getRowDimension(2)->setRowHeight(18);
    }

    /**
     * Baris header pakai biru primary (tombol utama di web), teks putih tebal
     * dan rata tengah. Guratan bawah sedikit lebih gelap supaya terpisah jelas
     * dari data.
     */
    protected function gayaHeader(Worksheet $ws, int $headerRow, int $jumlahKolom): void
    {
        $range = $this->selRange(1, $headerRow, $jumlahKolom, $headerRow);

        $ws->getStyle($range)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::WARNA_PRIMARY],
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
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => self::WARNA_GARIS],
                ],
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => self::WARNA_PRIMARY_GELAP],
                ],
            ],
        ]);
        $ws->getRowDimension($headerRow)->setRowHeight(24);
    }

    /**
     * Isi satu baris data. Kolom yang diminta tetap teks (lewat opsi
     * text_columns) biar kelas seperti "12-1" tidak berubah jadi tanggal saat
     * file dibuka. Baris genap diberi warna biru muda selang-seling.
     */
    protected function isiBaris(Worksheet $ws, int $row, array $data, int $jumlahKolom, array $textColumns): void
    {
        foreach (range(1, $jumlahKolom) as $kolom) {
            $cell = $ws->getCell([$kolom, $row]);
            $nilai = $data[$kolom - 1] ?? null;

            // Sel kosong diberi tanda "-" supaya di Excel kelihatan jelas data
            // sengaja kosong, bukan baris yang lupa diisi.
            if ($nilai === null || $nilai === '') {
                $nilai = '-';
            }

            if (isset($textColumns[$kolom])) {
                $cell->setValueExplicit((string) $nilai, DataType::TYPE_STRING);
                $cell->getStyle()->getNumberFormat()->setFormatCode('@');
            } elseif (is_int($nilai) || is_float($nilai)) {
                $cell->setValue($nilai);
            } else {
                // Teks selalu ditulis eksplisit sebagai string. Kalau memakai
                // setValue(), nilai yang diawali "=" dibaca PhpSpreadsheet
                // sebagai formula, sehingga nama hasil impor bisa dieksekusi
                // saat file export dibuka di Excel.
                $cell->setValueExplicit((string) $nilai, DataType::TYPE_STRING);
            }

            $horisontal = $kolom === 1
                ? Alignment::HORIZONTAL_CENTER
                : ($this->angka($nilai) ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_LEFT);

            $cell->getStyle()->applyFromArray([
                'alignment' => [
                    'horizontal' => $horisontal,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'name' => 'Calibri',
                    'size' => 10,
                ],
            ]);

            if ($row % 2 === 0) {
                $cell->getStyle()->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => self::WARNA_BARIS_GENAP],
                ]);
            }
        }
    }

    /**
     * Kisi antar sel dan tepi luar tabel. Kisi pakai garis tipis supaya isi
     * tetap mudah dibaca, lalu seluruh area tabel dilingkari garis tipis juga
     * supaya batas kiri-kanan-bawahnya kelihatan sebagai satu blok, bukan
     * kolom-kolom lepas.
     */
    protected function gayaKisi(Worksheet $ws, int $rowPertama, int $rowTerakhir, int $jumlahKolom): void
    {
        $range = $this->selRange(1, $rowPertama, $jumlahKolom, $rowTerakhir);

        $ws->getStyle($range)->getBorders()->applyFromArray([
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => self::WARNA_GARIS],
            ],
        ]);
    }

    /**
     * Tepi luar tabel, dari baris header sampai baris data terakhir. Dipisah
     * dari gayaKisi karena header sudah punya garis bawahnya sendiri yang lebih
     * tebal, jadi tepi tabel harus dipasang setelahnya supaya tidak menimpa
     * pemisah header dengan data.
     */
    protected function gayaTepiTabel(Worksheet $ws, int $headerRow, int $rowTerakhir, int $jumlahKolom): void
    {
        $range = $this->selRange(1, $headerRow, $jumlahKolom, $rowTerakhir);

        $ws->getStyle($range)->getBorders()->applyFromArray([
            'outline' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => self::WARNA_GARIS_TEBAL],
            ],
        ]);
    }

    /**
     * Lebar kolom mengikuti isi terpanjang (judul ikut dihitung), dibatasi
     * antara 10 dan 60 supaya kolom email yang panjang tidak melebar terus.
     *
     * @param  array<int, string>  $judulSeri
     * @param  array<int, array<int, mixed>>  $baris
     */
    protected function aturLebarKolom(Worksheet $ws, array $judulSeri, array $baris): void
    {
        foreach ($judulSeri as $i => $judul) {
            $terpanjang = mb_strlen((string) $judul);

            foreach ($baris as $row) {
                $nilai = $row[$i] ?? '';
                if ($nilai !== null) {
                    $terpanjang = max($terpanjang, mb_strlen((string) $nilai));
                }
            }

            $ws->getColumnDimensionByColumn($i + 1)
                ->setWidth(min(max($terpanjang + 3, 10), 60));
        }
    }

    /** Pengaturan cetak: lanskap, lebarkan ke satu halaman, header diulang. */
    protected function aturCetak(Worksheet $ws, int $headerRow): void
    {
        $page = $ws->getPageSetup();

        $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $page->setFitToWidth(1);
        $page->setFitToHeight(0);
        $ws->getPageMargins()->setTop(0.5)->setRight(0.4)->setBottom(0.5)->setLeft(0.4);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, $headerRow);
    }

    protected function namaKolom(int $kolom): string
    {
        return Coordinate::stringFromColumnIndex($kolom);
    }

    protected function selRange(int $kolomAwal, int $barisAwal, int $kolomAkhir, int $barisAkhir): string
    {
        return $this->namaKolom($kolomAwal).$barisAwal.':'.$this->namaKolom($kolomAkhir).$barisAkhir;
    }

    protected function angka(mixed $nilai): bool
    {
        return is_int($nilai) || is_float($nilai) || (is_string($nilai) && is_numeric($nilai));
    }
}
