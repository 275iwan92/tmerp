# STANDAR — TEMA (LIGHT/DARK/PASTEL/CERAH x CORAK WARNA)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-31 (keputusan user, 2 tahap: dasar light/dark dulu, lalu
> direvisi jadi matrix 4 mode x 3 corak). **Sumber kebenaran warna =
> tabel `tm2sys.sys_theme`, BUKAN dokumen ini** — dokumen ini menjelaskan
> strukturnya + tabel referensi biar gampang dibaca, tapi kalau mau ganti
> satu warna, edit barisnya langsung di DB (lihat §4), jangan edit angka di
> sini lalu berharap ikut berubah.

---

## 1. KAPAN PAKAI INI

Baca ini sebelum: menambah/mengubah pilihan tema (warna aksen/mode terang-
gelap) di mana pun; menambah token warna baru yang perlu ikut berubah per
mode; atau bikin picker/selector tema baru di luar dropdown avatar yang
sudah ada.

**Cakupan saat ini — CUMA shell lama** (`layouts::shell`, dipakai
`main-menu.blade.php`: sidebar, topbar, tabbar, halaman Home, dropdown
avatar, drawer notif/chat, command palette). **BELUM** mencakup:
- Halaman transaksi yang dibuka lewat `<iframe>` tab (Inquiry, Stock In,
  Quotation, dst.) — masih warna Tailwind literal, tidak ikut toggle.
- Panel Filament `/admin` — punya dark-mode bawaan sendiri, sengaja
  **dipaksa terang terus** (`AdminPanelProvider::panel()->darkMode(false)`)
  supaya tidak nyasar gelap sendiri lepas dari sistem ini.

---

## 2. KONSEP — 2 SUMBU JADI 1 PILIHAN

Tema BUKAN "pilih warna" + "pilih terang/gelap" secara terpisah. **1 baris
`sys_theme` = 1 kombinasi lengkap** (mode + family + corak + semua warna
turunannya), dipilih user lewat 1 klik swatch. Alasan: sidebar/topbar warna
gelap butuh teks terang dan sebaliknya — kalau mode & warna dipisah,
kombinasi tak-valid (mis. sidebar pastel + teks gelap-butuh-terang) gampang
kejadian tanpa sengaja.

**4 baris (family) x 3 kolom (hue) = matrix utama:**

| Family | Arti | `mode` | Sidebar/topbar | Background konten |
|---|---|---|---|---|
| **Pastel** | Terang, lembut/low-saturation | `light` | putih (`--side`/`--topbar` override, lihat §3) | tinted lembut sesuai corak |
| **Cerah** | Terang, warna aksen vivid/saturated | `light` | putih | netral terang standar |
| **Dark** | Gelap, sidebar & konten ikut ber-corak | `dark` | gelap ber-tint sesuai corak | gelap ber-tint sesuai corak |
| **Hitam** | Gelap pekat, TANPA tint — corak cuma di aksen | `dark` | hitam nyaris pekat, SAMA di 3 corak | hitam nyaris pekat, SAMA di 3 corak |

| Corak (`hue`) | Kode |
|---|---|
| Biru | `blue` |
| Hijau | `green` |
| Merah | `red` |

### Tabel referensi 12 kombinasi (state per 2026-08-31 — cek DB untuk nilai aktual terkini)

| `code` | Nama | Family | Hue | `primary_color` (aksen) | `sidebar_color` | `content_bg_color` |
|---|---|---|---|---|---|---|
| `pastel_blue` | Pastel Biru | pastel | blue | `#6aa3e8` | `#1f2742` | `#f2f6fc` |
| `pastel_green` | Pastel Hijau | pastel | green | `#7fae8f` | `#1c2e26` | `#f2f7f3` |
| `pastel_red` | Pastel Merah | pastel | red | `#d98fa0` | `#2f2028` | `#faf3f5` |
| `navy` | Cerah Biru (Navy) — **default app** | cerah | blue | `#2f6fed` | `#1f2742` | *(default terang)* |
| `emerald` | Cerah Hijau (Emerald) | cerah | green | `#16a34a` | `#123a26` | *(default terang)* |
| `cerah_red` | Cerah Merah | cerah | red | `#e5484d` | `#3a1414` | *(default terang)* |
| `dark_blue` | Dark Biru | dark | blue | `#4c85ff` | `#101b30` | `#12182c` |
| `dark_green` | Dark Hijau | dark | green | `#34d399` | `#0b1f16` | `#0f1c17` |
| `dark_red` | Dark Merah | dark | red | `#ff6b6b` | `#2a1416` | `#1f1518` |
| `hitam_blue` | Hitam Biru | hitam | blue | `#5b9dff` | `#0a0a0c` | `#060608` |
| `hitam_green` | Hitam Hijau | hitam | green | `#2ee6a6` | `#0a0a0c` | `#060608` |
| `hitam_red` | Hitam Merah | hitam | red | `#ff5c5c` | `#0a0a0c` | `#060608` |

