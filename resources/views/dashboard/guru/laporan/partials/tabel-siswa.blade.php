{{--
    Tabel Daftar Nilai per Siswa untuk halaman Laporan Guru.
    Dipakai dua tempat: panel layar (berhalaman, 5 baris) dan salinan cetak
    (seluruh baris). `$rows` berisi baris dari rekapPerSiswaSemua; `$nomorAwal`
    adalah nomor baris pertama — firstItem() di layar, 1 untuk salinan cetak.
--}}
@php
    $angkaLokal = fn ($nilai) => $nilai === null ? '—' : str_replace('.', ',', (string) $nilai);
@endphp

<table class="w-full min-w-[720px] text-left text-sm">
    <thead>
        <tr class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <th class="px-4 py-3.5 text-center font-semibold">No</th>
            <th class="px-4 py-3.5 font-semibold">NIS</th>
            <th class="px-4 py-3.5 font-semibold">Nama</th>
            <th class="px-4 py-3.5 font-semibold">Kelas</th>
            <th class="px-4 py-3.5 font-semibold">Jurusan</th>
            @foreach ($mapelDiampu as $mapel)
                <th class="px-4 py-3.5 text-center font-semibold">{{ $mapel->nama }}</th>
            @endforeach
            <th class="px-4 py-3.5 text-center font-semibold">Rata-Rata</th>
            <th class="px-4 py-3.5 text-center font-semibold">Nilai</th>
            <th class="px-4 py-3.5 text-center font-semibold">Tuntas</th>
            <th class="px-4 py-3.5 text-center font-semibold">Belum</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100 text-slate-800">
        @forelse ($rows as $row)
            <tr class="transition hover:bg-slate-50/70">
                <td class="px-4 py-3.5 text-center text-slate-500">{{ $nomorAwal + $loop->index }}</td>
                <td class="px-4 py-3.5 font-mono text-xs text-slate-500">{{ $row['nis'] ?: '-' }}</td>
                <td class="px-4 py-3.5 font-medium text-slate-900">{{ $row['nama'] }}</td>
                <td class="px-4 py-3.5 text-slate-600">{{ $row['kelas'] }}</td>
                <td class="px-4 py-3.5 text-slate-600">{{ $row['jurusan'] ?: '—' }}</td>
                @foreach ($mapelDiampu as $mapel)
                    @php $nilaiSiswa = $row['nilai'][(int) $mapel->id] ?? null; @endphp
                    <td class="px-4 py-3.5 text-center tabular-nums">
                        @if ($nilaiSiswa !== null)
                            <span class="font-semibold {{ $nilaiSiswa->tuntas() ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $nilaiSiswa->nilai }}
                            </span>
                        @else
                            <span class="text-slate-300">—</span>
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-3.5 text-center font-semibold tabular-nums text-slate-800">{{ $angkaLokal($row['rata_rata']) }}</td>
                <td class="px-4 py-3.5 text-center tabular-nums text-slate-600">{{ $row['jumlah_nilai'] }}</td>
                <td class="px-4 py-3.5 text-center tabular-nums text-emerald-600">{{ $row['tuntas'] }}</td>
                <td class="px-4 py-3.5 text-center tabular-nums text-rose-600">{{ $row['belum_tuntas'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $kolomSiswa }}" class="px-6 py-12 text-center text-sm text-slate-500">
                    @if (($filter['cari'] ?? '') !== '')
                        Tidak ada siswa yang cocok dengan pencarian "{{ $filter['cari'] }}".
                    @else
                        Belum ada nilai pada periode ini.
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
