---
name: devlib-inheritance
description: Bagian KHUSUS tmerp dari arah warisan DevLib -> tmerp (user 2026-10-07): cara tmerp memasang DevLib, helper t()/tf() yang tetap di tmerp, dan cara subclass `x-mp::*` mewarisi. Pakai saat membuat atau mengubah objek x-dl::* / x-mp::*, atau saat demo / komponen DevLib error karena memakai fungsi atau kelas milik tmerp. Aturan umum arah warisan ada di skill `devlib` (inheritance.md). Pelengkap devlib-oo (prioritas 1).
---

# DevLib membuat, tmerp mewarisi (bagian khusus tmerp)

Aturan umum (DevLib dulu, paket berdiri sendiri tanpa `App\`, helper global milik paket di `src/helpers.php`, perubahan opt-in, situs acuan mandiri, gejala umum `Call to undefined function raw()` / `Class "App\..." not found`) ada di skill umum `devlib`, berkas `inheritance.md`: baca itu, jangan diulang di sini. Sumber tmerp: `docs/globaluserrules.md` D.18, tes `tests/Feature/DevlibInheritanceTest.php`.

## Lokasi dan pemasangan di tmerp

- DevLib = repo git sendiri `C:\Users\iwanp\Herd\devlib` (dipindah dari `packages/devlib` 2026-10-19). Perubahan DevLib di-commit di repo itu; git tmerp tidak melihatnya.
- Terpasang lewat Composer path repository (`composer.json`: `jo/devlib` `@dev`, `url ../devlib`, `symlink`), jadi `vendor/jo/devlib` = tautan ke folder itu. Tailwind memindai lewat `@source '../../../devlib/...'` di `resources/css/app.css`.
- Situs acuan `devlib.test` = `devlib/site` DI REPO DEVLIB (bukan folder `devlib/` di tmerp, yang sudah tidak ada): demo di `site/resources/views/demos`, katalog `site/app/Support/Catalog.php`.

## Khusus tmerp

1. **`t()` dan `tf()` tetap di tmerp** (`app/Support/Localization/helpers.php`, bergantung pada `App\Support\Localization\Dictionary`). DevLib tidak boleh memakainya: ia menerima teks lewat props / kontrak, atau memakai `\Devlib\Lang::text()` / `\Devlib\Lang::raw()` / `\Devlib\Locale::*`. Di tmerp, teks layar cukup English (komponen `x-dl::*` menerjemahkan sendiri); `tf()` untuk kalimat bernilai, hasilnya dibungkus `raw()`.
2. **Subclass `x-mp::*` hanya mengganti yang tmerp punya.** Objek DevLib menyediakan metode yang dapat ditimpa; subclass di `packages/mockup-parts/src/Components/*` menimpanya dengan data / kontrak tmerp. Contoh: `Inv/WarehouseDesk` menimpa `sources()` (nama tabel `tm2mst.*` / `tm2inv.*` per peran tooltip) dan `src()` (penerjemah lewat `App\Support\V2\Src`). Nama tabel tmerp TIDAK boleh masuk ke `../devlib`; bila DevLib kurang satu titik timpa, tambahkan di induk (opt-in, default netral), lalu timpa di subclass.
3. Demo yang butuh variabel dari controller (`$languages`, `$action`, ...) tidak bisa dirender sendirian di situs; itu bukan pelanggaran.

## Memeriksa

```bash
php artisan test --filter=DevlibInheritanceTest      # paket bebas App\, raw() dari paket
cd ../devlib/site && php artisan test                # CatalogPagesTest merender semua halaman katalog tanpa kode tmerp
```
