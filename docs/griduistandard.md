# griduistandard.md — Acuan Tampilan Grid (Master & Worklist)

> Ditulis 2026-08-21 setelah menyeragamkan 8 grid Master (Customer, Supplier, UoM,
> Mesin, Shift, Employee, Item Bahan Baku, Warehouse) atas instruksi langsung user.
> Diperluas 2026-08-22 ke grid worklist/transaksi (Imposition Engine, Product
> Costing) — lihat catatan di bawah untuk perbedaan kecil toolbar/Aksi.
> **Baca file ini dulu sebelum membuat atau mengubah layar grid apa pun** — jangan
> menebak polanya lagi dari satu contoh, semua keputusan di bawah ini sudah final
> dan diverifikasi lintas banyak layar berbeda.

Berlaku untuk semua grid **Master Data** (Customer/Supplier/UoM/Mesin/Shift/
Employee/Item/Warehouse/Produk, dan modul master lain yang menyusul) **DAN sejak
2026-08-22 juga grid worklist/transaksi** (mis. Imposition Engine, Product
Costing) — user menegaskan tidak ada pengecualian gaya visual antara Master dan
transaksi, semua grid pakai bahasa desain yang sama (tanpa header halaman, kotak
statistik pastel, kolom identitas avatar+nama+kode, toolbar minimal, ikon Aksi).

**Perbedaan grid Master vs grid Worklist/Transaksi** (bukan soal gaya visual —
itu sama — tapi soal makna toolbar & kolom Aksi, karena worklist tidak selalu
menciptakan record baru dari grid-nya, dan dokumen transaksi tidak punya konsep
aktif/nonaktif seperti master data):

- **Toolbar**: grid Master = search + "+ New Record" (§6), selalu ada tombol
  tambah karena Master selalu bisa dibuat langsung dari grid. Grid Worklist
  **tergantung apakah record-nya memang lahir dari grid ini**:
  - Kalau ya (mis. **Quotation** — dibuat via picker customer di grid ini
    sendiri) → tetap pakai "+ New Record" seperti Master.
  - Kalau tidak (mis. **Imposition/Costing** worklist — sumbernya Inquiry yang
    sudah confirmed; **Customer Order** — lahir dari Quotation yang sudah
    PUBLISHED) → toolbar **search saja**, tanpa tombol apa pun di kanan.
    Kalau perlu, taruh SATU baris catatan pastel (bukan kontrol interaktif)
    yang menjelaskan dari mana record-nya lahir — contoh: grid CO punya
    catatan "CO dibuat dari Quotation yang sudah PUBLISHED...".
- **Kolom Aksi**:
  - Grid Master = 2 ikon wajib (✎ Edit, ⏻ Toggle-aktif) — §5, tidak berubah.
  - **Grid non-Master (worklist/transaksi) TIDAK PERNAH pakai ⏻ Toggle-aktif**
    (tidak ada makna "nonaktifkan" untuk sebuah dokumen transaksi). Sebagai
    gantinya:
    - **↗ Buka** — selalu ada, ikon tunggal ke layar per-record (detail/entry/
      print), sama urutan/posisi kiri seperti ✎ Edit di grid Master.
    - **✓ Confirm** — ikon kedua, **muncul kondisional** hanya saat baris ada
      di status "pra-konfirmasi" (mis. `doc_status === 'DRAFT'`) DAN punya
      minimal 1 item/baris (guard, jangan biarkan confirm dokumen kosong).
      Warna hijau (`text-[#16a34a] hover:bg-[#e9f9ef]`), `wire:confirm` dialog
      sebelum eksekusi (pola sama seperti `wire:confirm` di toggle-aktif Master).
      Method-nya langsung ubah `doc_status` ke status berikutnya (mis.
      DRAFT→PUBLISHED di Quotation, DRAFT→CONFIRMED di Customer Order) — tidak
      perlu re-hitung apa pun di sini, cuma transisi status.
    - Kalau grid-nya murni read/navigate tanpa status yang bisa dikonfirmasi
      (mis. worklist Imposition/Costing — statusnya berasal dari layar lain,
      bukan field langsung di baris ini) → cukup ↗ Buka saja, tidak ada ✓ Confirm.
  - Contoh implementasi rujukan: `resources/views/pages/quotation/list.blade.php`
    (`confirmQuotation()`) dan `resources/views/pages/co/list.blade.php`
    (`confirmOrder()`).
