<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="Login Portal Sistem Informasi KIP-Kuliah Universitas Airlangga" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>Login Portal | KIP-K UNAIR</title>

    {{-- Tailwind CSS via CDN + Plugins --}}
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

    {{-- Tailwind Config: Material Design 3 Color System --}}
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary":                    "#135bec",
                        "on-primary":                 "#ffffff",
                        "primary-container":          "#dbe1ff",
                        "on-primary-container":       "#00174c",
                        "primary-fixed":              "#dbe1ff",
                        "primary-fixed-dim":          "#b4c5ff",
                        "on-primary-fixed":           "#00174c",
                        "on-primary-fixed-variant":   "#003daa",
                        "inverse-primary":            "#b4c5ff",
                        "surface-tint":               "#135bec",
                        "secondary":                  "#eab308",
                        "on-secondary":               "#ffffff",
                        "secondary-container":        "#fef08a",
                        "on-secondary-container":     "#713f12",
                        "secondary-fixed":            "#fef9c3",
                        "secondary-fixed-dim":        "#facc15",
                        "on-secondary-fixed":         "#422006",
                        "on-secondary-fixed-variant": "#713f12",
                        "tertiary":                   "#0f1722",
                        "on-tertiary":                "#ffffff",
                        "tertiary-container":         "#334155",
                        "tertiary-fixed":             "#cbd5e1",
                        "on-tertiary-fixed":          "#0f172a",
                        "on-tertiary-fixed-variant":  "#334155",
                        "error":                      "#ef4444",
                        "on-error":                   "#ffffff",
                        "error-container":            "#fee2e2",
                        "on-error-container":         "#7f1d1d",
                        "surface":                    "#ffffff",
                        "on-surface":                 "#0f172a",
                        "surface-variant":            "#f1f5f9",
                        "on-surface-variant":         "#475569",
                        "surface-dim":                "#f6f6f8",
                        "surface-bright":             "#ffffff",
                        "surface-container":          "#f1f5f9",
                        "surface-container-low":      "#f8fafc",
                        "surface-container-high":     "#e2e8f0",
                        "surface-container-highest":  "#cbd5e1",
                        "surface-container-lowest":   "#ffffff",
                        "background":                 "#f6f6f8",
                        "on-background":              "#0f172a",
                        "outline":                    "#94a3b8",
                        "outline-variant":            "#e2e8f0",
                        "inverse-surface":            "#1e293b",
                        "inverse-on-surface":         "#f8fafc"
                    },
                    fontFamily: {
                        headline: ["Lexend"],
                        body:     ["Lexend"],
                        label:    ["Lexend"]
                    },
                    borderRadius: {
                        DEFAULT: "0.25rem",
                        lg:     "0.5rem",
                        xl:     "0.75rem",
                        full:   "9999px"
                    }
                }
            }
        }
    </script>

    {{-- Custom Styles --}}
    <style>
        /* Material Symbols */
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        /* Glassmorphism */
        .bg-glass {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Text Shadow */
        .text-shadow-premium {
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* Error ring */
        .ring-error {
            --tw-ring-color: #ef4444;
            box-shadow: 0 0 0 2px #ef4444;
        }

        /* Slide-in animation */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease forwards;
        }
    </style>
</head>

<body class="bg-background font-body text-on-background min-h-screen selection:bg-primary-container selection:text-primary overflow-x-hidden">
<main class="flex min-h-screen">

    {{-- ═══════════════════════════════════════════════════════════
         LEFT PANEL — Hero / Branding (Desktop only, hidden < lg)
         ═══════════════════════════════════════════════════════════ --}}
    <section class="hidden lg:flex lg:w-1/2 relative overflow-hidden items-end p-16">
        {{-- Background Image --}}
        <div class="absolute inset-0 z-0">
            <img
                src="https://unair.ac.id/wp-content/uploads/2024/01/kampus-b-768x432.webp"
                alt="Universitas Airlangga Campus"
                class="w-full h-full object-cover grayscale-[20%] brightness-[60%]"
            />
            {{-- Gradient overlays --}}
            <div class="absolute inset-0 bg-gradient-to-t from-primary/80 via-transparent to-transparent opacity-90"></div>
            <div class="absolute inset-0 bg-primary/10 mix-blend-multiply"></div>
        </div>

        {{-- Bottom Narrative --}}
        <div class="relative z-10 w-full max-w-xl">
            <div class="mb-8">
                <span class="inline-block px-3 py-1 mb-4 bg-secondary text-on-secondary-fixed text-[10px] uppercase tracking-widest font-bold rounded-sm shadow-lg">
                    Official Portal
                </span>
                <h1 class="font-headline text-5xl font-extrabold text-white tracking-tighter leading-tight text-shadow-premium">
                    Wujudkan Mimpi Bersama UNAIR
                </h1>
                <p class="mt-6 text-white/90 text-lg font-medium leading-relaxed max-w-md">
                    Sistem Informasi Manajemen KIP-Kuliah Universitas Airlangga.
                    Melayani masa depan dengan integritas dan transparansi.
                </p>
            </div>
            <div class="flex gap-4 items-center">
                <div class="h-[2px] w-12 bg-secondary"></div>
                <span class="text-white/70 font-label text-sm uppercase tracking-widest">Excellence with Morality</span>
            </div>
        </div>

        {{-- Floating Brand Badge (Glassmorphism) --}}
        <div class="absolute top-12 left-12 bg-glass border border-white/30 px-6 py-4 rounded-xl flex items-center gap-3">
            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-lg">
                <span class="material-symbols-outlined text-primary text-2xl">school</span>
            </div>
            <div>
                <div class="font-headline font-black text-white text-lg tracking-tighter">KIP-K UNAIR</div>
                <div class="text-[10px] text-white/80 uppercase tracking-widest font-bold">Academic Affairs</div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════
         RIGHT PANEL — Login Form
         ═══════════════════════════════════════════════════════════ --}}
    <section class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 md:p-24 bg-surface lg:bg-background">
        <div class="w-full max-w-md space-y-8">

            {{-- Mobile Branding (visible < lg) --}}
            <div class="lg:hidden flex flex-col items-center text-center mb-10">
                <div class="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center shadow-primary/20 shadow-xl mb-4">
                    <span class="material-symbols-outlined text-white text-3xl">account_balance</span>
                </div>
                <h2 class="font-headline text-2xl font-extrabold text-on-background tracking-tighter">KIP-K UNAIR</h2>
                <p class="text-on-surface-variant text-sm mt-1">Sistem Manajemen Beasiswa</p>
            </div>

            {{-- Desktop Form Header --}}
            <div class="hidden lg:block space-y-2">
                <h2 class="text-3xl font-extrabold text-on-background tracking-tight font-headline">Selamat Datang</h2>
                <p class="text-on-surface-variant text-sm">Masuk ke akun Anda untuk melanjutkan akses portal.</p>
            </div>

            {{-- Role Switcher --}}
            <div class="bg-surface-container p-1 rounded-xl flex items-center shadow-sm">
                <button
                    type="button"
                    class="flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-300 bg-surface text-primary shadow-sm"
                    id="role-mahasiswa"
                >Mahasiswa</button>
                <button
                    type="button"
                    class="flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold text-on-surface-variant hover:text-on-surface transition-all duration-300"
                    id="role-admin"
                >Administrator</button>
            </div>

            {{-- Error Alert --}}
            @if($errors->any())
            <div class="flex items-start gap-3 p-4 bg-error-container text-on-error-container rounded-xl border-t-2 border-error animate-slide-in" id="error-alert">
                <span class="material-symbols-outlined text-error">report</span>
                <div class="text-sm">
                    <p class="font-bold">Login Gagal</p>
                    <p class="opacity-90">{{ $errors->first('email') ?: 'Email atau kata sandi yang Anda masukkan salah.' }}</p>
                </div>
                <button type="button" class="ml-auto opacity-60 hover:opacity-100" id="error-close">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
            @endif

            {{-- Login Form --}}
            <form class="space-y-6" id="login-form" method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant px-1" for="email">Alamat Email</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">mail</span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            class="w-full pl-12 pr-4 py-4 bg-surface border-none ring-1 ring-outline/50 focus:ring-2 focus:ring-primary rounded-xl text-on-background placeholder:text-outline/70 transition-all outline-none shadow-sm"
                            placeholder="nama@student.unair.ac.id"
                            required
                            autocomplete="email"
                        />
                    </div>
                </div>

                {{-- Password --}}
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant" for="password">Kata Sandi</label>
                        <a class="text-xs font-bold text-primary hover:text-primary/80 transition-colors" href="#">Lupa sandi?</a>
                    </div>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">lock</span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="w-full pl-12 pr-12 py-4 bg-surface border-none ring-1 ring-outline/50 focus:ring-2 focus:ring-primary rounded-xl text-on-background placeholder:text-outline/70 transition-all outline-none shadow-sm"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        />
                        <button type="button" id="toggle-pw" class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface transition-colors">
                            <span class="material-symbols-outlined" id="pw-icon">visibility</span>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center gap-3 px-1">
                    <input id="remember" name="remember" type="checkbox" class="w-5 h-5 rounded border-outline/50 text-primary focus:ring-primary/20 cursor-pointer" />
                    <label class="text-sm text-on-surface-variant cursor-pointer select-none" for="remember">Biarkan saya tetap masuk</label>
                </div>

                {{-- Submit Button --}}
                <button
                    type="submit"
                    id="submit-btn"
                    class="w-full py-4 px-6 bg-primary text-white font-bold text-base rounded-xl shadow-xl shadow-primary/30 hover:shadow-primary/50 hover:brightness-110 active:scale-[0.97] transition-all duration-200 flex items-center justify-center gap-3"
                >
                    <span id="btn-text">Masuk Sekarang</span>
                    <div class="hidden" id="btn-spinner">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </button>
            </form>

            {{-- Footer Links --}}
            <div class="pt-8 border-t border-outline-variant text-center space-y-4">
                <p class="text-sm text-on-surface-variant">
                    Belum punya akun? <a class="text-primary font-bold hover:underline underline-offset-4" href="#">Daftar KIP-K</a>
                </p>
                <div class="flex justify-center gap-6">
                    <a class="text-[10px] uppercase tracking-widest font-bold text-outline hover:text-primary transition-colors" href="#">Privacy Policy</a>
                    <a class="text-[10px] uppercase tracking-widest font-bold text-outline hover:text-primary transition-colors" href="#">Help Center</a>
                    <a class="text-[10px] uppercase tracking-widest font-bold text-outline hover:text-primary transition-colors" href="#">Contact</a>
                </div>
            </div>

        </div>
    </section>

