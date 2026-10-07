---
name: tmerp-stack-rules
description: Aturan proyek TMERP yang MENGALAHKAN saran generik skill Boost (livewire-development, tailwindcss-development, laravel-best-practices, testing-best-practices). Pakai bersamaan setiap kali menulis/mengubah kode Laravel 13, Livewire 4, Tailwind 4, Blade, model, migration, atau test di repo ini.
---

# TMERP — override untuk skill Boost

**Urutan prioritas skill (user 2026-10-07):** 1) `devlib-oo` (utama: pewarisan / object oriented DevLib) → 2) skill TMERP (file ini, `tmerp-screen`, `tmerp-ui-mockup`) → 3) skill generik (Boost, plugin `superpowers-laravel`, dst). Bertentangan = yang bernomor lebih kecil menang.

Versi terpasang: Laravel 13.29, Livewire 4.4, Tailwind CSS 4.3 (Vite), Filament 5, PHPUnit 12. Skill Boost benar untuk API versinya; bila bertentangan dengan daftar di bawah, **daftar ini yang dipakai** (sumber: CLAUDE.md).

## Livewire 4
- Nama file komponen **ASCII saja, TANPA prefix `⚡`** (Boost/`make:livewire` default memakai `⚡` — rename atau buat manual). Halaman = SFC di `resources/views/pages/<modul>/<nama>.blade.php`, route `Route::livewire('/uri', 'pages::modul.nama')` di grup `module:<KODE>`.
- Layar baru: `php artisan make:tm-screen master|grid|form ...` (skill `tmerp-screen`), bukan `make:livewire` kosong.
- Jangan `<select wire:model>` polos (bug morph) → `x-form.select liveSync`, `x-form.toggle-group`, atau `x-form.searchable-select`.
- Jangan `@php` inline di dalam `@if/@foreach`; `@php(...)` inline wajib penutup. Jangan tulis tag komponen berkurung sudut di komentar.
- Jangan gate konten yang diubah Livewire dengan `x-if`/`x-show` state lokal Alpine (stale content).
- Pakai komponen `<x-*>` dari `resources/views/components/_manifest.yaml`, bukan markup input mentah.
- **Tambahan user 2026-10-07 (Laravel 13 / Livewire 4):** pakai PHP Attributes (`#[Title]`, `#[Lazy]`, `#[Validate]`, `#[Middleware]`/`#[Authorize]` bila perlu); `wire:model` dengan modifier modern (`.live`, `.blur`, `.debounce`), `wire:navigate` untuk pindah halaman. Bagian spesifikasi itu yang **tidak dipakai di sini**: Pest dan Larastan tidak terpasang — test tetap **PHPUnit 12**, gaya kode **Pint** (`vendor/bin/pint --dirty`); file komponen tetap tanpa `⚡`; route tetap `Route::livewire('/uri', 'pages::modul.nama')`. Layar baru tetap wajib berupa turunan objek DevLib (`devlib-oo`).

## Tailwind 4
- **Wajib `npm run build`** setelah menambah class baru (bukan `npm run dev`).
- `app.css` punya `.grid {grid-cols-4}` unlayered → `grid two`/`grid three`/flex, jangan `grid grid-cols-N`.
- Token: `navy #2c3e69`, `accent #2c7be5`, `muted #8a94a6` (`@theme` di app.css). Tema/dark mode dikelola data `tm2sys.sys_theme` — jangan menambah varian `dark:` sendiri.
- Jangan pakai class `container`.
- **v4 CSS-first (user 2026-10-07):** tanpa `tailwind.config.js/ts` dan tanpa `@tailwind ...` (cukup `@import "tailwindcss"`); token lewat `@theme`. Sintaks v4 wajib: `bg-black/50` (bukan `bg-opacity-*`/`text-opacity-*`), `shrink-0`/`grow` (bukan `flex-shrink-0`/`flex-grow`), `text-ellipsis` (bukan `overflow-ellipsis`). Jarak antar elemen pakai `gap-*`; komponen baru = komponen `x-ui.*`/`x-dl::*`, bukan `@apply`. Warna baru = token `@theme` yang ada (`accent`, `navy`, `muted`, `--color-field-*`), bukan hex/OKLCH literal; tanpa `@custom-variant dark` (dark mode = `sys_theme`). Properti CSS biasa `flex-shrink: 0` tetap sah.

