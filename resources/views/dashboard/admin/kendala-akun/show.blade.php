@extends('layouts.dashboard')

@section('title', 'Detail Kendala Akun')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div class="mx-auto max-w-3xl">

        <a href="{{ route('dashboard.admin.kendala-akun.index') }}"
           class="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-800">
            <x-lucide-arrow-left class="h-4 w-4" />
            Kembali ke Kendala Akun
        </a>

        @php
            $role = (string) $permintaan->role;

            $roleBadge = [
                'guru' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                'siswa' => 'bg-amber-50 text-amber-700 ring-amber-200',
            ][$role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

            $statusBadge = [
                \App\Models\ItHelpRequest::STATUS_BARU => 'bg-blue-50 text-blue-700 ring-blue-200',
                \App\Models\ItHelpRequest::STATUS_DIPROSES => 'bg-amber-50 text-amber-700 ring-amber-200',
                \App\Models\ItHelpRequest::STATUS_SUDAH_DIBALAS => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                \App\Models\ItHelpRequest::STATUS_SELESAI => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            ][$permintaan->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

            $emailTerdaftar = $permintaan->user?->email;
        @endphp

        {{-- Halaman ini hanya menampilkan IT Help Request (it_help_requests,
             di-resolve lewat route model binding); reset password
             (password_reset_requests) tidak pernah sampai ke sini. --}}
        <div class="panel-card overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">

            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <x-lucide-life-buoy class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 sm:text-base">Permintaan Bantuan · Hubungi Tim IT</h2>
                        <p class="mt-0.5 text-xs text-slate-400">#{{ $permintaan->id }} · {{ \App\Support\Waktu::lengkap($permintaan->created_at) ?? '-' }}</p>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusBadge }}">
                    {{ $permintaan->statusLabel() }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 sm:p-5">
                <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Pengguna</p>
                    <p class="mt-1 text-sm font-medium text-slate-800" title="{{ $permintaan->user?->name ?? 'Tidak terdaftar' }}">
                        {{ $permintaan->user?->name ?? 'Tidak terdaftar' }}
                    </p>
                    @if (! $permintaan->user)
                        <p class="mt-0.5 text-xs text-slate-400">NIP/NIS tidak cocok dengan akun manapun.</p>
                    @endif
                </div>

                <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Role</p>
                    <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $roleBadge }}">
                        {{ ucfirst($role) }}
                    </span>
                </div>

                <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $identifierLabel }}</p>
                    <p class="mt-1 font-mono text-sm font-medium text-slate-800">{{ $permintaan->identifier }}</p>
                </div>

                <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Email Pengirim</p>
                    <p class="mt-1 break-all text-sm font-medium text-slate-800">{{ $permintaan->email }}</p>
                </div>

                @if ($emailTerdaftar && $emailTerdaftar !== $permintaan->email)
                    <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Email Terdaftar</p>
                        <p class="mt-1 break-all text-sm font-medium text-slate-800">{{ $emailTerdaftar }}</p>
                    </div>
                @endif

                <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Pengajuan</p>
                    <p class="mt-1 text-sm font-medium text-slate-800">{{ \App\Support\Waktu::lengkap($permintaan->created_at) ?? '-' }}</p>
                </div>

                @if ($permintaan->ip_address)
                    <div class="rounded-xl bg-slate-50/70 px-4 py-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">IP Pengirim</p>
                        <p class="mt-1 font-mono text-sm font-medium text-slate-800">{{ $permintaan->ip_address }}</p>
                    </div>
                @endif
            </div>

            <div class="border-t border-slate-100 p-4 sm:p-5">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Isi Kendala</p>
                <p class="mt-2 whitespace-pre-line rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700">{{ $permintaan->pesan }}</p>
            </div>

            @if ($permintaan->replies->isNotEmpty())
                <div class="border-t border-slate-100 p-4 sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Riwayat Tindak Lanjut</p>
                    <div class="mt-2 space-y-3">
                        @foreach ($permintaan->replies as $balasan)
                            <div class="rounded-xl bg-slate-50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                        <span class="font-semibold text-slate-700">{{ $balasan->user?->name ?? 'Admin' }}</span>
                                        <span aria-hidden="true">&middot;</span>
                                        <span>{{ \App\Support\Waktu::lengkap($balasan->created_at) ?? '-' }}</span>
                                    </p>
                                    @if ($balasan->status_pengiriman)
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1',
                                            'bg-emerald-50 text-emerald-700 ring-emerald-200' => $balasan->status_pengiriman === 'terkirim',
                                            'bg-rose-50 text-rose-700 ring-rose-200' => $balasan->status_pengiriman !== 'terkirim',
                                        ])>
                                            {{ ucfirst($balasan->status_pengiriman) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $balasan->pesan }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <p class="text-xs text-slate-400">Tanggapan dikirim ke email pengaju dan tercatat sebagai riwayat tindak lanjut.</p>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($permintaan->status !== \App\Models\ItHelpRequest::STATUS_SELESAI)
                        <form id="form-selesai" method="POST"
                              action="{{ route('dashboard.admin.kendala-akun.selesai', $permintaan) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" id="btn-selesai"
                                    class="inline-flex w-fit items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100">
                                <x-lucide-circle-check-big class="h-4 w-4" />
                                Selesai
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('dashboard.admin.kendala-akun.balas.create', $permintaan) }}"
                       class="inline-flex w-fit items-center gap-1.5 rounded-xl bg-primary-60 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70">
                        <x-lucide-reply class="h-4 w-4" />
                        Balas
                    </a>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
    document.getElementById('form-selesai')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const result = await Swal.fire({
            icon: 'question',
            title: 'Tandai Selesai?',
            text: 'Status pengajuan akan diubah menjadi Selesai dan email pemberitahuan "kendala sudah diperbaiki" akan dikirim ke pengaju. Tindakan ini tidak dapat dibatalkan secara otomatis.',
            showCancelButton: true,
            confirmButtonText: 'Ya, Tandai Selesai',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#059669',
        });

        if (! result.isConfirmed) return;

        const btn   = document.getElementById('btn-selesai');
        btn.disabled = true;
        btn.innerHTML = '<svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg> Memproses…';

        try {
            const res  = await fetch(this.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-HTTP-Method-Override': 'PATCH',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(this),
            });

            const data = await res.json().catch(() => null);

            if (! res.ok || data?.status !== 'success') {
                throw new Error(data?.message || 'Terjadi kesalahan saat memperbarui status.');
            }

            await Swal.fire({
                icon: 'success',
                title: 'Pengajuan Selesai',
                text: data.message || 'Status berhasil diubah menjadi Selesai.',
                showConfirmButton: false,
                timer: 1400,
                timerProgressBar: true,
            });

            window.location.reload();
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Selesai';
            Swal.fire({ icon: 'error', title: 'Gagal', text: err.message, confirmButtonColor: '#0F3D73' });
        }
    });
</script>
@endpush
@endsection