- Kolom identitas (avatar+nama+kode, §3) & interaksi klik-2x-buka (§4) berlaku
  SAMA persis di semua jenis grid (Master maupun Worklist/Transaksi).
- Badge status (mis. `sub_state`/`doc_status` pada Inquiry/Quotation/CO) pakai
  palet pastel §2 yang sama, dipetakan manual per state ke salah satu dari 5
  warna baku — bukan bikin warna baru per status.
- Filter/dropdown/checkbox toolbar **tetap dilarang** di grid Worklist juga,
  sama seperti grid Master (§6) — contoh nyata: worklist Imposition tadinya
  punya checkbox "Tampilkan RECALC", dihapus; worklist Costing tadinya punya
  dropdown status, dihapus. Kalau butuh menyaring, itu pakai search atau
  ditampilkan sebagai badge informatif di kolom, bukan kontrol toolbar baru.

Referensi implementasi acuan untuk grid Worklist: `resources/views/pages/inquiry/imposition-list.blade.php`
dan `costing-list.blade.php` (murni ↗ Buka, tanpa Confirm); `resources/views/pages/quotation/list.blade.php`
dan `resources/views/pages/co/list.blade.php` (↗ Buka + ✓ Confirm kondisional, plus "+ New Record" khusus Quotation).

**(2026-08-22)** `resources/views/pages/pur/pr/index.blade.php`, `pur/po/index.blade.php`,
`pur/pv/index.blade.php` — contoh lain "+ New Record" (record memang lahir dari
picker di grid ini sendiri) TAPI Aksi cuma ↗ Buka, **tanpa** ✓ Confirm — beda
dari Quotation/CO karena transisi DRAFT→SUBMITTED di sini lewat
`FlowEngineService` (butuh validasi & pesan blokir sungguhan di layar entry,
bukan sekadar flip status yang aman diduplikasi ke grid). Kolom identitas PR
pakai **Departemen peminta** (bukan supplier — PR belum punya supplier, baru
dipilih di tahap PO), sedangkan PO/PV pakai nama supplier seperti biasa.

## 8. Layar Approval Center — antrian (grid) + form aslinya, BUKAN ringkasan terpisah

**(2026-08-22)** Dicek dulu ke ERP besar (SAP ME29N, action pane Dynamics 365,
approval di form Odoo, notifikasi Oracle BPM yang membuka dokumen aslinya) —
semua memakai pola yang sama: approver melihat & memutuskan di **dokumen asli**
(field otomatis read-only krn status ≠ DRAFT), bukan layar ringkasan/konsolidasi
custom terpisah. `pur/pr/approval.blade.php` dan `pur/po/approval.blade.php`
sebelumnya adalah layar 2-kolom (list kiri + ringkasan kanan) dengan tombol
Approve/Reject sendiri — **dirombak total**:

- **Approve / Reject / Hold pindah ke form entry aslinya** (`pur/pr/entry.blade.php`,
  `pur/po/entry.blade.php`). Form itu sudah otomatis read-only saat
  `status !== 'DRAFT'` (semua `@disabled($this->po->status !== 'DRAFT')`),
  jadi approver melihat PERSIS dokumen yang sama yang dibuat/diedit pembuatnya
  — tinggal ditambah blok "Keputusan Approval" (Catatan + tombol Hold/Tolak/
  Setujui) yang muncul kalau `$this->pendingApproval` (baris `flow_log`
  terakhir milik user login untuk dokumen ini) tidak null, plus timeline
  "Riwayat Approval" yang selalu tampil begitu ada histori.
