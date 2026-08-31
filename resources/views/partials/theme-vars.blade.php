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
     global langsung (bg-navy dsb), otomatis ikut tanpa perlu blok terpisah.

     mode terang/gelap (2026-08-31, sys_theme.mode): sidebar/topbar SENGAJA
     tetap gelap di kedua mode (teks .mega-item/.um-item hardcode terang di
     app.css, pastel di sidebar bikin teks itu tak terbaca) -- yang berubah
     cuma token area KONTEN .shell (--content-bg dst). Ini baru cover chrome
     .shell (halaman pasca-login + isi tab non-iframe) -- layar transaksi yg
     dimuat via <iframe> (inquiry, stock-in, dst) pakai token global
     --color-navy/--color-accent yg SUDAH ikut, tapi belum tentu tiap kelas
     Tailwind di dalamnya sadar dark-mode; itu scope terpisah, belum digarap. --}}
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
        @if ($__theme->mode === 'dark')
            --content-bg: {{ $__theme->content_bg_color ?: '#12162a' }};
            --tabbar: #171c33;
            --sh-border: #2a3157;
            --sh-surface: #1b2140;
            --sh-ink: #c7cee8;
            --sh-ink-strong: #eef1ff;
            --sh-muted: #7680a6;
        @else
            --content-bg: {{ $__theme->content_bg_color ?: '#eef1f6' }};
        @endif
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
