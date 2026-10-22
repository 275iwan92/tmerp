---
name: tmerp-stack-rules
description: Aturan proyek TMERP yang MENGALAHKAN saran generik skill Boost (laravel-best-practices, testing-best-practices). Pakai bersamaan setiap kali menulis/mengubah kode Laravel 13, Blade (objek DevLib / MockupParts), model, migration, atau test di repo ini.
---

# TMERP — override untuk skill Boost

**Urutan prioritas skill (user 2026-10-07):** 1) `devlib-oo` (utama: pewarisan / object oriented DevLib) → 2) skill TMERP (file ini, `tmerp-ui-mockup`) → 3) skill generik (Boost, plugin `superpowers-laravel`, dst). Bertentangan = yang bernomor lebih kecil menang.

Versi terpasang: Laravel 13, PHPUnit 12. UI = objek DevLib (`x-dl::*`) dan MockupParts (`x-mp::*`); **tidak ada** Livewire, Filament, PowerGrid, Tailwind, Vite, atau npm (v1 dibuang 2026-10-22; arsip di `tmerp_unused/arsip_v1_*.zip`). Skill Boost benar untuk API versinya; bila bertentangan dengan daftar di bawah, **daftar ini yang dipakai** (sumber: CLAUDE.md).

## Layar v2 (Blade)

- Halaman = deklarasi tipis objek `x-dl::*` / `x-mp::*` di `resources/views/v2/screens`, rute di `routes/v2*.php`, menu dari `sys_app_menu` (`target='v2:<slug>'`). Aturan simpan / hitung di `app/Services/*` atau kelas `App\Support\V2\Data\*`, bukan di Blade.
- CSS dan JS datang dari objek: `devlib.css/js` (repo `../devlib`) dan `mockup-parts.css/js` (`packages/mockup-parts`). Tanpa build npm; warna lewat token objek (teks hitam atau terang, abu-abu hanya `--dl-disabled`), bukan hex atau class buatan sendiri.
- Jangan `@php` inline di dalam `@if/@foreach`; `@php(...)` inline wajib penutup. Jangan tulis tag komponen berkurung sudut di komentar Blade.
- Cari objek yang ada dulu (katalog devlib.test, `../devlib/src/Components`, `packages/mockup-parts`); perilaku baru dipakai >1 layar = tambahkan di induk objek (skill `devlib-oo`).

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

- PHPUnit (bukan Pest). Test berjalan ke MySQL `tm2*` sungguhan (atau dummy `ddt*` lewat `tools/test-ddt.ps1`), **tanpa `RefreshDatabase`** — bersihkan data sendiri di `tearDown()` dan cek ulang di DB. Jangan pernah pakai `employee_id=1` / `sec_user id=1` sebagai data uji yang diubah.
- Verifikasi UI: permintaan HTTP di test (`actingAs`) atau login admin di browser uji (CLAUDE.md butir 8); tangkapan layar sendiri.
