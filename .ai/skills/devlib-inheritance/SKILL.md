---
name: devlib-inheritance
description: Aturan arah warisan DevLib -> tmerp (user 2026-10-07). Pakai saat membuat atau mengubah objek x-dl::*, helper global, kelas di ../devlib, demo devlib.test, atau saat sebuah demo / komponen DevLib error karena memakai fungsi atau kelas milik tmerp (mis. "Call to undefined function raw()"). Pelengkap devlib-oo (prioritas 1).
---

# DevLib membuat, tmerp mewarisi

Pelengkap `devlib-oo`. Sumber: `docs/globaluserrules.md` D.18, test `tests/Feature/DevlibInheritanceTest.php`.

## Lokasi (pindah 2026-10-19)

DevLib = repo git sendiri `C:\Users\iwanp\Herd\devlib` (setara tmerp, bisa dipakai proyek lain), BUKAN lagi `tmerp/packages/devlib`. tmerp dan situs `tmerp/devlib` memasangnya lewat Composer path repository (`telasih/devlib`, junction `vendor/telasih/devlib`). Mengubah DevLib = edit di folder itu dan **commit di repo itu**; git tmerp tidak melihatnya. Tailwind memindai lewat `@source` di `resources/css/app.css`.

## Aturan

1. **DevLib dulu.** Objek, helper, dan perilaku bersama dibuat di `../devlib` (demo di `devlib/resources/views/demos`, entri di `devlib/app/Support/Catalog.php`). tmerp dan mockup.test hanya memakai atau menurunkannya.
2. **Paket berdiri sendiri.** `../devlib/src` tidak boleh memakai kode `App\` milik tmerp: tidak ada `use App\...`, `new App\...`, atau panggilan statis `App\...::`. Menyebut `App\...` di komentar atau teks keterangan boleh.
3. **Helper global milik paket.** Fungsi global yang dipakai komponen / demo `x-dl::*` didefinisikan di `../devlib/src/helpers.php` (dibungkus `function_exists`, dimuat `DevlibServiceProvider::register`), bukan di `app/`. Contoh: `raw()`.
4. **Butuh data / kamus tmerp?** `t()` dan `tf()` bergantung pada `App\Support\Localization\Dictionary`, jadi tetap di tmerp. DevLib menerimanya lewat props / kontrak (interface, kelas dasar), atau memakai `\Devlib\Lang::text()` / `\Devlib\Lang::raw()`.
5. **Uji arah warisan:** demo harus tampil di devlib.test tanpa kode tmerp. Di demo / view paket pakai `raw()`, `\Devlib\Lang::text()`, `\Devlib\Locale::*`; jangan pakai `t()`, `tf()`, model, atau service tmerp.

## Memeriksa

```bash
php artisan test --filter=DevlibInheritanceTest      # paket bebas App\, raw() dari paket
```

Render semua demo bila curiga ada yang bergantung pada tmerp: lihat pola skrip di commit `146edd65` (bootstrap aplikasi `devlib/`, `view("demos.$n")->render()` per berkas). Demo yang butuh variabel dari controller (`$languages`, `$action`, ...) memang tidak bisa dirender sendirian; itu bukan pelanggaran.

## Gejala umum

- `Call to undefined function raw()` / `t()` di devlib.test -> helper hidup di `app/`; pindahkan yang netral ke `../devlib/src/helpers.php`, atau ganti di demo dengan `\Devlib\Lang::*`.
- `Class "App\..." not found` di devlib.test -> komponen paket memanggil kelas tmerp; balik arahnya: DevLib menerima data lewat props, tmerp yang mengisinya.