- **Layar `*/approval.blade.php` jadi murni GRID antrian** (bukan detail) —
  ikut standar grid biasa di file ini: kotak statistik pastel (Total di
  Antrian / Menunggu Keputusan / Ditahan), kolom identitas (Departemen atau
  Supplier + no. dokumen), klik 2x buka dokumen asli (route entry yang sama
  dipakai pembuat dokumen), Aksi cuma ↗ Buka. **Tanpa "+ New Record"** —
  antrian bukan tempat membuat dokumen baru, dan tidak perlu catatan pastel
  penjelas (beda dari CO) karena sudah jelas dari namanya "Approval Center".
- **Hold = keputusan ketiga** di luar Approve/Reject (mis. approver menahan
  keputusan karena cash flow perusahaan belum siap) — TIDAK mengubah status
  dokumen, dokumen tetap di antrian approver yang sama utk diputuskan lagi
  nanti, cuma tercatat sebagai baris `flow_log` baru (`action='HOLD'`, notes
  wajib diisi). `FlowEngineService::pendingForUser()` sengaja meng-include
  `action IN ('SUBMITTED','HOLD')` supaya dokumen yang di-hold tidak hilang
  dari antrian. Baca `App\Services\Flow\FlowEngineService::hold()` untuk detail.

---

## 1. Tidak ada header halaman

Shell (`layouts.shell`, lihat `docs/customer_fix_nav_address.md` §A) sudah
menampilkan nama halaman lewat tab di atas. Grid **tidak boleh** punya bar
judulnya sendiri (ikon+nama+subtitle+tombol pada background terpisah) — itu
duplikat chrome. Halaman grid langsung mulai dari kotak statistik.

Ini perluasan dari `NAV-SHELL-CONTRACT` yang sudah ada — dulu bar tipis untuk
satu tombol aksi utama masih dianggap boleh, sekarang **tidak lagi**: tombol aksi
utama ("+ New Record") pindah ke ujung kanan baris toolbar (§3), bukan bar sendiri.

---

## 2. Kotak statistik — pastel, bukan gradient mencolok

4 kotak (kadang bisa lebih/kurang sesuai kebutuhan data), `grid grid-cols-2
lg:grid-cols-4 gap-3 mb-3.5` (atau `mb-3`/`mb-4`, ikuti spacing sekitar).

**JANGAN** pakai `bg-gradient-to-br ... text-white` (draf pertama sesi ini, DITOLAK
user — terlalu mencolok, teks putih di atas warna jenuh susah dibaca). **PAKAI**
latar pastel solid + teks gelap senada, selaras dengan navy/biru menu:

```html
<div class="rounded-lg p-3.5 shadow-sm bg-[BG] border border-[BORDER]">
    <p class="text-[10.5px] font-bold uppercase text-[LABEL]">Label</p>
    <p class="text-xl font-extrabold text-[VALUE] mt-0.5">{{ $angka }}</p>
</div>
```

Palet baku (5 warna, pilih sesuai makna metrik — jangan bikin warna baru):

| Makna | bg | border | label (teks kecil) | value (angka) |
|---|---|---|---|---|
| Total / primer | `#eaf2ff` | `#c8ddfb` | `#3b5b99` | `#1d4ed8` |
| Aktif / baik | `#e9f9ef` | `#c8ecd6` | `#2f7a52` | `#16a34a` |
| Dasar / perlu perhatian ringan | `#fef6e7` | `#f5e0b3` | `#8a6d00` | `#b45309` |
| Nonaktif / terblokir / kritis | `#fdecec` | `#f5c6c6` | `#a3453f` | `#c0392b` |
| Metrik tambahan (kategori ke-5) | `#f1ecfc` | `#ddd0f7` | `#6b4fa0` | `#6d28d9` |

Kalau butuh subtitle kecil di bawah angka (mis. "MATERIAL", "RM"), pakai
`text-[10px]` warna senada label (`text-[LABEL]/70` kalau perlu redup sedikit).

