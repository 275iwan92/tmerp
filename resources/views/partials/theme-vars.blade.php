{{-- Override CSS var runtime dari sys_theme aktif (queue.md #8) -- ditaruh
     SETELAH <link> app.css (@vite) supaya menang di cascade (sama spesifisitas,
     urutan sumber menentukan). Tailwind v4 @theme MEMANG emit var(--color-navy)
     di tiap utility (bukan hex literal), jadi override di sini cukup, tak perlu
     ubah satupun class bg-navy/text-accent di seluruh app. Cuma variabel dgn
     pemetaan 1:1 ke 2 kolom sys_theme yang di-override -- shade turunan shell
     (--side-2, --sh-violet, dst) sengaja dibiarkan statis, lihat queue.md #8.
     3 sistem variabel warna independen di app.css ke-cover di sini: .shell
     (post-login), .authpage (login/register/forgot-password), .secpage
     (security) -- Finance/HRM/Master/Inquiry pakai --color-navy/--color-accent
     global langsung (bg-navy dsb), otomatis ikut tanpa perlu blok terpisah. --}}
@php $__theme = \App\Support\ActiveTheme::resolve(); @endphp
<style>
    :root {
        --color-navy: {{ $__theme->sidebar_color }};
        --color-accent: {{ $__theme->primary_color }};
    }
    .shell {
        --side: {{ $__theme->sidebar_color }};
        --topbar: {{ $__theme->sidebar_color }};
        --sh-accent: {{ $__theme->primary_color }};
    }
    .authpage {
        --ap-navy: {{ $__theme->sidebar_color }};
        --ap-navy-dark: {{ $__theme->sidebar_color }};
        --ap-blue: {{ $__theme->primary_color }};
        --ap-blue-dark: {{ $__theme->primary_color }};
    }
    .secpage {
        --sp-navy: {{ $__theme->sidebar_color }};
        --sp-blue: {{ $__theme->primary_color }};
        --sp-blue-dark: {{ $__theme->primary_color }};
    }
</style>
