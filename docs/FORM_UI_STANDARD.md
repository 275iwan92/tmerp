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

**JANGAN** lagi pakai tombol teks polos (`← Daftar X` dalam satu kotak rata,
`bg-[#43508a] text-[#cfd6ef] text-xs px-3.5 py-1.5 rounded-md`). Standar baru:
pill navy dengan label **"Back"** (Inggris, bukan "Daftar X"/"Menu"/dsb. — label
generik yang sama di semua layar) + lingkaran putih berisi ikon panah, tujuan
spesifik pindah ke `title` (tooltip).

```html
<a href="{{ route('nama.route') }}" title="Kembali ke <tujuan spesifik>"
    class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full no-underline hover:brightness-110">
    <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
</a>
```

Untuk tombol yang trigger-nya method Livewire (bukan navigasi `<a href>`), pola
sama persis, ganti tag jadi `<button type="button" wire:click="...">`:

```html
<button type="button" wire:click="backToGrid" title="Kembali ke Daftar"
    class="ml-auto inline-flex items-center gap-2 pl-3.5 pr-1 py-1 bg-[#43508a] rounded-full cursor-pointer hover:brightness-110">
    <span class="text-[11px] font-bold uppercase tracking-wide text-white">Back</span>
    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-white text-[#43508a] text-xs font-bold">←</span>
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
  ukuran tetap `w-6 h-6`, isi cuma karakter `←` (bukan SVG/emoji lain).
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