---

## 3. Kolom identitas — avatar + Nama (tebal) + Kode (subtitle abu)

**Jangan lagi bikin kolom "Kode" dan "Nama X" terpisah.** Gabung jadi satu kolom
identitas paling kiri:

```html
<td class="px-2.5 py-2 border-b border-[#d7dbe2]">
    <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($row->id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($row->name) }}</div>
        <div>
            <div class="font-semibold text-[13px]">{{ $row->name }}</div>
            <div class="text-[11px] text-muted font-mono">{{ $row->code }}</div>
        </div>
    </div>
</td>
```

Gradient avatar (beda dari gradient kotak statistik yang dilarang di §2 — badge
kecil 32×32px dengan gradient tetap enak dilihat, itu yang ditolak user cuma
kotak statistik besar). Tiap komponen punya method sendiri (single-file component,
tak ada shared partial di codebase ini):

```php
public function avatarInitials(string $name): string
{
    $words = array_values(array_filter(explode(' ', trim($name))));
    if (count($words) >= 2) {
        return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
    }
    return mb_strtoupper(mb_substr($name, 0, 2));
}

private const AVATAR_PALETTE = [
    'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
    'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
];

public function avatarGradient(int $id): string
{
    return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
}
```

`avatarGradient($id)` dipanggil dengan PK baris (auto-increment int) supaya warna
konsisten tiap render tanpa perlu disimpan — modulo warna pool.

**Kalau entitasnya tidak punya kolom kode** (mis. `emp_shift.shift_name` saja,
tanpa `shift_code`) — jangan mengarang kode. Cukup avatar + Nama saja, tanpa
baris subtitle kedua. Jangan pakai field lain (departemen, dsb.) sebagai kode
palsu.

**Kalau kode adalah field utama/pendek** (mis. UoM `uom_code` = "PCS", "KG") dan
nama cuma label deskriptif — pola tetap sama (avatar dari nama, Nama tebal di
atas, Kode mono di bawah), meski secara teknis kode itu PK-nya. Konsistensi
visual menang di sini, bukan "kode itu penting jadi taruh atas".

Kolom lain (departemen, kategori, dsb.) tetap kolom terpisah seperti biasa —
yang digabung HANYA identitas nama+kode.

---

## 4. Interaksi baris: klik = pilih (kalau perlu), klik 2x = buka detail

- **Klik 2x (`wire:dblclick` atau `ondblclick` untuk grid yang edit-nya halaman
  terpisah, bukan modal)** pada baris = **satu-satunya** cara buka form/detail.
  Tambahkan `title="Klik 2x untuk buka detail"` dan `cursor-pointer` pada `<tr>`.
- **Jangan** pasang kolom radio-button atau checkbox select terpisah lagi (pola
  lama Customer/Supplier sebelum sesi ini — DIHAPUS).