**Di luar matrix ("Klasik"):** `slate`, `plum`, `indigo`, `teal` — 4 tema
lama (family/hue `NULL`), tetap dipertahankan & muncul di grup terpisah di
picker (§5), tak dihapus karena mungkin sudah dipakai user sebelum matrix
ini ada.

---

## 3. STRUKTUR TABEL `tm2sys.sys_theme`

Migration: `2026_08_31_000001` (kolom dasar) → `2026_08_31_000003` (kolom
`mode`) → `2026_08_31_000004` (kolom `family`/`hue` + isi matrix).

| Kolom | Tipe | Arti |
|---|---|---|
| `code` | varchar(30), PK | ID unik, dipakai `sec_user.theme_code` |
| `name` | varchar(60) | Label ditampilkan (tooltip swatch) |
| `primary_color` | varchar(9) | Warna aksen (tombol, link, highlight) — SELALU dipakai apa pun mode |
| `sidebar_color` | varchar(9) | Warna sidebar/topbar — **HANYA dipakai kalau `mode`=`dark`** (lihat §3.1) |
| `mode` | enum(`light`,`dark`) | Menentukan set token yang dipakai (lihat §3.1) |
| `family` | enum(`pastel`,`cerah`,`dark`,`hitam`), nullable | Baris matrix picker. `NULL` = tema Klasik, di luar grid |
| `hue` | enum(`blue`,`green`,`red`), nullable | Kolom matrix picker. `NULL` = tema Klasik |
| `content_bg_color` | varchar(9), nullable | Override background area konten. `NULL` = pakai default per `mode` (`#eef1f6` terang / `#12162a` gelap) |
| `is_default` | boolean | Tema fallback kalau user belum pilih (HARUS cuma 1 baris `1`) |
| `activate` | boolean | Nonaktifkan tanpa hapus baris (tak muncul di picker) |
| `sort_order` | int | Urutan tampil (dalam grup family yang sama / grup Klasik) |

### 3.1 Kenapa `sidebar_color` diabaikan saat `mode`=`light`

Teks sidebar/topbar (`.leaf`, `.mega-item`, `.tb-btn`, logo, dst di
`app.css`) HARUS terang di atas bg gelap, HARUS gelap di atas bg terang —
tak bisa 1 warna teks cocok untuk semua kombinasi. Solusinya:
`resources/views/partials/theme-vars.blade.php` cabang berdasar `$__theme->mode`:

- **`mode`=`dark`**: `--side`/`--topbar` = `sidebar_color` baris itu (jadi
  corak tetap kepakai di sidebar/topbar), token teks (`--side-ink`,
  `--side-ink-strong`, dst) di-set ke warna TERANG.
- **`mode`=`light`**: `--side`/`--topbar` **DIPAKSA putih**
  (`sidebar_color` baris itu TIDAK dipakai sama sekali), token teks di-set
  ke warna GELAP. Corak tetap kelihatan lewat `--sh-accent` (link, tombol,
  ikon aktif, dsb), bukan lewat bg sidebar.

Semua token turunan (`--side-ink`, `--side-ink-strong`, `--side-muted`,
`--side-hover`, `--side-hover-soft`, `--side-border-soft`, `--side-head-bg`,
`--side-border`) di-define di `.shell` (app.css, nilai default = mode
gelap) lalu di-override di `theme-vars.blade.php` cabang `light`. **Jangan
pernah hardcode `text-white`/warna literal baru di elemen sidebar/topbar**
— selalu lewat variabel ini, atau kombinasi mode berikutnya akan pecah lagi
(insiden yang sama persis terjadi 2026-08-31 sebelum token ini ada).

