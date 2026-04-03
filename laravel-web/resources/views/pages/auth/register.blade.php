@extends('layouts.portal')

@section('title', 'Daftar Akun | KIP-K UNAIR')
@section('description', 'Registrasi akun mahasiswa portal KIP-K Universitas Airlangga')

@section('content')
<main class="flex min-h-screen flex-col md:flex-row">
    <section class="relative hidden w-1/2 overflow-hidden bg-primary md:flex md:items-center md:justify-center md:p-12">
        <div class="absolute inset-0 opacity-40">
            <img
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuABUrVESiLyA3iUfI9BEQVWsDtUa-Ame1hbGS6msYH0PS2ld4ZrFvWsY3JIi2ETJQQ6Bzk6DY9ZZcJH01Wtr_U4Y_ovWSAPbGFsd96k98udtbSKsJdbMWDurmC_x1BywkhIFct6uj9XdBaFtW7Z9jMDxqnGnNpMw7KaCIIY9qMWzjkZjaqRYgQ5lclx7zjflt6llv4OWFUeJlVJkxy0nm-nXAC1rNcTEqkwH-jahfa082rwD3CFaU_-xOlm8C1eoGi3XsGZR_KB2kw"
                alt="Kampus Universitas Airlangga"
                class="h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-primary via-primary/70 to-transparent"></div>
        </div>

        <div class="relative z-10 max-w-lg text-white">
            <div class="mb-8 inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/10 px-4 py-2 backdrop-blur-md">
                <span class="material-symbols-outlined text-secondary" style="font-variation-settings: 'FILL' 1;">school</span>
                <span class="text-xs font-semibold uppercase tracking-widest">Pendaftaran Akun KIP-K</span>
            </div>
            <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-[1.1]">
                Mulai langkah menuju <span class="text-secondary-fixed">masa depan.</span>
            </h1>
            <p class="mt-6 text-lg font-light leading-relaxed text-primary-fixed/80">
                Buat akun mahasiswa untuk memantau pengajuan, melihat status verifikasi, dan mengelola dokumen KIP-K dalam satu portal.
            </p>
            <div class="mt-8 rounded-xl border border-white/10 bg-white/5 p-4">
                <div class="flex items-start gap-4">
                    <span class="material-symbols-outlined text-secondary">verified</span>
                    <div>
                        <h3 class="font-semibold">Registrasi khusus mahasiswa</h3>
                        <p class="text-sm text-primary-fixed/70">Akun admin tidak dibuat dari halaman publik ini.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="flex w-full items-center justify-center bg-surface p-6 md:w-1/2 md:p-12 lg:p-24">
        <div class="w-full max-w-md">
            <div class="mb-10 text-center md:text-left">
                <h2 class="font-headline text-3xl font-extrabold tracking-tighter text-primary">KIP-K UNAIR</h2>
                <p class="mt-2 text-on-surface-variant">Buat akun untuk memulai akses portal mahasiswa.</p>
            </div>

            @if($errors->any())
                <div class="mb-6 rounded-xl border border-error/10 bg-error-container px-4 py-3 text-sm text-on-error-container">
                    <p class="font-bold">Registrasi belum berhasil</p>
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <label class="ml-1 block text-sm font-semibold text-on-surface-variant" for="name">Nama Lengkap</label>
                    <div class="group relative">
                        <span class="material-symbols-outlined absolute inset-y-0 left-4 flex items-center text-slate-400 group-focus-within:text-primary">person</span>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            class="w-full rounded-xl bg-surface-container py-3.5 pl-12 pr-4 text-on-surface shadow-sm outline-none transition-all focus:ring-2 focus:ring-primary/20"
                            placeholder="Masukkan nama sesuai KTP"
                            required
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="ml-1 block text-sm font-semibold text-on-surface-variant" for="email">Alamat Email</label>
                    <div class="group relative">
                        <span class="material-symbols-outlined absolute inset-y-0 left-4 flex items-center text-slate-400 group-focus-within:text-primary">mail</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            class="w-full rounded-xl bg-surface-container py-3.5 pl-12 pr-4 text-on-surface shadow-sm outline-none transition-all focus:ring-2 focus:ring-primary/20"
                            placeholder="nama@student.unair.ac.id"
                            required
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="ml-1 block text-sm font-semibold text-on-surface-variant" for="password">Kata Sandi</label>
                    <div class="group relative">
                        <span class="material-symbols-outlined absolute inset-y-0 left-4 flex items-center text-slate-400 group-focus-within:text-primary">lock</span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="w-full rounded-xl bg-surface-container py-3.5 pl-12 pr-4 text-on-surface shadow-sm outline-none transition-all focus:ring-2 focus:ring-primary/20"
                            placeholder="Minimal 8 karakter"
                            required
                        />
                    </div>
                    <p class="ml-1 text-[11px] font-medium uppercase tracking-wider text-on-surface-variant">Gunakan kombinasi huruf dan angka.</p>
                </div>

                <div class="space-y-2">
                    <label class="ml-1 block text-sm font-semibold text-on-surface-variant" for="password_confirmation">Konfirmasi Kata Sandi</label>
                    <div class="group relative">
                        <span class="material-symbols-outlined absolute inset-y-0 left-4 flex items-center text-slate-400 group-focus-within:text-primary">verified_user</span>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="w-full rounded-xl bg-surface-container py-3.5 pl-12 pr-4 text-on-surface shadow-sm outline-none transition-all focus:ring-2 focus:ring-primary/20"
                            placeholder="Ulangi kata sandi"
                            required
                        />
                    </div>
                </div>

                <button type="submit" class="flex w-full items-center justify-center gap-3 rounded-xl bg-primary px-6 py-4 text-base font-bold text-on-primary shadow-lg shadow-primary/20 transition-all hover:brightness-110 active:scale-[0.98]">
                    <span>Daftar Sekarang</span>
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </button>
            </form>

            <div class="pt-8 text-center">
                <p class="text-sm font-medium text-on-surface-variant">
                    Sudah punya akun?
                    <a href="{{ route('login') }}" class="ml-1 font-bold text-primary hover:underline">Masuk di sini</a>
                </p>
            </div>
        </div>
    </section>
</main>
@endsection