- **(Update 2026-08-21)** Karena toolbar sekarang **tidak boleh punya tombol
  aksi sekunder sama sekali** (§6 — direvisi, dulu Supplier/UoM boleh punya
  tombol yang butuh "baris terpilih", sekarang tidak), pola `wire:click="selectRow(...)"`
  untuk single-click-select praktis sudah tidak dipakai lagi di toolbar manapun.
  Kalau suatu saat ada aksi per-baris yang butuh dipicu dari grid (seperti
  UoM's Konversi), taruh sebagai **ikon tambahan di kolom Aksi (§5)**, bukan
  tombol toolbar yang bergantung ke baris terpilih — lihat contoh UoM di §5.
- Tombol ikon di kolom Aksi (§5) pakai `wire:click.stop`/`wire:click.stop` (atau
  event modifier setara) supaya klik ikon tidak ikut memicu select/dblclick baris.

---

## 5. Kolom Aksi — selalu tepat 2 ikon: Edit + Toggle-aktif

```html
<td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
    <button type="button" wire:click.stop="edit({{ $row->id }})" title="Edit"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
    <button type="button" wire:click.stop="toggleActive({{ $row->id }})"
        wire:confirm="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $entityLabel }} {{ $row->name }}?"
        title="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $row->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
</td>
```

- Urutan tetap: Edit (✎) dulu, baru toggle (⏻) — jangan dibalik, jangan diselingi
  tombol lain.
- Ikon TEKS (`Edit`, `Nonaktifkan`) **dilarang** di kolom Aksi grid — selalu ikon
  polos + `title` tooltip.
- Warna toggle berubah sesuai state saat ini (merah = klik untuk nonaktifkan,
  accent/biru = klik untuk aktifkan) — bukan glyph yang berganti (⛔↔✓), cukup
  `⏻` konsisten, warna+tooltip yang bicara.
- **Setiap grid Master wajib punya `toggleActive()` (atau
  `toggleXxxActive()` kalau ada beberapa entitas dalam satu file, mis. UoM punya
  `toggleUnitActive()` + `toggleConversionActive()`)** — kalau modelnya belum
  punya kolom `is_active`, itu tanda modelnya perlu delta dulu, bukan alasan
  melewati kolom Aksi.
- Kalau ada tombol Edit/Aksi lain yang jadi redundan setelah kolom ini + klik 2x
  ada (mis. tombol "✎ Edit" terpisah di toolbar), **hapus yang redundan** —
  jangan biarkan dua cara berbeda untuk hal yang sama.
- **Aksi per-baris lain di luar Edit/Toggle boleh ditambah sebagai ikon ke-3+**
  kalau memang fungsinya melekat ke satu baris tertentu (bukan filter/aksi
  global) — mis. UoM punya ikon 🔁 "Konversi" di kolom Aksi
  (`wire:click.stop="openConversionPanel('{{ $u->uom_code }}')"`) karena
  tombol itu dulu di toolbar butuh "baris terpilih" dulu, sekarang toolbar
  tidak boleh punya tombol begitu (§6) — jadi dipindah ke baris itu sendiri.
  Taruh di kiri Edit/Toggle atau sesuai urutan kepentingan, tetap ikon polos +
  `title` tooltip, tidak ada teks.

---

## 6. Toolbar — HANYA search + "+ New Record", tidak ada elemen lain

**(Direvisi 2026-08-21, menggantikan versi sebelumnya yang masih mengizinkan
filter select/checkbox/tombol sekunder di toolbar.)** User menegaskan secara
eksplisit: toolbar grid Master **hanya boleh berisi dua elemen** — kotak
pencarian dan tombol "+ New Record". Tidak ada elemen ketiga dalam bentuk
apa pun: bukan filter `<select>`, bukan checkbox "sembunyikan nonaktif", bukan
tombol aksi sekunder (Detail/Blokir/dsb.), bukan teks jumlah baris.

```html
<div class="flex items-center gap-2 bg-white border ... px-2.5 py-2 overflow-x-auto">
    <div class="flex items-center gap-1.5 shrink-0">
        <span class="text-sm">🔍</span>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="..."
            class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
    </div>
    <span class="flex-1"></span>
    <button type="button" wire:click="openNew"
        class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded text-xs font-semibold bg-[#2c3e69] text-white border border-[#2c3e69] cursor-pointer">+ New Record</button>
</div>
```

**PENTING — jangan taruh `<input>` sebagai anak langsung dari toolbar flex row.**
`resources/css/app.css` punya reset global `input:not([type=checkbox]):not([type=radio])
{ ... w-full ... }` yang **unlayered** (di luar `@layer`) — di cascade CSS, aturan
unlayered SELALU menang atas utility class Tailwind manapun (termasuk `.w-56`),
apa pun urutan/specificity-nya. Kalau `<input>` jadi anak langsung toolbar,
`w-56`-nya percuma, lebar sungguhan yang dipakai browser tetap `w-full` →
search box melebar penuh, tombol "+ New Record" terdorong keluar layar
(butuh scroll horizontal untuk terlihat — persis bug yang ditemukan user di
Master Bahan Baku 2026-08-21). **Wajib bungkus `<input>` dalam
`<div class="flex items-center gap-1.5 shrink-0">` bersama ikon 🔍** seperti
contoh di atas — div pembungkus ini yang membatasi lebar visual, bukan class
di `<input>` itu sendiri (div tanpa lebar eksplisit + flex-shrink membuat
percentage width `<input>` di dalamnya efektif auto-sized, bukan 100% dari
toolbar). Warna tombol "+ New Record" juga dikunci ke `bg-[#2c3e69]`
(navy — sama seperti Customer/Supplier), bukan `bg-accent`, supaya seragam.

- **`flex-wrap` DILARANG** di baris toolbar — pakai `overflow-x-auto` sebagai
  jaga-jaga di layar sangat sempit, meski dengan cuma 2 elemen ini jarang
  kepakai lagi.
- Search input tetap `shrink-0` + lebar eksplisit (`w-56`/`w-64`, dst.).
- `<span class="flex-1"></span>` sebagai spacer mendorong tombol New Record
  ke ujung kanan.
- Tombol tambah data **SELALU** di ujung paling kanan, teksnya **SELALU**
  `+ New Record` (ASCII `+`, bahasa Inggris, sama persis di semua grid — bukan
  "+ Tambah X", bukan "+ New" saja, bukan nama entitas).
- **Tombol Hapus (delete) DILARANG ada di toolbar grid.** Kalau grid itu
  memang butuh aksi hapus permanen, taruh di panel form/detail (bukan grid).
- **Filter dropdown/checkbox yang sebelumnya ada di toolbar (tipe, kategori,
  divisi, dimensi, sembunyikan-nonaktif, dsb.) DIHAPUS total, bukan
  dipindahkan.** Kalau nanti user minta kemampuan filter itu kembali, itu
  keputusan baru yang perlu ditanyakan dulu (lihat memory
  `feedback_master_grid_ui_standard` untuk daftar filter yang hilang per grid)
  — jangan diam-diam menambah lagi tanpa konfirmasi.
- **Tombol yang butuh "baris terpilih" (mis. dulu Supplier's Detail/Blokir,
  UoM's Konversi) TIDAK BOLEH lagi jadi tombol toolbar.** Kalau aksinya
  melekat ke satu baris, pindahkan ke ikon kolom Aksi (§5) yang menerima kode
  baris itu langsung sebagai parameter — bukan bergantung pada state
  "baris mana yang sedang dipilih".

---

## 7. Ringkasan checklist tiap kali bikin/ubah grid Master

- [ ] Tidak ada `<header>` bar di atas kotak statistik.
- [ ] Kotak statistik pastel (§2), bukan gradient/putih polos.
- [ ] Kolom identitas = avatar + Nama tebal + Kode subtitle (§3), bukan 2 kolom
      terpisah.
- [ ] Baris: `wire:dblclick`/`ondblclick` buka detail; tidak ada radio/checkbox
      select kolom terpisah.
- [ ] Kolom Aksi = 2 ikon wajib (✎ Edit, ⏻ toggle) + ikon tambahan kalau ada
      aksi per-baris lain (mis. 🔁 Konversi), semua `wire:click.stop`, ada
      `title` + `wire:confirm` di toggle.
- [ ] Toolbar HANYA search + "+ New Record" (§6) — tidak ada filter select,
      checkbox, tombol sekunder, atau teks jumlah baris apa pun.
- [ ] `colspan` baris kosong (`@empty`) disesuaikan ulang setelah kolom
      digabung/dihapus — ini yang paling sering kelewat.
- [ ] **`npm run build` dijalankan setelah semua edit selesai.** Project ini pakai
      Vite production build, bukan dev-server hot-reload — class Tailwind baru
      tidak akan tampil di browser sampai di-build ulang. Lihat CLAUDE.md §7.8.
      Jangan anggap `php -l` lolos = tampilan sudah benar, itu cuma cek syntax PHP.
