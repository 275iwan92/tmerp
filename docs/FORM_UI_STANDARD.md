# FORM_UI_STANDARD.md — Acuan Tampilan Form/Entry (header, tombol Back)

> Ditulis 2026-08-24 setelah dirapikan bareng user di Customer Order Entry
> (`co/entry.blade.php`), lalu diterapkan ke semua layar form/entry senada.
> **Baca file ini dulu sebelum bikin/ubah header layar form apa pun** — sama
> semangatnya dengan `GRID_UI_STANDARD.md` untuk grid.

Berlaku untuk semua layar **form/entry** (Customer Order, Quotation, PR/PO/PV/PRT/PQ,
Master Warehouse/Raw Material, Inventory Stock In/Out/Return/Adjustment, Finance
Cash & Bank, Notification, print report, Inquiry Imposition/Costing, dst) — layar
dengan `<header class="bg-navy text-white px-5 py-3 flex items-center gap-3.5 sticky top-0 z-50">`
di atas.

---

## 1. Tombol Back — pill navy + lingkaran panah putih

> **UPDATE 2026-09-18 (instruksi user, LOCKED): Back = kembali ke layar
> SEBELUMNYA, bukan route hardcode.** Pakai komponen
> `resources/views/components/back-button.blade.php`:
> `x-back-button :fallback="route('grid.modul')"` (+ `title`/`class` opsional).
> Klik ditangani `resources/js/dirty-guard.js` via atribut `data-back`: tanya
> simpan kalau form berubah → kembali ke entri riwayat sebelumnya di tab itu
> (lewati URL sama & entri `/new|/create`) → `fallback` hanya bila tak ada
> riwayat (fallback `/menu` di tab shell = tutup tab). Link teks "← Kembali ke …"
> di halaman cetak cukup tambah atribut `data-back`. Tombol Back yang
> `wire:click` toggle grid/form di halaman yang sama (`backToGrid`, `showGrid`)
> tetap boleh, WAJIB tambah atribut `data-dirty-guard`.
>
> **Dirty-form guard (2026-09-18):** meninggalkan form yang berubah (klik menu
> shell, tutup tab ×, Back, link lain) → dialog "Simpan perubahan?"
> **Yes / No / Cancel**. Yes = klik tombol Save layar itu (dikenali dari
> `wire:click="save|saveDraft|saveAll|simpan"` atau teks "Save/Simpan"; tandai
> manual dengan `data-dirty-save` kalau beda), gagal validasi → tetap di form.
> Field pencarian/filter tidak dihitung (atau tandai `data-dirty-ignore`).
> Layar kanvas/JS murni daftar adaptor: `window.tmerpDirty.register({ isDirty, save })`
> (contoh: `production/prodev.blade.php`). Klik menu yang tab-nya sudah terbuka
> = tab kembali ke layar awal menu itu (grid), setelah lewat guard ini.
> Markup HTML di bawah = isi komponen tsb (acuan visual), jangan disalin manual lagi.

> **UPDATE 2026-09-09 (instruksi user, lihat memory `project_back_button_icon_upgrade`):**
> ikon panah di lingkaran putih diganti dari karakter Unicode polos `←` ke
> ikon Font Awesome `fa-solid fa-arrow-left` — user menilai karakter `←`
> "kurang tegas kurang jelas" (render tipis/tidak konsisten tebalnya
> tergantung font). Font Awesome 6.5.1 SUDAH dimuat via CDN di
> `layouts/app.blade.php` & `layouts/shell.blade.php`, jadi tidak nambah
> dependency baru. **Acuan visual terbaru ada di Settings > DevLib UI UX**
> (`setup/component-library.blade.php`, tab Form, section "Aksi & Status")
> — cek ke sana kalau ragu bentuk pastinya. **Rollout ke 25+ layar yang
> masih pakai `←` lama BELUM dikerjakan** — item backlog terpisah
> (`docs/BACKLOG.md` §10, dieksekusi nightly autowork, TANPA prompting
> user lebih lanjut) — jangan asumsikan semua layar sudah ikut versi baru
> ini sampai backlog itu selesai.

**JANGAN** lagi pakai tombol teks polos (`← Daftar X` dalam satu kotak rata,
`bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md`). Standar baru:
pill navy dengan label **"Back"** (Inggris, bukan "Daftar X"/"Menu"/dsb. — label
generik yang sama di semua layar) + lingkaran putih berisi ikon panah Font
Awesome, tujuan spesifik pindah ke `title` (tooltip).

