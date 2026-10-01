# BACKLOG 10 — MOBILE: APLIKASI ANDROID & iOS

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Lanjutan dari PWA yang sudah bisa di-install: menuju aplikasi mobile Android & iOS sungguhan.

---

## TITIK BERANGKAT — YANG SUDAH ADA (dicek 2026-09-22)

Aplikasi sudah bisa dipasang di HP sebagai **PWA**, bukan aplikasi native:

| Bagian | Keadaan |
|---|---|
| `manifest.json` | **dinamis** lewat route `pwa.manifest` di `routes/web.php` (bukan file statis) supaya nama ikut `Brand::name()` |
| Service worker | `public/sw.js` |
| Ikon | dibuat otomatis dari logo (Settings > Identifikasi) lewat `App\Support\Branding\PwaIcon`; tanpa logo = ikon bawaan navy "TM" |
| Layar yang sudah mobile-minded | `Scan Transaction (Mobile Camera)` dan `Absen GPS (Mobile)` (ada di `sys_app_menu`) |

## YANG BELUM

| # | Pekerjaan | Status |
|---|---|---|
| 10.1 | Putusan arah: lanjut perkuat PWA, atau bikin aplikasi native/hybrid (React Native / Flutter / Capacitor)? Ini menentukan semua pekerjaan di bawahnya | menunggu user |
| 10.2 | Kalau native: API layer sendiri — sekarang semua layar Livewire (server-rendered), tidak ada REST/GraphQL yang bisa dipanggil aplikasi mobile | **BELUM** |
| 10.3 | Autentikasi mobile (token/Sanctum) terpisah dari session web, termasuk alur lupa password 4-pertanyaan yang sudah ada | **BELUM** |
| 10.4 | Notifikasi push (Android/iOS), beda mekanisme dari notifikasi in-app yang sekarang | **BELUM** |
| 10.5 | Mode offline untuk layar lapangan (absen GPS, scan stok) — antre lalu sinkron saat online | **BELUM** |
| 10.7 | Daftar layar mana saja yang perlu versi mobile. Tidak semua layar ERP masuk akal di HP; perlu dipilih user | menunggu user |

Belum ada mockup mobile. Perlu arahan user (10.1 & 10.7) sebelum dirancang.

---