---

## 4. CARA EDIT/TAMBAH TEMA (untuk user, langsung ke DB)

**Ubah warna tema yang sudah ada** — cukup UPDATE baris-nya, tak perlu
migration baru:
```sql
UPDATE tm2sys.sys_theme SET primary_color = '#1e90ff' WHERE code = 'dark_blue';
```
Perubahan langsung kepakai (tinggal reload — lihat §5, ganti tema memang
butuh reload karena token di-render server-side sekali per page load).

**Tambah tema baru** (mis. corak ke-4 "Ungu/Purple", ATAU family ke-5) —
INSERT baris baru dengan `family`/`hue` yang sesuai, picker (§5) otomatis
menampilkannya di sel yang tepat TANPA ubah kode apa pun:
```sql
INSERT INTO tm2sys.sys_theme
  (code, name, primary_color, sidebar_color, mode, family, hue, content_bg_color, is_default, activate, sort_order)
VALUES
  ('pastel_purple', 'Pastel Ungu', '#a78bda', '#2a2140', 'light', 'pastel', 'purple', '#f6f3fb', 0, 1, 135);
```
Catatan: `hue` kolomnya `enum('blue','green','red')` saat ini — corak baru
di luar 3 itu butuh `ALTER TABLE ... MODIFY hue ENUM(...)` dulu (tambah
nilai enum) sebelum bisa INSERT. Kalau mau nambah corak permanen, migration
baru lebih aman daripada ALTER manual (ikut pola `2026_08_31_000004`).

**Ganti tema default aplikasi** — cuma 1 baris boleh `is_default=1`:
```sql
UPDATE tm2sys.sys_theme SET is_default = 0;
UPDATE tm2sys.sys_theme SET is_default = 1 WHERE code = 'pastel_blue';
```

---

## 5. UI PICKER (dropdown avatar)

`main-menu.blade.php` — computed `themeMatrix()` query `sys_theme`,
kelompokkan jadi grid 4 baris (family) x 3 kolom (hue) + daftar terpisah
"Klasik" (family `NULL`). Markup: bagian "Tema" di dropdown avatar
(`.pop-menu`), grid `.utm-hdr`/`.utm-row` (header B/G/R + baris per family)
pakai `.um-swatch` (lingkaran warna `primary_color`, klik = pilih).

Selain grid lengkap, ada **toggle cepat** (ikon matahari/bulan di topbar,
sebelah kiri avatar) — `quickToggleTheme()` pindah ke pasangan
terang/gelap tema SAAT INI (cari `{code}_dark`/strip `_dark`), fallback ke
tema `dark`/`is_default` pertama kalau pasangannya tak ada.

**Kedua jalur (grid & toggle cepat) memanggil `$wire.setTheme(code)` lalu
`location.reload()`** — token warna dirender sekali di `<head>`
(`theme-vars.blade.php`, di luar boundary re-render Livewire component),
jadi ganti tema BUTUH reload penuh, tidak instan seperti toggle CSS klien
biasa. Ini keputusan sadar (server jadi satu-satunya sumber kebenaran,
tak ada state warna di client yang bisa nyasar tak sinkron), bukan
keterbatasan yang belum sempat diperbaiki.

---

## 6. YANG SENGAJA TIDAK DITANGANI

- **Halaman iframe (transaksi)** — lihat §1, scope terpisah & jauh lebih
  besar (audit warna Tailwind literal di puluhan file), belum digarap.
- **Filament `/admin`** — sengaja dipaksa terang (`darkMode(false)`),
  TIDAK disinkronkan ke `sys_theme` — 2 sistem independen.
- **Preferensi per-device/lintas-perangkat** — `theme_code` nempel ke
  `sec_user`, jadi 1 pilihan berlaku di semua device user itu (bukan per-
  browser). Ini sudah pola yang sama dengan `sec_user.theme_code` versi
  lama (cuma warna aksen), tidak diubah.
- **Kontras otomatis/validasi warna** — tak ada pengecekan WCAG kontras
  saat tambah baris baru (§4). Kalau nambah corak/tema baru manual, cek
  manual keterbacaan teks di atas `primary_color`/`sidebar_color`-nya.
