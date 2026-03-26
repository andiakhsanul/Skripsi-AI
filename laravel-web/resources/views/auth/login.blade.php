<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Login Portal Sistem Informasi KIP-Kuliah Universitas Airlangga" />
    <title>Login Portal | KIP-K UNAIR</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    {{-- Vite Assets --}}
    @vite(['resources/css/app.css', 'resources/css/auth/login.css', 'resources/js/auth/login.js'])

    {{-- CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}" />
</head>
<body class="auth-body">

<main class="auth-layout">

    {{-- ===================================================== --}}
    {{-- LEFT PANEL: Hero / Branding (Desktop Only)            --}}
    {{-- ===================================================== --}}
    <section class="hero-panel" aria-hidden="true">
        {{-- Background Image --}}
        <div class="hero-bg">
            <img
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuDs4BumqwsE3jjWHcG13MHeDnIvkkqDtcU0YZJTeOgm9AMriGbsm71Ep-p6iYGvOtW_CmEiW9idyOqFxe1IsLdYT9kSQH9PCOZsguHjlbZLiqeSVN4St98D57qZ0WT5P3kHOUn7dxpRnpeLzI4N087XGIO_uYl4KtC0ReWRBtVY_-6JH3qhOCItreB4A1QXiQYu1yjIb_K_hiBSB1pRurSb7zRrgJGsEhUJkBFv90eIoqOTfQWOvZfW0m5ZdAIcZFmP7LTDbRqT4-4"
                alt="Kampus Universitas Airlangga"
                class="hero-img"
            />
            <div class="hero-overlay"></div>
        </div>

        {{-- Floating Brand Badge (Glassmorphism) --}}
        <div class="hero-badge">
            <div class="hero-badge__icon">
                <span class="material-symbols-outlined">school</span>
            </div>
            <div>
                <div class="hero-badge__title">KIP-K UNAIR</div>
                <div class="hero-badge__subtitle">Academic Affairs</div>
            </div>
        </div>

        {{-- Bottom Narrative --}}
        <div class="hero-content">
            <div class="hero-content__body">
                <span class="hero-tag">Official Portal</span>
                <h1 class="hero-headline">Wujudkan Mimpi Bersama UNAIR</h1>
                <p class="hero-desc">
                    Sistem Informasi Manajemen KIP-Kuliah Universitas Airlangga.
                    Melayani masa depan dengan integritas dan transparansi.
                </p>
            </div>
            <div class="hero-divider">
                <div class="hero-divider__line"></div>
                <span class="hero-divider__text">Excellence with Morality</span>
            </div>
        </div>
    </section>

    {{-- ===================================================== --}}
    {{-- RIGHT PANEL: Login Form                               --}}
    {{-- ===================================================== --}}
    <section class="form-panel">
        <div class="form-wrapper">

            {{-- Mobile Branding --}}
            <div class="mobile-brand">
                <div class="mobile-brand__icon">
                    <span class="material-symbols-outlined">account_balance</span>
                </div>
                <h2 class="mobile-brand__title">KIP-K UNAIR</h2>
                <p class="mobile-brand__subtitle">Sistem Manajemen Beasiswa</p>
            </div>

            {{-- Desktop Form Header --}}
            <div class="form-header desktop-only">
                <h2 class="form-header__title">Selamat Datang</h2>
                <p class="form-header__subtitle">Masuk ke akun Anda untuk melanjutkan akses portal.</p>
            </div>

            {{-- Role Switcher --}}
            <div class="role-switcher" role="tablist" aria-label="Pilih Peran">
                <button
                    class="role-btn role-btn--active"
                    id="role-mahasiswa"
                    role="tab"
                    aria-selected="true"
                    data-role="mahasiswa"
                    data-placeholder="nama@student.unair.ac.id"
                >
                    Mahasiswa
                </button>
                <button
                    class="role-btn"
                    id="role-admin"
                    role="tab"
                    aria-selected="false"
                    data-role="admin"
                    data-placeholder="admin@unair.ac.id"
                >
                    Administrator
                </button>
            </div>

            {{-- Error Alert (shown on server-side validation failure OR via JS) --}}
            <div
                class="error-alert {{ $errors->any() ? '' : 'hidden' }}"
                id="error-alert"
                role="alert"
                aria-live="assertive"
            >
                <span class="material-symbols-outlined error-alert__icon">report</span>
                <div class="error-alert__body">
                    <p class="error-alert__title">Login Gagal</p>
                    <p class="error-alert__desc">
                        {{ $errors->first('email') ?: 'Email atau kata sandi yang Anda masukkan salah. Silakan coba lagi.' }}
                    </p>
                </div>
                <button class="error-alert__close" id="error-close" aria-label="Tutup pesan error">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Login Form --}}
            <form id="login-form" method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                {{-- Email Field --}}
                <div class="field-group">
                    <label class="field-label" for="email">Alamat Email</label>
                    <div class="field-input-wrap" id="email-wrap">
                        <span class="material-symbols-outlined field-icon">mail</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="field-input"
                            placeholder="nama@student.unair.ac.id"
                            autocomplete="email"
                            required
                            value="{{ old('email') }}"
                        />
                    </div>
                    @error('email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password Field --}}
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="password">Kata Sandi</label>
                        <a href="#" class="field-forgot">Lupa sandi?</a>
                    </div>
                    <div class="field-input-wrap">
                        <span class="material-symbols-outlined field-icon">lock</span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="field-input field-input--padded-right"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        />
                        <button
                            type="button"
                            class="field-toggle-pw"
                            id="toggle-pw"
                            aria-label="Tampilkan / sembunyikan kata sandi"
                        >
                            <span class="material-symbols-outlined" id="pw-icon">visibility</span>
                        </button>
                    </div>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember Me --}}
                <div class="remember-row">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        class="remember-checkbox"
                    />
                    <label for="remember" class="remember-label">Biarkan saya tetap masuk</label>
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-submit" id="submit-btn">
                    <span id="btn-text">Masuk Sekarang</span>
                    <span class="btn-spinner hidden" id="btn-spinner" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                </button>

            </form>

            {{-- Footer Links --}}
            <footer class="form-footer">
                <p class="form-footer__register">
                    Belum punya akun?
                    <a href="#" class="form-footer__link">Daftar KIP-K</a>
                </p>
                <nav class="form-footer__nav" aria-label="Footer navigation">
                    <a href="#" class="form-footer__nav-link">Privacy Policy</a>
                    <a href="#" class="form-footer__nav-link">Help Center</a>
                    <a href="#" class="form-footer__nav-link">Contact</a>
                </nav>
            </footer>

        </div>
    </section>

</main>

</body>
</html>
