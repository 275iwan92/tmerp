# Instruksi untuk Claude Code — Layout `opening_app`

> Copy blok di bawah (di dalam ```), tempel ke Claude Code.

```
KONTEKS: Modul opening_app (login, register, forgot-password, main_menu).
MASALAH: Halaman login tidak full-screen — panel & hero mengambang di tengah,
ada ruang putih di kiri/sekeliling. Penyebab: view dibungkus layout ber-container
(punya max-width / mx-auto / padding). Gambar hero sudah OK.

PERBAIKI DI LEVEL LAYOUT/BLADE, BUKAN CSS MOCKUP:

1. Buat DUA layout khusus, keduanya TANPA container/max-width/padding:
   - layouts/guest.blade.php  -> untuk login, register, forgot-password
   - layouts/shell.blade.php  -> untuk main_menu
   Isi <body> HANYA {{ $slot }} — tidak ada <div class="container">,
   <main class="max-w-...">, atau <div class="p-..."> di antaranya.

2. Hierarki wajib:  <body> -> langsung .wrap  (tanpa pembungkus lain).
   .wrap { display:flex; height:100vh; width:100%; }
   .hero { flex:1; }  (hero: position:absolute; inset:0; object-fit:cover)

3. Reset global KHUSUS halaman opening_app:
   html,body { margin:0; padding:0; height:100%; width:100%; }
   *{ box-sizing:border-box; }
   Jangan biarkan reset/utility Tailwind menimpa CSS mockup.

4. Pastikan view login/register/forgot MEMAKAI layouts.guest,
   main_menu MEMAKAI layouts.shell. Jangan pakai layout app default.

VERIFIKASI: buka DevTools, klik <body> -> lebarnya harus = lebar viewport penuh.
Telusuri anak-anaknya sampai .wrap; tidak boleh ada elemen dengan
margin-auto / max-width / lebar < 100vw di antara <body> dan .wrap.
```

---

## Cara Anda cek sendiri (opsional)
1. Tekan **F12** → klik ikon panah inspektor.
2. Arahkan ke **area putih di kiri kartu**.
3. Elemen yang ter-highlight = **pembungkus penyebabnya** (biasanya `<main>` atau `<div class="container">` dari layout app).
4. Kalau tahu nama elemennya, kabari saya — instruksinya bisa dibuat lebih spesifik.

## Kenapa dua layout
- **`guest`** (full-bleed): auth pakai hero + panel yang mengisi 100% layar.
- **`shell`** (full-viewport): main_menu punya top bar + sidebar + area tab yang mengisi layar tanpa scroll body.
- Dibuat sekali → semua layar opening_app konsisten, tak perlu koreksi per-halaman.
