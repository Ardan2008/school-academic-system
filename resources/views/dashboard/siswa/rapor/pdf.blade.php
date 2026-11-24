<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor {{ $siswa->name }} - {{ $periodeLabel }}</title>
    <style>
        @page { margin: 20mm 18mm 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 18px; }
        .school-name { font-size: 13px; font-weight: 700; letter-spacing: 0.4px; margin: 0; color: #111827; }
        .doc-title { font-size: 16px; font-weight: 700; letter-spacing: 0.6px; margin: 4px 0 8px 0; color: #111827; text-transform: uppercase; }
        .periode { font-size: 11px; color: #374151; margin: 0; }
        .periode span { display: inline-block; margin: 0 10px; }
        .section { margin-top: 14px; }
        .section-title { font-size: 11px; font-weight: 700; margin: 0 0 6px 0; color: #111827; }
        .identity { width: 100%; border-collapse: collapse; }
        .identity td { padding: 3px 0; vertical-align: top; font-size: 11px; }
        .identity .label { width: 150px; color: #374151; }
        .identity .sep { width: 14px; color: #374151; }
        .identity .value { font-weight: 600; color: #111827; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.data th, table.data td { border: 1px solid #374151; padding: 7px 8px; }
        table.data th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; color: #111827; font-weight: 700; }
        table.data td { font-size: 11px; color: #111827; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .rekap { width: 100%; border-collapse: collapse; margin-top: 2px; }
        .rekap td { border: 1px solid #374151; padding: 7px 8px; font-size: 11px; }
        .rekap .label { width: 60%; color: #374151; background: #f9fafb; }
        .rekap .value { font-weight: 700; color: #111827; }
    </style>
</head>
<body>
    <div class="header">
        <p class="school-name">SASKANSA</p>
        <p class="doc-title">RAPOR HASIL BELAJAR SISWA</p>
        <p class="periode">
            <span>Tahun Ajaran: <strong>{{ $periode['tahun_ajaran'] }}</strong></span>
            <span>Semester: <strong>{{ $periode['semester'] }}</strong></span>
        </p>
    </div>

    <div class="section">
        <p class="section-title">Identitas Siswa</p>
        <table class="identity">
            <tr>
                <td class="label">Nama Lengkap</td>
                <td class="sep">:</td>
                <td class="value">{{ $siswa->name }}</td>
            </tr>
            <tr>
                <td class="label">NIS</td>
                <td class="sep">:</td>
                <td class="value">{{ $siswa->nis ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Kelas</td>
                <td class="sep">:</td>
                <td class="value">{{ $siswa->kelas ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Jurusan</td>
                <td class="sep">:</td>
                <td class="value">{{ $siswa->jurusan ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Daftar Nilai</p>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 34px;" class="center">No</th>
                    <th>Mata Pelajaran</th>
                    <th style="width: 60px;" class="center">KKM</th>
                    <th style="width: 60px;" class="center">Nilai</th>
                    <th style="width: 110px;" class="center">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($nilai as $i => $baris)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>{{ $baris->mataPelajaran?->nama ?? '-' }}</td>
                        <td class="center">{{ $baris->kkm }}</td>
                        <td class="center bold">{{ $baris->nilai }}</td>
                        <td class="center">{{ $baris->tuntas() ? 'Tuntas' : 'Belum Tuntas' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <p class="section-title">Rekapitulasi Nilai</p>
        <table class="rekap">
            <tr>
                <td class="label">Jumlah mata pelajaran</td>
                <td class="value">{{ $ringkasan['jumlah_mapel'] }}</td>
            </tr>
            <tr>
                <td class="label">Rata-rata nilai</td>
                <td class="value">{{ $ringkasan['rata_rata'] !== null ? number_format($ringkasan['rata_rata'], 2, ',', '.') : '-' }}</td>
            </tr>
            <tr>
                <td class="label">Jumlah mata pelajaran tuntas</td>
                <td class="value">{{ $ringkasan['tuntas'] }}</td>
            </tr>
            <tr>
                <td class="label">Jumlah mata pelajaran belum tuntas</td>
                <td class="value">{{ $ringkasan['belum_tuntas'] }}</td>
            </tr>
            <tr>
                <td class="label">Persentase ketuntasan</td>
                <td class="value">{{ $ringkasan['persen_tuntas'] !== null ? $ringkasan['persen_tuntas'].'%' : '-' }}</td>
            </tr>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf) && isset($PAGE_COUNT) && $PAGE_COUNT > 1) {
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $size = 9;
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = $pdf->get_width() - $width - 36;
            $y = $pdf->get_height() - 28;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.35, 0.35, 0.35));
        }
    </script>
</body>
</html>
