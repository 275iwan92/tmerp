---
name: devlib-oo
description: Skill UTAMA (prioritas 1) proyek TMERP — cara kerja object oriented / pewarisan DevLib. Pakai SEBELUM menulis markup, JS, atau kode PHP apa pun di repo ini (layar, komponen, grid, print, engine, seeder). Mengalahkan tmerp-stack-rules, skill Boost, dan plugin superpowers-laravel bila bertentangan.
---

# DevLib OO — pedoman utama

**Urutan prioritas skill (user 2026-10-07):**
1. `devlib-oo` (file ini) — cara berpikir & bekerja
2. `tmerp-stack-rules` (+ `tmerp-screen`, `tmerp-ui-mockup`) — aturan proyek
3. Skill generik (Boost, `superpowers-laravel:*`, dst) — hanya untuk hal yang tidak diatur 1 dan 2. Bertentangan = abaikan skill generik.

Sumber: memori `feedback_devlib_object_oriented_inheritance_first`, `docs/globaluserrules.md` D.15, `docs/componentstandard.md`.

## Cara DevLib OO bekerja
- Akar: `Devlib\Components\DevlibComponent` (abstrak, turunan `Component` Laravel): `tag()`, `source()`, `lineage()` (rantai induk tampil di tooltip Developer Mode), `can="modul.aksi"`, terjemahan otomatis prop teks, `dictionaryGroup()`, `jsClass()` opsional.
- Anak = `extends` induk dan hanya menyatakan yang berbeda (contoh `SideScreen extends Drawer`; `SideScreen\Layout extends Tabs`). Induk dapat fitur baru -> semua anak ikut.
- PHP + JS berpasangan: `jsClass()` -> `data-dl-js`; JS `class X extends Devlib.Component` (`static selector`, `init()`, `emit()`), didaftar `Devlib.define/register`; timpa perilaku induk dengan `Devlib.register(class Y extends Devlib.classes.Kanban {...})`. Satu kelas per file di `packages/devlib/resources/assets/js/components/`.
- Pola sama di luar komponen: `Devlib\PrintDoc\Document` + satu subclass per dokumen (`App\Support\V2\Print`); `EngineRegistry`; kelas Grid (`app/Support/V2/Grid/*`, daftar dokumen = turunan `DocListGrid`); trait `PricedDummyChain` untuk seeder; layar v2 = deklarasi tipis dari `x-dl::*` / `x-mp::*`; form khusus = objek `x-mp::*` (`packages/mockup-parts`).

## Cara menerapkan (setiap tugas)
1. **Cari objek terdekat dulu** (katalog devlib.test, `packages/devlib/src/Components`, `packages/mockup-parts`, `_manifest.yaml`) lalu **PERLUAS atau SUSUN** darinya. Jangan menulis dari nol.
2. Perilaku yang mungkin dipakai >1 layar = tambahkan di **INDUK** (prop / method / hook baru). Beda per layar = kelas anak tipis atau props — **jangan salin-tempel**.
3. Layar tetap kecil: **deklarasikan, jangan bangun.** Tanpa HTML mentah, JS inline, hex warna. Form kecil = `x-dl::action-form`; form dokumen/master = `x-dl::form`.
4. Objek baru: **DevLib dulu** (demo di devlib.test + entri katalog), mockup/tmerp mewarisi. Kode non-UI sama: kelas dasar / trait + subclass per kasus.
5. Patuhi kontrak rantai: implement `tag()`, `source()`, `jsClass()` bila ada JS; teks English (kamus menerjemahkan), nilai data dibungkus `raw()`.
6. Tes penjaga: `DevlibOnlyRatchetTest` (HTML mentah tidak boleh bertambah), tes per objek ala `SideScreenTest`.
7. Selesai membangun: `php artisan build:catalog --env=mockup` (commit `build.json`) dan trailer commit `Agent: Iwan|Jo`.

## Dasar pemrograman: OO + komponen modular + library (user 2026-10-07)
Main code (layar, route, komponen Livewire, command) harus **sederhana, mudah di-debug, traceable**:
- **Main code = daftar panggilan.** Dibaca seperti alur bisnis (`$order = CoOrderBuilder::fromQuotation($q); $order->confirm();`). Logika, rumus, query, dan markup tinggal di kelas / service / library / objek `x-dl::*`, bukan di file layar.
- **Satu kelas = satu tanggung jawab**, nama kelas dan method memuat maksud. Method pendek; bila perlu komentar untuk menjelaskan *apa*, pecah jadi method bernama.
- **Modular & berdiri sendiri:** modul / paket (`packages/*`) tidak bergantung pada kode tmerp; ketergantungan lewat kontrak (interface / kelas dasar) dan konstruktor, bukan `new` tersembunyi atau global.
- **Library dulu:** cari kelas di `app/Services`, `app/Support`, `packages/*` sebelum menulis. Perilaku dipakai >1 tempat = naik ke induk / trait / library, tidak disalin.
- **Traceable:** objek memakai `tag()` / `source()` / `lineage()`; engine terdaftar di `EngineRegistry`; satu jalur log; kegagalan = exception bernama (bukan `false` / `null` diam-diam) dengan konteks (id dokumen, langkah); jangan menelan error dengan `catch` kosong.
- **Mudah di-debug:** tanpa efek samping tersembunyi, tanpa magic string berserakan (pakai konstanta / enum), satu sumber kebenaran per nilai, input / output method jelas dan bertipe (`declare(strict_types=1)` untuk kode baru, tipe parameter dan return).
- **Yang bertambah = test kecil per kelas** (service / engine), supaya bug terlacak ke satu kelas.
Dikutip di `docs/globaluserrules.md` D.17.
## Bila skill generik bertentangan
Contoh: saran "buat Blade component/Form Request/controller baru" padahal sudah ada objek DevLib yang bisa diperluas; saran markup Tailwind mentah; file Livewire berprefix `⚡`. Pilih jalur DevLib / tmerp, sebut singkat alasannya.
