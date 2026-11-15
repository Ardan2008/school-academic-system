{{--
    Tabel Rekap per Kelas untuk halaman Laporan Guru.
    Dipakai dua tempat: panel layar (berhalaman, 5 baris) dan salinan cetak
    (seluruh baris). `$rows` berisi baris dari StatistikNilai::rekapPerKelas
    yang sudah diurutkan, lengkap dengan nomor urut global.
--}}
@php
    $angkaLokal = fn ($nilai) => $nilai === null ? '—' : str_replace('.', ',', (string) $nilai);
@endphp

<table class="w-full min-w-[640px] text-left text-sm">
    <thead>
        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <th class="px-4 py-3.5 text-center font-semibold">No</th>
            <th class="px-4 py-3.5 font-semibold">Kelas</th>
            <th class="px-4 py-3.5 font-semibold">Jurusan</th>
            <th class="px-4 py-3.5 text-center font-semibold">Siswa</th>
            <th class="px-4 py-3.5 text-center font-semibold">Nilai</th>
            <th class="px-4 py-3.5 text-center font-semibold">Rata-Rata</th>
            <th class="px-4 py-3.5 text-center font-semibold">Tuntas</th>
            <th class="px-4 py-3.5 text-center font-semibold">Belum</th>
            <th class="px-4 py-3.5 text-center font-semibold">% Tuntas</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 text-slate-800">
        @forelse ($rows as $row)
            <tr class="transition hover:bg-slate-50/70">
                <td class="px-4 py-3.5 text-center text-slate-500">{{ $row['no'] }}</td>
                <td class="px-4 py-3.5 font-medium text-slate-900">{{ $row['kode'] }}</td>
                <td class="px-4 py-3.5 text-slate-600">{{ $row['nama'] ?: '—' }}</td>
                <td class="px-4 py-3.5 text-center text-slate-600">{{ $row['jumlah_siswa'] }}</td>
                <td class="px-4 py-3.5 text-center text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                <td class="px-4 py-3.5 text-center font-semibold tabular-nums text-slate-800">{{ $angkaLokal($row['rata_rata']) }}</td>
                <td class="px-4 py-3.5 text-center tabular-nums text-emerald-600">{{ $row['tuntas'] }}</td>
                <td class="px-4 py-3.5 text-center tabular-nums text-rose-600">{{ $row['belum_tuntas'] }}</td>
                <td class="px-4 py-3.5 text-center tabular-nums text-slate-600">{{ $row['persen_tuntas'] }}%</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-500">Belum ada nilai pada periode ini.</td>
            </tr>
        @endforelse
    </tbody>
</table>