</main>

{{-- ═══════════════════════════════════════════════════════════
     SCRIPTS — Role Switcher, Password Toggle, Loading State
     ═══════════════════════════════════════════════════════════ --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // ─── Elements ───────────────────────────────────────
        const btnMhs      = document.getElementById('role-mahasiswa');
        const btnAdm      = document.getElementById('role-admin');
        const emailInput  = document.getElementById('email');
        const pwInput     = document.getElementById('password');
        const togglePwBtn = document.getElementById('toggle-pw');
        const pwIcon      = document.getElementById('pw-icon');
        const loginForm   = document.getElementById('login-form');
        const submitBtn   = document.getElementById('submit-btn');
        const btnText     = document.getElementById('btn-text');
        const btnSpinner  = document.getElementById('btn-spinner');
        const errorAlert  = document.getElementById('error-alert');
        const errorClose  = document.getElementById('error-close');

        // ─── Class sets for role switcher ────────────────────
        const ACTIVE   = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-300 bg-surface text-primary shadow-sm';
        const INACTIVE = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold text-on-surface-variant hover:text-on-surface transition-all duration-300';

        // ─── Role Switcher ─────────────────────────────────
        if (btnMhs && btnAdm && emailInput) {
            btnMhs.addEventListener('click', () => {
                btnMhs.className = ACTIVE;
                btnAdm.className = INACTIVE;
                emailInput.placeholder = 'nama@student.unair.ac.id';
            });
            btnAdm.addEventListener('click', () => {
                btnAdm.className = ACTIVE;
                btnMhs.className = INACTIVE;
                emailInput.placeholder = 'admin@unair.ac.id';
            });
        }

        // ─── Password Toggle ───────────────────────────────
        if (togglePwBtn && pwInput && pwIcon) {
            togglePwBtn.addEventListener('click', () => {
                const visible = pwInput.type === 'text';
                pwInput.type = visible ? 'password' : 'text';
                pwIcon.textContent = visible ? 'visibility' : 'visibility_off';
            });
        }

        // ─── Error Alert Dismiss ───────────────────────────
        if (errorClose && errorAlert) {
            errorClose.addEventListener('click', () => {
                errorAlert.style.display = 'none';
            });
        }

        // ─── Form Submit — Loading State ───────────────────
        if (loginForm) {
            loginForm.addEventListener('submit', () => {
                if (submitBtn) submitBtn.disabled = true;
                if (btnText)   btnText.classList.add('hidden');
                if (btnSpinner) btnSpinner.classList.remove('hidden');
            });
        }

        // ─── Reset error state on input ────────────────────
        [emailInput, pwInput].forEach(input => {
            if (!input) return;
            input.addEventListener('input', () => {
                input.classList.remove('ring-error');
                const icon = input.parentElement?.querySelector('.material-symbols-outlined');
                if (icon) icon.classList.remove('text-error');
            });
        });
    });
</script>

</body>
</html>
