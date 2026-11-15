@extends('layouts.dashboard')

@section('title', 'Balas Kendala Akun')
@section('subtitle', 'Dashboard Admin')

@section('content')
    <div class="mx-auto max-w-3xl">

        <a href="{{ route('dashboard.admin.kendala-akun.show', $permintaan) }}"
           class="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-800">
            <x-lucide-arrow-left class="h-4 w-4" />
            Kembali ke Detail Kendala Akun
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
        @endphp

        <div class="panel-card overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">

            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                        <x-lucide-reply class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900 sm:text-base">Balas Kendala Akun · Permintaan Bantuan IT</h2>
                        <p class="mt-0.5 text-xs text-slate-400">Pengajuan #{{ $permintaan->id }} · {{ \App\Support\Waktu::lengkap($permintaan->created_at) ?? '-' }}</p>
                    </div>
                </div>
                <span class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusBadge }}">
                    {{ $permintaan->statusLabel() }}
                </span>
            </div>

            <div class="border-b border-slate-100 bg-slate-50/40 p-4 sm:p-5">
                <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Informasi Pengajuan</h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Pengguna</p>
                        <p class="mt-1 text-sm font-medium text-slate-800" title="{{ $permintaan->user?->name ?? 'Tidak terdaftar' }}">
                            {{ $permintaan->user?->name ?? 'Tidak terdaftar' }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Role</p>
                        <span class="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $roleBadge }}">
                            {{ ucfirst($role) }}
                        </span>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $identifierLabel }}</p>
                        <p class="mt-1 font-mono text-sm font-medium text-slate-800">{{ $permintaan->identifier }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-xs">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Email Pengirim</p>
                        <p class="mt-1 break-all text-sm font-medium text-slate-800">{{ $permintaan->email }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-white px-4 py-3 shadow-xs sm:col-span-2">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Pengajuan</p>
                        <p class="mt-1 text-sm font-medium text-slate-800">{{ \App\Support\Waktu::lengkap($permintaan->created_at) ?? '-' }}</p>
                    </div>
                </div>

                <div class="mt-3 rounded-xl border border-slate-100 bg-white p-4 shadow-xs">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Isi Kendala</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $permintaan->pesan }}</p>
                </div>
            </div>

            <form id="balas-form" class="p-4 sm:p-5">
                <h3 class="mb-4 text-sm font-semibold text-slate-900">Tulis Balasan Tanggapan</h3>

                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-slate-800">
                        Email Penerima <span class="text-xs font-normal text-slate-400">(Otomatis berdasarkan email pengaju)</span>
                    </label>
                    <div class="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3">
                        <span class="text-slate-400">
                            <x-lucide-mail class="h-4.5 w-4.5" />
                        </span>
                        <input type="text"
                               readonly
                               value="{{ $permintaan->email }}"
                               class="w-full bg-transparent text-sm font-medium text-slate-800 outline-none select-all" />
                    </div>
                </div>

                <div class="mb-4">
                    <label for="balas-pesan" class="mb-1.5 block text-sm font-semibold text-slate-800">
                        Isi Balasan / Solusi <span class="text-red-500">*</span>
                    </label>
                    <textarea id="balas-pesan"
                              name="pesan"
                              rows="6"
                              maxlength="2000"
                              placeholder="Tuliskan solusi, instruksi tindak lanjut, atau penjelasan kendala akun untuk pengaju di sini..."
                              class="w-full resize-y rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm leading-relaxed text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10"></textarea>
                    <div class="mt-1.5 flex items-center justify-between text-xs text-slate-400">
                        <span>Minimal 10 karakter, maksimal 2000 karakter.</span>
                        <span id="char-counter">0 / 2000</span>
                    </div>
                    <p id="balas-error" class="mt-1.5 hidden text-xs font-medium text-red-500"></p>
                </div>

                <div id="feedback-container" class="mb-4 hidden rounded-xl p-4 text-sm"></div>

                <div class="flex flex-col-reverse gap-2.5 pt-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('dashboard.admin.kendala-akun.show', $permintaan) }}"
                       class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">
                        Batal
                    </a>
                    <button type="submit"
                            id="btn-kirim-balasan"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-60 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-70 disabled:cursor-not-allowed disabled:opacity-70">
                        <x-lucide-send id="btn-icon" class="h-4 w-4" />
                        <span id="btn-text">Kirim Balasan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('balas-form');
            const pesanInput = document.getElementById('balas-pesan');
            const charCounter = document.getElementById('char-counter');
            const errorEl = document.getElementById('balas-error');
            const feedbackContainer = document.getElementById('feedback-container');
            const submitBtn = document.getElementById('btn-kirim-balasan');
            const btnIcon = document.getElementById('btn-icon');
            const btnText = document.getElementById('btn-text');

            const ENDPOINT = @json(route('dashboard.admin.kendala-akun.balas', $permintaan));
            const DETAIL_URL = @json(route('dashboard.admin.kendala-akun.show', $permintaan));
            const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

            pesanInput.addEventListener('input', () => {
                const len = pesanInput.value.length;
                charCounter.textContent = `${len} / 2000`;
                hideError();
            });

            const showError = (msg) => {
                errorEl.textContent = msg;
                errorEl.classList.remove('hidden');
                feedbackContainer.className = 'mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700';
                feedbackContainer.textContent = msg;
                feedbackContainer.classList.remove('hidden');
            };

            const hideError = () => {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
                feedbackContainer.classList.add('hidden');
            };

            const setLoading = (loading) => {
                submitBtn.disabled = loading;
                btnText.textContent = loading ? 'Mengirim Balasan...' : 'Kirim Balasan';
                btnIcon.classList.toggle('animate-spin', loading);
            };

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                hideError();

                const pesan = pesanInput.value.trim();

                if (pesan.length < 10) {
                    showError('Tuliskan tanggapan minimal 10 karakter.');
                    pesanInput.focus();
                    return;
                }

                setLoading(true);

                try {
                    const res = await fetch(ENDPOINT, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ pesan }),
                    });

                    const data = await res.json().catch(() => null);

                    if (! res.ok || ! data || data.status !== 'success') {
                        const errMsg = data?.errors?.pesan?.[0] || data?.message || 'Balasan gagal dikirim. Silakan coba lagi.';
                        showError(errMsg);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal Mengirim Balasan',
                                text: errMsg,
                                confirmButtonColor: '#0F3D73',
                            });
                        }
                        return;
                    }

                    feedbackContainer.className = 'mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700';
                    feedbackContainer.textContent = data.message || 'Balasan berhasil dikirim. Mengalihkan ke halaman detail...';
                    feedbackContainer.classList.remove('hidden');

                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Balasan Terkirim!',
                            text: data.message || 'Balasan telah berhasil dikirim ke pengaju dan status pengajuan diperbarui menjadi Sudah Dibalas.',
                            confirmButtonColor: '#0F3D73',
                            confirmButtonText: 'Kembali ke Detail',
                        });
                    }

                    window.location.href = data.redirect || DETAIL_URL;
                } catch (err) {
                    showError('Tidak dapat terhubung ke server. Silakan periksa koneksi Anda dan coba lagi.');
                } finally {
                    setLoading(false);
                }
            });
        });
    </script>
@endsection

