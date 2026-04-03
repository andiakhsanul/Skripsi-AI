<!DOCTYPE html>
<html class="light" lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="@yield('description', 'Portal Sistem Informasi KIP-Kuliah Universitas Airlangga')" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title', 'KIP-K UNAIR')</title>

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

    {{-- Custom Common Styles --}}
    <style>
        /* Material Symbols */
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        /* Essential Utility Classes */
        .bg-glass {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .text-shadow-premium {
            text-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .ring-error {
            --tw-ring-color: #ef4444;
            box-shadow: 0 0 0 2px #ef4444;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        
        .animate-slide-in {
            animation: slideIn 0.3s ease forwards;
        }
    </style>
    
    @stack('styles')
</head>

<body class="bg-background font-body text-on-background min-h-screen selection:bg-primary-container selection:text-primary overflow-x-hidden">
    
    @yield('content')

    @stack('scripts')
</body>
</html>
