---
name: tmerp-screen
description: Bangun layar Livewire TMERP baru (grid master, grid worklist, form entry) secara cepat dan hemat token — generate kerangka standar dengan make:tm-screen, lalu isi bagian khasnya saja memakai komponen dari manifest. Pakai tiap kali membuat layar/komponen UI baru.
---

# Bangun layar TMERP (turunan dari kerangka standar)

Prinsip: jangan tulis ulang pola yang sudah baku. Kerangka (trait grid, toolbar, stat box, header navy, Simpan/Back) diambil dari generator; komponen diambil dari library `<x-*>`. Yang ditulis manual hanya kolom, field, query, dan aturan bisnis layar itu.

## Baca HANYA ini
1. `resources/views/components/_manifest.yaml` — semua tag, props, tone, gotcha. **Jangan buka file komponen satu per satu** kecuali manifest kurang jelas.
2. Model Eloquent terkait (`$connection`, `$table`, `$primaryKey`, `$fillable`).
3. Mockup/acuan layar bila ada (`docs/mockups/`, skill `tmerp-ui-mockup` untuk JPEG yang sudah disetujui).
Jangan baca `docs/BACKLOG.md`, `docs/LOG_JOB_DONE.md`, atau standar UI lengkap kecuali ada hal yang tidak tercakup manifest.

## Langkah
1. Generate kerangka:
   ```bash
   php artisan make:tm-screen master master.gudang --model="Master\WhWarehouse" --title="Master Gudang" --route=master.gudang.edit --param=warehouse
   php artisan make:tm-screen grid   sales.xyz-list --model="Sdi\CoOrder" --route=co.entry --param=order --name-col=... --code-col=co_no
   php artisan make:tm-screen form   master.gudang-entry --model="Master\WhWarehouse" --route=master.gudang.edit --list-route=master.gudang --param=warehouse --fields="warehouse_code,warehouse_name,activate"
   ```
   `master` = Edit + toggle aktif + "+ New Record"; `grid` = Buka + Confirm kondisional (tanpa toggle aktif); `form` = header navy + Simpan + Back + field.
2. Isi yang ditandai: `gridColumnDefs()`/`gridDefaultColumns()`, `columnValue()`, stat box tambahan, field & rules form (ganti `required|string|max:255` sesuai DDL), tipe field (date-picker, select, dll dari manifest).
3. Sesuaikan nama kolom audit bila model berbeda (mis. `update_by` vs `updated_by`) dan validasi lintas-DB `exists:tm2xxx.tabel,kolom`.
4. Daftarkan route di `routes/web.php` pada grup `module:<KODE>` yang benar; menu di `sys_app_menu` lewat migration.
5. Verifikasi: `php -l`, lalu `Livewire::test('pages::modul.nama')` via tinker; `npm run build` bila ada class Tailwind baru.

## Multi-bahasa (wajib, LOCKED 2026-10-01)
Label/teks ditulis ENGLISH: komponen `x-dl::*` & Grid/Lookup DB (`DbGrid`/`DbLookup`) menerjemahkan otomatis lewat kamus
`sys_dictionary`; teks lain `t('...')` / `@t('...')` (generator sudah memakainya). Teks baru tercatat sendiri -- terjemahan diisi di
Setup > Translation Dictionary. Angka/tanggal lewat `Devlib\Locale` / objek nilai DevLib (bukan `number_format` / `format('d-M-Y')`).
`MultiLanguageRatchetTest` gagal bila label Indonesia mentah / format sendiri bertambah. Detail: skill `tmerp-stack-rules` bagian Multi-bahasa.

## Komponen baru
Buat komponen baru HANYA bila pola dipakai ≥2 layar dan tidak ada di manifest. Setelah dibuat: tambahkan barisnya ke `_manifest.yaml` dan tampilkan di layar DevLib (`setup/component-library.blade.php`). Pola bersama (label/hint/error, tone warna) diikuti, bukan disalin dengan variasi baru.