```html
<a href="{{ route('nama.route') }}" title="Kembali ke <tujuan spesifik>"
    class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
    <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
</a>
```

Untuk tombol yang trigger-nya method Livewire (bukan navigasi `<a href>`), pola
sama persis, ganti tag jadi `<button type="button" wire:click="...">`:

```html
<button type="button" wire:click="backToGrid" title="Kembali ke Daftar"
    class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full cursor-pointer hover:brightness-110">
    <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-[13px]"><i class="fa-solid fa-arrow-left"></i></span>
</button>
```

**Aturan:**
- Label di dalam pill **selalu "Back"** (Inggris, uppercase via `tracking-wide` +
  `font-bold`, bukan `text-transform:uppercase` manual) — jangan diganti per
  layar ("Daftar Retur", "Menu", dst.). Konsistensi lintas modul menang di sini,
  detail tujuannya taruh di `title`.
- `title` **wajib** diisi kalimat spesifik ("Kembali ke Daftar PO", "Kembali ke
  Inquiry Hub", "Kembali ke Menu Utama", dst.) — supaya tetap jelas walau
  labelnya generik.
- `ml-auto` dipasang di tombol Back **kalau dia satu-satunya elemen navigasi di
  ujung kanan header**. Kalau ada tombol lain sebelahnya yang sudah pakai
  `ml-auto` (mis. tombol "→ Costing"/"🖨️ Preview Cetak" mendorong grup ke kanan),
  tombol Back TIDAK perlu `ml-auto` lagi (lihat `inquiry/imposition.blade.php`,
  `inquiry/costing.blade.php`, `quotation/entry.blade.php`).
- Kalau header itu juga punya info ringkas lain yang perlu nempel di ujung kanan
  (mis. Net Receivable di CO Entry, atau tombol Confirm), bungkus semuanya dalam
  satu `<div class="ml-auto flex items-center gap-4">` dan taruh tombol Back
  paling kanan di dalam div itu (lihat `co/entry.blade.php`) — jangan pasang
  `ml-auto` di tombol Back-nya sendiri kalau sudah ada di dalam wrapper begini.
- Lingkaran ikon **selalu bg putih + teks navy** (`bg-white text-[#43508a]`),
  ukuran tetap `w-6 h-6`, isi **ikon Font Awesome `fa-solid fa-arrow-left`**
  (`text-[13px]`, TANPA `font-bold` — solid FA icon sudah tebal secara
  visual) — bukan karakter Unicode `←` (versi lama, digantikan 2026-09-09)
  ataupun SVG/emoji custom lain.
- Warna pill **selalu `bg-[#43508a]`** (sama seperti header lain di aplikasi),
  jangan ganti warna per modul.

**Referensi implementasi:** `co/entry.blade.php`, `quotation/entry.blade.php`,
`pur/{pr,po,pv,prt,pq}/entry.blade.php`, `master/{warehouse,raw-material}/entry.blade.php`,
`inventory/{stock-in,stock-out,stock-return,stock-adjustment}.blade.php`,
`finance/cash-bank.blade.php`, `notification/index.blade.php`,
`inventory/print/{master-stock,stock-card}.blade.php`,
`inquiry/{imposition,costing}.blade.php`.

**Belum diterapkan (beda bahasa desain, keputusan terpisah):** layar Inquiry
`form.blade.php`/`recalc.blade.php`/`revise.blade.php`/`nota.blade.php` pakai
`class="back"`/`class="sc"` (header putih, bukan navy sticky) — beda sistem
visual dari header navy standar; `master/supplier.blade.php` &
`master/employee.blade.php` pakai tombol "Kembali" untuk **toggle state dalam
halaman yang sama** (`wire:click="showGrid"`/`"showDirectory"`), bukan navigasi
ke route lain — bukan kandidat pola ini. Halaman auth (login/register/forgot-password)
& `self-service/index.blade.php` sengaja dikecualikan (konteks publik/beda tema).

---

## 1a. Standardisasi tombol aksi umum (Save/Cancel/Close) — LOCKED (keputusan user 2026-09-10)

> Latar belakang: user menemukan tombol Back di `wizard-bar.blade.php` pakai
> label "Kembali ke Grid" (melanggar §1 di atas) — dari situ diperluas jadi
> aturan umum: **semua tombol aksi generik harus satu kata yang sama persis
> di seluruh app**, bukan cuma Back. Riset banding (Odoo/SAP Fiori/Dynamics
> 365) dipakai sebagai bahan diskusi tapi TIDAK mengubah keputusan di sini —
> topik draft/auto-save (SAP Fiori Elements) itu keputusan TERPISAH, masih
> menunggu diskusi lanjutan (lihat `docs/BACKLOG.md` §9.1), JANGAN dicampur
> dengan aturan penamaan tombol di bawah ini.

**Kata baku per aksi (ikut `sec_user.lang_code`, lihat memori
`project_i18n_shell_framework` utk mekanisme Translator):**

| Aksi | Label ID | Label EN |
|---|---|---|
| Simpan perubahan | `Simpan` | `Save` |
| Batalkan/tutup tanpa simpan | `Batal` | `Cancel` |
| Tutup panel/modal (bukan discard data) | `Tutup` | `Close` |
| Kembali ke layar sebelumnya | `Back` (kedua bahasa, LOCKED §1 — tidak ikut toggle) | `Back` |

**Berlaku default di SELURUH app termasuk panel Filament `/admin`** — label
native Filament (Save/Create/Edit/Delete) di-override ikut tabel di atas,
bukan dibiarkan default framework. **Kecuali** user eksplisit minta label/
layout khusus utk 1 layar tertentu (pengecualian sengaja, bukan variasi bebas
per developer).

**Pengecualian yang SAH (bukan pelanggaran):** tombol "+ Tambah [objek]"
untuk nambah baris/item DI DALAM form (mis. "+ Tambah Cabang", "+ Tambah
Riwayat Pendidikan", "+ Tambah Proses") — ini aksi BEDA dari "+ New Record"
level grid (LOCKED di `griduistandard.md` §6, sudah 100% patuh di kode), jadi
boleh sebut objeknya per konteks. Jangan disamakan jadi "+ Tambah" polos.

**File yang masih menyimpang (ditemukan saat audit 2026-09-10, PERLU
diperbaiki — dicatat sbg item kerja di `docs/BACKLOG.md` §10):**
- `components/prodev/wizard-bar.blade.php:27` — `backLabel` default
  `"Kembali ke Grid"` → harus `"Back"` (dipakai di ProDev workbench, Inquiry
  Costing, Inquiry form).
- `components/kanban/board.blade.php:167,269,314,364` — label "Save" (EN)
  padahal komponen yg sama pakai "Batal" (ID) di baris 109/110 → tidak
  konsisten dgn dirinya sendiri; `:315` juga ada "Cancel" (EN) harusnya
  "Batal".
- `pages/setup/support-tickets.blade.php` — tombol "Close panel" → "Tutup"
  (baris `>Closed<` di filter status BUKAN tombol, itu label opsi `<option>`,
  tidak perlu diubah).
- `filament/forms/dashboard-theme-picker.blade.php:74` — "Save" → "Simpan"
  (atau override resmi Filament Action label kalau ini dirender lewat
  Filament Action, bukan Blade polos — cek dulu sebelum ubah).
- Panel Filament `/admin` secara umum: `EmpEmployeeResource`,
  `OrgPositionResource`, dan Resource berikutnya — pastikan
  `EditAction`/`CreateAction`/`DeleteAction` diberi `->label()` eksplisit
  ikut tabel di atas, bukan mengandalkan default bawaan Filament (yang
  selalu Inggris apa pun `lang_code` user).

---

## 1b. Input teks string — LOCKED (keputusan user 2026-09-21)

> **Tujuan aturan ini (kalimat user): "mencegah kedepan jika terjadi perubahan
> mockup tetap semua text input string mempergunakan component tersebut tanpa
> harus saya perintah lagi."** Jadi ini BUKAN checklist sekali jalan — berlaku
> untuk setiap layar baru, setiap revisi mockup, dan setiap sesi berikutnya.
> Sama berlakunya untuk SELURUH komponen di §3: kalau sudah ada `x-form.*`
> untuk suatu jenis input, memakai HTML mentah = pelanggaran, bukan pilihan.

**Tiga aturan yang mengikat:**

1. **Case toggle wajib.** Setiap input teks string memakai `x-form.input`
   (type=text) atau `x-form.textarea`, yang sudah membawa badge **aa → Aa →
   AA** di pojok kanan field. Alasannya operasional, bukan kosmetik: user
   banyak mengisi lewat **HP/tablet**, dan menekan Shift tiap kata itu
   merepotkan + gampang tidak konsisten. Badge mati sendiri untuk `readonly`,
   `disabled`, dan type non-teks (email/number/password/date/dst).
   - Opt-out `:case-toggle="false"` **hanya** untuk kotak CARI (nilainya tidak
     disimpan ke mana pun) atau kasus khusus yang alasannya ditulis di tempat
     pemanggilan. Bukan untuk "biar cepat".
   - Implementasi: `resources/js/case-input.js` (Alpine `tmCaseInput`), sengaja
     mentransform nilai **di elemen aslinya** supaya `wire:model` dan handler
     Alpine milik pemanggil tetap menempel di elemen yang benar.
2. **Lookup dulu, ketik belakangan.** Kalau nilainya SUDAH ADA di master,
   field-nya WAJIB lookup (`x-form.searchable-select`, `x-form.select`, atau
   picker modal), bukan teks bebas — tujuannya menekan salah ketik. Teks bebas
   hanya sah untuk: nama/kode master yang sedang DIBUAT di layar itu sendiri,
   catatan/keterangan, dan nilai yang memang belum punya master. Daftar field
   yang masih melanggar: `BACKLOG.md` §21.
3. **Warna lewat token, bukan hex.** Field memakai `border-field-border`,
   `bg-field-soft`, `bg-field-badge`, `focus:border-field-focus`,
   `bg-accent-soft` (didefinisikan di `resources/css/app.css` `@theme`,
   dicampur dari `--color-accent` via `color-mix`). Karena
   `theme-vars.blade.php` menimpa `--color-accent` di `:root`, **12 kombinasi
   tema `sys_theme` otomatis memberi corak yang sama ke semua field tanpa
   satu baris pun warna per-tema**. JANGAN tulis hex abu-abu/biru literal lagi
   di komponen form atau layar form.
   - Catatan teknis (jangan terjebak saat menguji): nilai `color-mix` pada
     custom property disubstitusi di tempat property itu **dideklarasikan**
     (`:root`), bukan di tempat dipakai — override `--color-accent` di elemen
     biasa TIDAK akan mengubah token turunan. Uji dengan override di `:root`.

**Bukti render 3 corak:** `docs/mockups/bukti_komponen_input_tema.jpg`
(+ `.html` per tema, dibangkitkan dari komponen sungguhan, bukan gambar tiruan).

---
## 2. CSS terkait — WASPADAI `.grid { grid-cols-4 }` unlayered di app.css

Kalau bikin/ubah grid di dalam layar form (mis. Header Dokumen multi-kolom),
**JANGAN** pakai `class="grid grid-cols-N"` (Tailwind mentah) — `app.css` baris
~60 punya `.grid { @apply grid grid-cols-4 gap-x-3.5 gap-y-2.5; }` yang
**unlayered** (di luar `@layer` Tailwind manapun), jadi SELALU menang atas
`grid-cols-N`/`lg:grid-cols-[...]` Tailwind apa pun, berapa pun urutan/
spesifisitasnya (sama mekanisme seperti reset `input{width:full}` di
`GRID_UI_STANDARD.md` §6, dikonfirmasi lewat inspeksi CSS ter-compile).

**Pakai konvensi yang sudah ada:**
- Butuh 3 kolom → `class="grid three"` (bukan `grid grid-cols-3`).
- Butuh 2 kolom → `class="grid two"`.
- Butuh split lebar tetap (mis. `1fr` + sidebar `320px`, BUKAN kolom sama rata)
  → jangan pakai `class="grid ..."` sama sekali (tidak ada modifier untuk pola
  ini) — pakai **flexbox**: `class="flex flex-col lg:flex-row gap-4"` pada
  wrapper, `class="lg:flex-1 min-w-0"` di kolom lebar, `class="lg:w-[320px]
  lg:shrink-0"` di kolom sempit. Lihat `co/entry.blade.php` sebagai referensi
  (wrapper KIRI/KANAN).
- Butuh 4 kolom → `class="grid grid-cols-4 gap-3"` Tailwind mentah **kebetulan
  aman** (angkanya cocok dengan default `.grid`), tapi tetap berisiko/rapuh
  kalau nanti default `.grid` diubah — pertimbangkan pakai `class="grid"` polos
  saja (tanpa `grid-cols-4` tambahan, karena itu sudah default-nya).

**Technical debt diketahui (belum diaudit penuh):** pola "`class="grid"` +
`grid-cols-N` Tailwind mentah tanpa `.two`/`.three`" ditemukan di puluhan file
blade lain di luar cakupan sesi ini (kebanyakan pakai `grid-cols-4` yang
kebetulan cocok, jadi belum kelihatan rusak). Audit lintas-modul menyusul kalau
diminta user secara eksplisit — jangan ubah diam-diam di luar layar yang sedang
dikerjakan.

---

## 3. Komponen Input — acuan `docs/mockups/UIUX_components_part1.html`

> Ditulis 2026-09-02 (keputusan user) setelah date/time picker dibangun & di-
> rollout ke semua field date/datetime aplikasi. Mockup ini berisi 21 pola
> komponen input; semua didaftarkan di sini sebagai target standar — **bukan**
> berarti semua sudah diimplementasikan (lihat status per baris). Kalau bikin
> layar baru yang butuh salah satu jenis input ini, **cek dulu tabel ini** —
> kalau statusnya ✅ pakai komponennya, kalau 📋 pola acuan yang belum
> diimplementasi, contek perilaku dari mockup (jangan bikin native
> `<input type="...">` polos begitu saja tanpa mempertimbangkan pola ini).

Semua komponen kustom di sini pakai **Tailwind CSS + Alpine.js murni**
(`Alpine.data(...)` di `resources/js/*.js`, didaftarkan lewat listener
`alpine:init` — pola yang sama dengan `grid/action-dropdown.blade.php`), **tanpa
library JS eksternal** (tidak ada flatpickr/select2/dsb. — konsisten dengan
keputusan "simple, Tailwind" 2026-09-02). Binding ke Livewire selalu lewat
`<input type="hidden" wire:model="...">` yang di-set nilainya oleh Alpine lalu
di-`dispatchEvent(new Event('input'/'change'))` — bukan `wire:model` langsung
di elemen custom (hindari gotcha "Livewire `<select>` morph bug", lihat memory).

| # | Komponen (nama di mockup) | Status | Implementasi / pola acuan |
|---|---|---|---|
| 1 | **Date picker** (kalender popover) | ✅ **Implemented** | `<x-form.date-picker mode="date">` — `resources/views/components/form/date-picker.blade.php` + `resources/js/date-picker.js` (`Alpine.data('tmDatePicker')`). Dipakai di semua field tanggal (rollout 2026-09-02, ~84 titik lintas modul). |
| 2 | **Datetime picker** (kalender + jam:menit) | ✅ **Implemented** | Komponen sama, `mode="datetime"` — tambah 2 `<select>` Jam/Menit (kelipatan 5 menit) di bawah kalender. Dipakai di `inventory/stock-adjustment.blade.php` (opn_start/opn_stop). |
| 3 | Range date picker (dari–sampai + hint durasi) | 📋 Pola acuan | Belum ada komponen gabungan; saat ini 2× `<x-form.date-picker>` berdampingan (`co/delivery-plan.blade.php`, filter laporan). Kalau nanti butuh hint durasi otomatis (spt "Durasi: 6 Tahun 7 Bulan" di mockup), tambahkan sebagai prop `:hint-duration` di `date-picker.blade.php`, jangan bikin komponen baru terpisah. |
| 4 | Textarea auto-height + case toggle (aa/Aa/AA) | ✅ **Implemented & WAJIB** | Case toggle sudah masuk ke `x-form.input` (type=text) & `x-form.textarea` sendiri — otomatis nyala, bukan opt-in. Lihat §1b di bawah. `x-form.case-toggle-input` (versi lama berbasis hidden input) tetap ada utk Component Library, TAPI layar baru pakai `x-form.input`/`x-form.textarea` biasa. |
| 5 | Searchable location/entity dropdown (ketik utk filter) | 📋 Pola acuan | Pola: `<button>` trigger + panel absolute berisi `<input>` search + list hasil filter live (Alpine `x-model` + computed filter, atau fetch server via Livewire kalau datanya besar). Mirip `custom-dropdown-btn`/`custom-dropdown-panel` di mockup. |
| 6 | Password + eye toggle | 📋 Pola acuan | Toggle `type="password"`↔`type="text"` + ganti ikon 👁️/🙈 via Alpine `x-data="{ show: false }"`, `:type="show ? 'text' : 'password'"`. Sederhana, prioritas rendah (browser native sudah punya reveal di sebagian OS). |
| 7 | Color picker + hex code display | 📋 Pola acuan | `<input type="color">` native + `<span>` display hex uppercase via Alpine `x-model.fill`. Belum ada use-case di aplikasi ini (tak ada field warna). |
| 8 | Radio button group | ✅ Sudah lazim | Sudah dipakai luas via HTML radio biasa (bukan komponen khusus) — tidak perlu komponenisasi, cukup label+input Tailwind seperti pola mockup. |
| 9 | Toggle switch (Active/Non-Active) | ✅ Ada varian | `x-form.toggle-option`/`toggle-group` sudah ada (dipakai inquiry, dll.) — beda visual dari switch iOS-style mockup tapi fungsinya sama; tidak perlu duplikat komponen. |
| 10 | Checkbox multi-select group | ✅ Sudah lazim | `x-form.checkbox` sudah ada; grup checkbox cukup beberapa instance berdampingan, tidak perlu wrapper khusus. |
| 11 | Range slider + live value display | 📋 Pola acuan | `<input type="range">` native + `<span x-text>` live value via Alpine `x-model`. Belum ada use-case (field numerik di app ini semua pakai input angka langsung, bukan slider). |
| 12 | Dropdown dgn tick mark (multi-select bahasa) | 📋 Pola acuan | Sama pola dgn item 5 tapi tiap baris punya checkbox sendiri (bukan single-select). Kandidat pakai: field multi-value spt "Kemampuan Bahasa"/tag kategori — belum ada di app ini. |
| 13 | OTP 6-digit auto-focus boxes | 📋 Pola acuan | 6× `<input maxlength="1">` + Alpine auto-`focus()` ke box berikut saat isi, ke box sebelumnya saat Backspace kosong. Kandidat: verifikasi 2FA kalau nanti dibutuhkan (belum ada). |
| 14 | Star rating interaktif 1–5 | 📋 Pola acuan | 5× `<span>` bintang, Alpine `x-data="{ value:0, hover:0 }"`, `@click`/`@mouseover` toggle class aktif. Belum ada use-case (tak ada fitur rating di app ini). |
| 15 | Masking format (NIK 6.6.4 digit) | ✅ Sudah ada | Sudah diimplementasi khusus per field (`master/employee.blade.php` NIK KTP) via Alpine `@input` format manual — pola sama dgn mockup, tak perlu komponen generik karena format masking beda-beda per jenis dokumen. |
| 16 | Phone + country code selector | 📋 Pola acuan | Prefix `<button>` (bendera+kode) + dropdown searchable (pola sama item 5) + `<input>` nomor. App ini saat ini semua nomor HP lokal (+62 implisit) — belum perlu multi-negara. |
| 17 | Currency-prefixed valuation input | 📋 Pola acuan | Sama pola item 16 tapi prefix simbol mata uang. App ini pakai Rupiah saja di hampir semua field nominal — belum ada kebutuhan multi-currency di form (kurs ada di `fin_currency` tapi bukan di form transaksi harian). |
| 18 | Decimal input auto `.00` on blur + unit label | 📋 Pola acuan | `@blur="value = parseFloat(value \|\| 0).toFixed(2)"` + `<span class="unit-label">` absolute-positioned di kanan input (kg, cm, dll.) — belum ada komponen generik, field berat/dimensi saat ini pakai `x-form.input type="number"` polos. |
| 19 | Integer input + spinner ▲▼ | 📋 Pola acuan | `<input>` + 2 tombol kecil kanan (increment/decrement), Alpine `x-data="{ value }"`. Kandidat: qty di form yang belum pakai spinner (mayoritas masih native `type="number"`, cukup untuk sekarang). |
| 20 | Drag & drop file upload | ✅ Ada varian | Upload file di app ini sudah pakai `wire:model` file input Livewire standar (native, bukan drag&drop kustom) — cukup untuk kebutuhan saat ini (foto profil karyawan, dokumen self-service, dst.), belum perlu drag&drop kustom. |
| 21 | Split action button (main + dropdown menu) | ✅ Ada varian | `grid/action-dropdown.blade.php` sudah mengisi kebutuhan serupa (aksi per-baris grid) meski beda konteks (bukan tombol Simpan utama) — tidak perlu duplikat. |
| 22 | QR code generate + modal preview | 📋 Tidak relevan | Spesifik demo mockup (encode seluruh form ke QR) — tidak ada use-case di ERP ini, tidak perlu diimplementasi. |

**Cara pakai `x-form.date-picker` (§1–2):**

```blade
<x-form.date-picker label="Tanggal Dokumen *" wire:model="doc_date" :disabled="$readOnly" name="doc_date" />

{{-- mode datetime --}}
<x-form.date-picker mode="datetime" label="Mulai Hitung *" wire:model="opn_start" />

{{-- dialek legacy (form lama spt inquiry/auth) --}}
<x-form.date-picker legacy="field" label="Tanggal lahir" wire:model="birthDate" name="birthDate" />

{{-- baris tabel padat: custom class nempel ke elemen visible, bukan hidden input --}}
<x-form.date-picker value="{{ $item->due_date?->format('Y-m-d') }}" :disabled="..."
    wire:change="updateItem({{ $item->id_item }}, 'due_date', $event.target.value)" class="!py-1 text-[11px]" />
```

Value yang dikirim ke `wire:model`/`wire:change` **selalu ISO** (`Y-m-d` untuk
`mode="date"`, `Y-m-d H:i:s` untuk `mode="datetime"`) — cocok langsung dgn cast
Carbon/date Eloquent, tidak perlu parsing tambahan di Livewire component.
Tampilan yang dilihat user ("2 Sep 2026") murni kosmetik di input kedua
(readonly, tanpa `wire:model`) — jangan bingung kalau baca DOM dan lihat 2
`<input>` bertumpuk (1 hidden berisi ISO, 1 visible berisi teks manusiawi).

**Field time-only** (mis. jam wawancara `recruitment/interview-schedule.blade.php`)
**sengaja TIDAK ikut migrasi ini** — native `<input type="time">` sudah cukup
baik UX-nya di browser modern, beda kasus dari date/datetime yang formatnya
ambigu lintas locale.

**Sengaja DIKECUALIKAN dari migrasi date-picker:** 2 sel inline-edit
dblclick-to-reveal di `production/mps-spreadsheet.blade.php` (target_start/
target_finish) & `production/wo-entry.blade.php` (baris 1021/1034, pola sama)
— itu bagian dari standar spreadsheet-editable cell (`spreadsheetgridstandard.md`),
beda paradigma interaksi (dblclick reveal native input, langsung commit on
blur) dari popover date-picker (klik buka, pilih, popover nutup). Native
`type="datetime-local"` tetap dipakai di situ.

---

## 4. Scrollbar tipis standar — class `.tm-scroll` (komponen bersama dgn Grid)

**(2026-09-05)** Sama komponen dengan `docs/griduistandard.md` §13 — 1
definisi CSS tunggal di `resources/css/app.css` (class `.tm-scroll`), dipakai
lintas Grid **dan** Form supaya scrollbar-nya konsisten & tidak ada yang
balik ke scrollbar tebal bawaan browser. Jangan buat aturan scrollbar
terpisah khusus Form — tambahkan `tm-scroll` ke elemen yang sudah
`overflow-y-auto`/`overflow-x-auto`, definisi warnanya otomatis ikut (terang
via `--color-muted`/`--color-accent`, atau gelap kalau berada di dalam
`.shell` via `--side-hover`/`--side-muted`).

Belum ada popover/panel Form yang benar-benar overflow saat ini (popover
`date-picker.blade.php` masih `overflow-hidden` murni untuk sudut
membulat, isinya muat tanpa scroll) — tapi begitu komponen §3 yang scroll
beneran dibangun (dropdown searchable item 5, dropdown tick-mark item 12,
dst.), WAJIB pasang `tm-scroll` di panel/list hasilnya, contek pola
`components/grid/column-modal.blade.php`.