## Laravel 13 / database
- Multi-DB: model wajib `$connection` (`tm2mst`, `tm2sdi`, ... bukan `mst`), `$table`, `$primaryKey` (`id_<entitas>`).
- **Dilarang** FK lintas-DB, `->join()`/`whereHas()` lintas koneksi. Pakai relasi Eloquent terpisah / `whereIn`; validasi `exists:tm2xxx.tabel,kolom`.
- Kolom: `activate` (bukan `is_active`), `stat_confirm/stat_cancel/stat_close`, `created_by/updated_by` berisi `login` (cek per tabel: sebagian legacy `update_by`).
- Logika bisnis di `app/Services/*`; migration mencerminkan dump `docs/tm2*.sql`.
- **Index / EXPLAIN / query lambat / deadlock / migration tabel besar / "isi tabel X"** → skill `tmerp-mysql` (playbook MySQL 8 InnoDB, user 2026-10-07).
- **Engine baru / tanda tangan method engine berubah**: daftarkan di `App\Support\Engines\EngineRegistry` (kegunaan, input, output,
  cara kerja, tabel; English) lalu `php artisan engine:catalog` -- dijaga `EngineCatalogTest`. Rumus harga ProCost tidak ditulis di sana.
- **Layar v2 hanya objek DevLib** (`x-dl::*` / `x-mp::*`): form kecil (tombol aksi, isian pop-up, saringan GET) = `x-dl::action-form`
  (token CSRF dipasang objeknya), bukan `<form>` mentah. Dijaga `DevlibOnlyRatchetTest`.
- Tombol & status selalu English satu kata (New/Save/Undo/Delete/Confirm/Cancel/Back; Draft/Confirmed/Cancelled/Closed).

## Multi-bahasa (LOCKED user 2026-10-01) -- modul baru OTOMATIS siap ID + EN
- **Database & semua formula ENGLISH** (kolom, kode, status, nilai lookup, konstanta, nama variabel rumus). Bahasa lain hanya tampilan & cetakan.
- **Label ditulis ENGLISH.** Komponen `x-dl::*` menerjemahkan sendiri label/title/placeholder/hint/message + label kolom/stat/tab
  (`DevlibComponent::translateTexts`) -- layar tidak memanggil apa pun. Teks lain: `t('English text')` (PHP/Blade) atau `@t('...')`;
  JS: `Devlib.h.t('...')`; kata milik objek DevLib: kamus `Devlib\Lang` (kunci [ID, EN]). Nilai lookup dari DB: `t($value, '<db>.<tabel>.<kolom>')`.
- Teks English baru **tercatat otomatis** di `tm2sys.sys_dictionary`; terjemahan diisi di Setup > Translation Dictionary. Jangan insert manual,
  jangan tulis label Indonesia (dijaga `MultiLanguageRatchetTest`), jangan `Translator::t` (kamus kunci v1) di layar baru.
- **Nilai DATA di prop teks `x-dl::*` dibungkus `raw()`** (no. dokumen, nama customer / product, angka, tanggal):
  `:title="'Customer Order · '.raw($o->co_no)" :subtitle="raw($customer.' · '.$status)"`. Label di sekitarnya tetap English dan
  diterjemahkan komponen (tanpa `t()`); nilainya tidak diterjemahkan dan tidak tercatat di kamus. Kalimat yang sudah jadi
  (`str_replace(':n', $n, t('...'))`) = `raw()` seluruhnya. Dijaga `MultiLanguageRatchetTest` (`data_in_text_prop`).
- **Angka/tanggal/jam lewat objek DevLib**: `Devlib\Locale::number/decimal/money/d/dt`, `NumberValue`, `DateValue`, `TimeValue`, `DateTimeValue`,
  `TextValue` (JS `fmt`, `DlNumber`, `DlDate`, `DlTime`, `DlDateTime`, `DlText`). Dilarang `number_format(.., ',', '.')`, `->format('d-M-Y')`,
  `toLocaleString('id-ID')`. Form mengirim angka kanonik `1234.5` (isian angka DevLib bertanda `data-dl-num`); server baca `Locale::input()`.
- Cetak: `x-dl::doc-share` sudah punya pilihan Bahasa cetak (`?print_lang`) -- label cetak juga English + kamus.

## Test
- PHPUnit (bukan Pest). Test berjalan ke MySQL `tm2*` sungguhan, **tanpa `RefreshDatabase`** — bersihkan data sendiri di `tearDown()` dan cek ulang di DB. Jangan pernah pakai `employee_id=1` / `sec_user id=1` sebagai data uji yang diubah.
- Verifikasi UI tanpa login browser: `Livewire::test('pages::modul.nama')` via tinker.
