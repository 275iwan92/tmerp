---
name: devlib-oo
description: Skill UTAMA (prioritas 1) proyek TMERP — cara kerja object oriented / pewarisan DevLib. Pakai SEBELUM menulis markup, JS, atau kode PHP apa pun di repo ini (layar, komponen, grid, print, engine, seeder). Mengalahkan tmerp-stack-rules, skill Boost, dan plugin superpowers-laravel bila bertentangan.
---

# DevLib OO — pedoman utama

**Urutan prioritas skill (user 2026-10-07):**
1. `devlib-oo` (file ini) — cara berpikir & bekerja
2. `tmerp-stack-rules` (+ `tmerp-screen`, `tmerp-ui-mockup`) — aturan proyek
3. Skill generik (Boost, `superpowers-laravel:*`, dst) — hanya untuk hal yang tidak diatur 1 dan 2. Bertentangan = abaikan skill generik.

Sumber: memori `feedback_devlib_object_oriented_inheritance_first`, `docs/globaluserrules.md` D.15, D.17, D.18. Aturan UMUM (cara DevLib OO bekerja, arah warisan, kode proyek/main code) ada di skill umum `devlib` (`SKILL.md`, `inheritance.md`, `engine.md`); file ini hanya menambah yang KHUSUS tmerp.

## Pola OO khusus tmerp

Akar `DevlibComponent`, anak = `extends` induk, PHP + JS berpasangan (`jsClass()`): lihat skill `devlib` (`inheritance.md`). Pola yang sama di luar komponen, di tmerp:
- Print: `Devlib\PrintDoc\Document` + satu subclass per dokumen di `App\Support\V2\Print`; engine terdaftar di `App\Support\Engines\EngineRegistry`; kelas Grid di `app/Support/V2/Grid/*` (daftar dokumen = turunan `DocListGrid`); trait `PricedDummyChain` untuk seeder (`database/seeders/Concerns`).
- JS: timpa perilaku induk dengan `Devlib.register(class Y extends Devlib.classes.Kanban {...})`; satu kelas per file di `../devlib/resources/assets/js/components/`.
- Layar v2 = deklarasi tipis dari `x-dl::*` / `x-mp::*`; form khusus = objek `x-mp::*` (`packages/mockup-parts`) yang menurunkan objek DevLib dan hanya menimpa titik timpa-nya (mis. `Inv/WarehouseDesk::sources()`).

## Cara menerapkan (setiap tugas)

1. **Cari objek terdekat dulu** (katalog devlib.test, `../devlib/src/Components`, `../devlib/skills/devlib/components.md`, `packages/mockup-parts`, `resources/views/components/_manifest.yaml`) lalu **PERLUAS atau SUSUN** darinya; perilaku >1 layar naik ke INDUK, bukan salin-tempel (rinci: skill `devlib`).
2. Layar tetap kecil: deklarasikan, jangan bangun. Form kecil = `x-dl::action-form`; form dokumen/master = `x-dl::form`.
3. Objek baru: **DevLib dulu** (demo di devlib.test + entri katalog), mockup/tmerp mewarisi. Kode non-UI sama: kelas dasar / trait + subclass per kasus.
4. Patuhi kontrak rantai: implement `tag()`, `source()`, `jsClass()` bila ada JS; teks English (kamus menerjemahkan), nilai data dibungkus `raw()`.
5. Tes penjaga: `DevlibOnlyRatchetTest` (HTML mentah tidak boleh bertambah), tes per objek ala `SideScreenTest`.
6. Selesai membangun: `php artisan build:catalog --env=mockup` (commit `build.json`) dan trailer commit `Agent: Iwan|Jo`.

## Dasar pemrograman: OO + komponen modular + library (user 2026-10-07)

Main code = daftar panggilan, satu kelas satu tanggung jawab, traceable, test kecil per kelas: aturan umum di skill `devlib` (`inheritance.md`, bagian "Kode proyek"). Tambahan khusus tmerp:
- **Library dulu:** cari kelas di `app/Services`, `app/Support`, `packages/*` sebelum menulis; perilaku dipakai >1 tempat naik ke induk / trait / library.
- Modul / paket (`packages/*`) tidak bergantung pada kode tmerp; ketergantungan lewat kontrak (interface / kelas dasar) dan konstruktor.
- Traceable: objek memakai `tag()` / `source()` / `lineage()`; engine terdaftar di `EngineRegistry`; satu jalur log; kegagalan = exception bernama dengan konteks (id dokumen, langkah).
- Kode baru: `declare(strict_types=1)`, tipe parameter dan return; konstanta / enum, bukan magic string.
Dikutip di `docs/globaluserrules.md` D.17.

## Arah warisan DevLib -> tmerp

DevLib membuat, tmerp mewarisi; paket `../devlib` tanpa kode `App\`, helper global (mis. `raw()`) milik paket. Rinci: skill `devlib-inheritance`.

## Bila skill generik bertentangan

Contoh: saran "buat Blade component/Form Request/controller baru" padahal sudah ada objek DevLib yang bisa diperluas; saran markup Tailwind mentah; file Livewire berprefix `⚡`. Pilih jalur DevLib / tmerp, sebut singkat alasannya.
