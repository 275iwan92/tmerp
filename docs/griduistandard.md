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

## 9. Grid master-detail — expand-row untuk preview item, tanpa pindah halaman

**(2026-08-24)** Instruksi user: grid dokumen yang punya baris anak (item/line) --
CO, Quotation, PO, dll -- harus gampang diintip detailnya dari grid, tidak
cuma nama+total. Dicek dulu ke pola ERP besar (ScriptCase Grid Report versi
lama kita sendiri: baris expand nampilin sub-tabel item; Odoo one2many list
preview; SAP ALV drill-down; Dynamics 365 sub-grid) -- semua konvergen ke pola
yang sama: **baris master + chevron toggle -> baris anak (nested table)
inline**, bukan modal, bukan pindah halaman. Referensi implementasi:
`resources/views/pages/co/list.blade.php`.

- Kolom chevron **paling kiri**, sebelum kolom identitas, lebar sempit
  (`w-[32px]`), tanpa label header. Isi `▸` (tertutup) / `▾` (terbuka).
- State expand disimpan per-baris di komponen (`public array $expanded`,
  simpan PK yang lagi kebuka) -- boleh lebih dari satu baris terbuka
  bersamaan, tidak exclusive/accordion.
- `wire:click.stop="toggleExpand($id)"` di tombol chevron -- **wajib** `.stop`
  supaya tidak ikut memicu `wire:dblclick` buka halaman entry di baris yang
  sama (dua aksi berbeda: klik chevron = intip di tempat, klik 2x baris =
  buka form penuh untuk edit).
- Baris detail = `<tr>` terpisah, `colspan` penuh ke lebar tabel, isi
  nested `<table>` item (kolom relevan: deskripsi, qty, harga, subtotal,
  due date, dst -- ikut kolom yang sama dipakai di tab "Product" pada
  layar entry, jangan mengarang kolom yang tidak ada di skema).
- **Query**: eager-load relasi item di komputed grid utama (`->with([...,
  'items' => fn ($q) => $q->orderBy(...)])`) -- jangan N+1 per baris expand.
  Data item sudah ikut ter-load meski belum di-expand (grid CO ukurannya
  wajar/paginated 20 baris, biayanya kecil dibanding N+1 query per klik).
- Ini **preview read-only** -- tidak ada edit/hapus/tambah item dari sini.
  Untuk ubah item, tetap lewat ↗ Buka (form entry aslinya).
- Tidak menggantikan kolom Aksi (§5 pola non-Master) atau toolbar (§6) --
  chevron murni tambahan kolom paling kiri, aturan lain grid tetap berlaku
  penuh.

---

## 10. Fasilitas custom view/sort/group-by/export (opsional per grid)

**(2026-08-27, redesain sesudahnya di sesi sama setelah user tunjukkan
referensi ScriptCase — dual-listbox Available/Selected, tombol berlabel
terpisah, bukan 1 ikon serba-guna)**. Keputusan user: grid boleh punya kolom
TERSEDIA jauh lebih banyak dari yang TAMPIL (mis. 15 kolom tersedia, 5 tampil
default, user bisa naikkan lebih — sadar konsekuensi scroll horizontal),
sort per kolom, group-by dari kolom yang lagi tampil, dan export ke
CSV/Excel/PDF. **Ini fasilitas OPSIONAL** — pasang hanya di grid yang benar
dari sononya punya banyak kolom bermakna DAN yang secara aktif diminta user,
bukan default wajib di semua grid. Sejauh ini terpasang di 2 grid:
`master/raw-material/index.blade.php` (pilot, 15 kolom dari skema `mat_raw`)
dan `co/list.blade.php` (rollout ke-2, Status & Net Receivable jadi kolom
anchor tetap — tak ikut disembunyikan, karena itu info finansial inti
worklist Sales).

**Konflik dengan §6 (toolbar terkunci)**: kontrol Kolom/Sort/Export TIDAK
BOLEH jadi elemen toolbar baru sembarangan (search + New Record tetap
satu-satunya isi baris toolbar aslinya). Solusinya: **3 tombol berlabel
kecil** ("☰ Kolom", "↕ Sort", "⬇ Export") dikelompokkan dalam 1 blok
`border-r` di antara search dan "+ New Record" ("+ New Record" tetap di
ujung paling kanan) — beda kategori visual dari search/New-Record, masing2
buka modalnya sendiri (bukan 1 panel serba-guna).

**Implementasi**: `App\Livewire\Concerns\HasCustomizableGrid` (trait, BUKAN
child Livewire component — state-nya erat kaitan dgn query grid induk).
Markup modal **dibagi lewat `@include`, BUKAN `<x-component>`** — anonymous
Blade component (`<x-...>`) punya scope terisolasi (`@props`), TIDAK otomatis
lihat properti Livewire induk (`$showColumnModal` dkk jadi undefined kalau
dipakai) — `@include('components.grid.xxx-modal', [...])` mewarisi scope
current, ini yang benar utk kasus ini. File markup: `resources/views/components/grid/{toolbar-buttons,column-modal,sort-modal,export-modal}.blade.php`
(reusable via `@include`/`<x-grid.toolbar-buttons />` — toolbar-buttons aman
dipakai sbg `<x-...>` krn tak butuh variable scope, cuma `wire:click` string).

Komponen pemakai wajib implementasikan 5 method kontrak: `gridKey()`,
`gridColumnDefs()` (`['key' => ['label' => .., 'sortable' => bool]]`),
`gridDefaultColumns()`, `gridBaseQuery()`, `columnValue($row, $key)` (format
tampil 1 sel, dipakai tabel DAN export — supaya angka/tanggal yang diexport
konsisten dgn yang terlihat di grid, bukan raw value). Preferensi (kolom
tampil + sort + group-by, BUKAN kolom export — lihat di bawah) tersimpan per
user PER grid di `tm2sys.sys_grid_preference`.

- **Modal Kolom**: dual-listbox (Tersedia ↔ Tampil), klik nama kolom
  pindah sisi, `»`/`«` pindah semua/kosongkan. State kerja
  (`columnModalSelected`) TERPISAH dari `gridVisibleColumns` sampai tombol
  "Terapkan" diklik — "Batal" beneran batal, tidak ada yang ke-apply
  diam-diam per klik (beda dari draf checkbox pertama yg langsung apply).
  Group-by (dari kolom yg lagi dipilih di modal ini) & tombol Reset ada di
  modal yang sama.
- **Modal Sort**: daftar kolom (dari yang tampil, kolom `sortable: false`
  otomatis tak muncul) + toggle Asc/Desc, SATU kolom aktif sekaligus (bukan
  multi-kolom — keputusan user eksplisit, meski ScriptCase punya multi-sort).
  Icon `↕`/`▲`/`▼` di judul kolom tabel tetap ada sbg shortcut cepat (klik
  langsung toggle 3-arah asc→desc→none), tak perlu buka modal.
- **Group-by**: HANYA dari kolom yang lagi tampil, render sbg baris header
  grup sebelum anggotanya — per-halaman-paginasi saat ini, BUKAN agregat
  lintas semua halaman (batasan v1 disengaja).
- **Modal Export**: pilih format (Excel/CSV/PDF) + dual-listbox kolom KHUSUS
  export (`exportColumns`, defaultnya ikut kolom yg lagi tampil tapi BOLEH
  beda — tak ikut tersimpan ke `sys_grid_preference`, reset tiap buka modal).
  Excel: `maatwebsite/excel` (`App\Exports\GenericGridExport`, 1 class
  generik dipakai ulang). CSV: native `fputcsv`. PDF: `barryvdh/laravel-dompdf`
  — **dibatasi 200 baris** (`HasCustomizableGrid::PDF_ROW_LIMIT`), dompdf
  boros memori luar biasa utk tabel besar (500 baris × 6 kolom sempat OOM di
  512MB) — PDF memang bukan format yang cocok utk dump ribuan baris, dikasih
  peringatan di halaman PDF-nya kalau data terpotong. Data lama hasil migrasi
  disaring lewat `iconv('UTF-8','UTF-8//IGNORE',...)` sebelum masuk PDF —
  byte non-UTF-8 di kolom teks bebas bikin dompdf gagal total.
- **Gotcha test**: `Livewire::test()->call('exportGridPdf'/'runExport')`
  bisa muncul error palsu "Malformed UTF-8" — itu Livewire test harness yang
  gagal proses respons PDF biner (bukan streaming), BUKAN bug produksi.
  Verifikasi export PDF lewat `$test->instance()->exportGridPdf(...)`
  langsung (skip pembungkus `->call()`), cek `str_starts_with($content, '%PDF-')`.
- **Identitas kolom** (avatar+nama+kode, §3) & kolom anchor grid-spesifik
  (mis. Status/Net Receivable di CO) selalu tampil, TIDAK ikut jadi bagian
  kolom yang bisa disembunyikan.
- Minimal 1 kolom kustom wajib tetap tampil (trait menolak hapus kolom
  terakhir, baik di modal Kolom maupun Export).

**Search fleksibel** (referensi ScriptCase, sama sesi): dropdown RINGAN
nempel langsung di search box (`resources/views/components/grid/search-box.blade.php`,
`▾` di ujung kanan input) — beda dari 3 modal Kolom/Sort/Export yang lebih
berat, cukup 2 pengaturan jadi tak perlu modal penuh. Isi: pilih **kolom**
yang dicari (default "Semua Field" — union kolom identitas + kolom
customizable yang `searchable`, lihat `gridIdentitySearchColumns()`) dan
**kondisi**: Mengandung / Diawali / Sama Dengan / Tidak Mengandung.

- **Kolom identitas HARUS didaftarkan lewat `gridIdentitySearchColumns()`**
  (override di komponen), bukan cuma `gridColumnDefs()` — identitas (nama+
  kode) tak pernah masuk `gridColumnDefs()` krn tak boleh disembunyikan
  (§3), tapi tetap harus bisa dicari (itu justru target pencarian paling
  umum).
- **"Sama Dengan" WAJIB `CAST(col AS CHAR)`, jangan `where($col, '=', $term)`
  polos** — ditemukan bug nyata 2026-08-27: MySQL diam-diam meng-cast
  string non-numerik ke `0` saat dibandingkan ke kolom numerik/boolean
  (`stat_confirm = 'ANIMAL GLUE'` jadi `stat_confirm = 0`, cocok ke SEMUA
  baris draft). Search multi-kolom heterogen (angka+teks+boolean sekaligus)
  bikin ini gampang lolos tak ketahuan kalau cuma dites 1 kolom.
- **Kolom relasi CROSS-DATABASE tak bisa pakai `whereHas()` bertingkat**
  (mis. `co_order`→`quo_quotation` di tm2sdi →`cust_customer` di tm2mst) --
  Eloquent membangun 1 EXISTS subquery bersarang di KONEKSI YANG SAMA
  dengan query induk, error "table doesn't exist" begitu subquery
  menyentuh tabel di DB lain. Ini TERNYATA sudah jadi bug lama di kode CO
  asli (`orWhereHas('quotation.customer', ...)` sejak awal dibangun),
  baru ketahuan sekarang krn baru dites sungguhan. Fix: override
  `gridCrossDbSearchResolvers()` — resolusi manual 2+ tahap ("query
  terpisah, cocokkan di PHP", CLAUDE.md §2): cari ID yang cocok di DB
  tujuan, lalu `whereIn` berantai balik ke PK grid induk. Referensi
  implementasi: `co/list.blade.php` (`quotation.customer.customer_name`).
  **Kalau bikin grid baru dengan kolom identitas dari relasi lintas-DB,
  cek dulu apakah itu whereHas 1-tingkat same-DB (aman) atau bertingkat/
  lintas-DB (butuh resolver) — jangan asumsikan whereHas selalu aman.**
- **Posisi dropdown pakai `x-teleport="body"` + `position:fixed` dihitung
  manual (`getBoundingClientRect()`), BUKAN `position:absolute` biasa** —
  bug nyata 2026-08-27: toolbar induk pakai `overflow-x-auto` (§6, jaga-jaga
  layar sempit), yang MEMOTONG `position:absolute` (CSS: set overflow-x ke
  selain visible bikin overflow-y ikut jadi auto, bukan cuma horizontal).
  Dropdown kepotong/kegencet, susah diklik. `x-teleport` (Alpine core, tanpa
  plugin) pindahkan dropdown ke `<body>`, lepas dari batasan leluhur
  manapun. Pola sama berlaku utk floating panel LAIN yang nempel ke elemen
  di dalam toolbar `overflow-x-auto` — modal penuh (`fixed inset-0`, spt
  Kolom/Sort/Export) TIDAK kena masalah ini krn posisinya relatif ke
  viewport langsung, bukan relatif ke elemen di dalam toolbar.

**Pagination footer 3-bagian** (referensi ScriptCase, 2026-08-27): footer
grid diganti dari pagination Livewire default ke `@include('components.grid.pagination-footer',
['paginator' => $this->items])` — kiri = "Go to [nomor halaman]" (input
angka, `wire:change="gotoPage(...)"`) + "View [10/25/50/100]" (pill button,
`setPerPage()`, BUKAN `<select>` — hindari bug morph §Livewire), tengah =
navigasi halaman (first/prev/nomor-halaman via `gridPageRange()`/next/last,
pakai method native `WithPagination`), kanan = `[a – b dari total]`.
`perPage` (default 25) properti trait baru, dipakai di `items()`/`orders()`
computed sbg `->paginate($this->perPage)`.

**Pilot: Aksi pindah ke KIRI + checkbox multi-delete** (2026-08-27) — INI
PENGECUALIAN TERBATAS dari §4 dan §5 (yang LOCKED: Aksi selalu di kanan,
tidak ada checkbox select), bukan aturan baru menggantikan §4/§5. User
eksplisit membatasi scope: **hanya 2 layar pilot** yang sedang aktif
dikerjakan sesi ini — `master/raw-material/index.blade.php` dan
`co/list.blade.php`. **Jangan roll out ke grid lain tanpa ditanya dulu.**

- **Kolom Aksi pindah dari `<td>` terakhir (kanan) ke `<td>` pertama
  (kiri, sebelum kolom identitas)**, lebar tetap `w-[110px]`. Ikon
  diperbesar dari `w-7 h-7` (§5) jadi **`w-8 h-8` + `border` (selalu
  terlihat, bukan cuma saat hover)** supaya lebih jelas ("icon edit yang
  cukup jelas" — permintaan user eksplisit), `title` tooltip tetap wajib.
  Raw Material: ✎ Edit + ⏻ Toggle-aktif (border warna ikut state, sama pola
  §5). CO: ↗ Buka + ✓ Confirm kondisional (`wire:confirm`, border hijau).
- **Checkbox multi-select — HANYA utk baris yang BELUM confirm/masih
  draft** (`@if (! $m->stat_confirm)` di Raw Material,
  `@if ($o->doc_status === 'DRAFT')` di CO) — baris yang sudah
  confirm/bukan-draft TIDAK dapat checkbox sama sekali, bukan checkbox
  yang di-disable. `selectedRows` (array, trait) + `toggleRowSelection($id)`
  + `clearSelection()`.
- **Bulk-action bar kondisional** (`@if (count($selectedRows))`) muncul di
  atas tabel, bukan di toolbar (§6 tetap terkunci: toolbar tak boleh nambah
  elemen) — isinya jumlah baris terpilih + tombol "Hapus" dengan
  `wire:confirm`.
- **`bulkDelete()` wajib guard di level query, bukan cuma sembunyikan
  checkbox di UI** — checkbox yang tak dirender mencegah user memilih
  baris confirmed dari grid, tapi guard backend (`->where('stat_confirm',
  false)` / `->where('doc_status', 'DRAFT')` di query delete-nya) tetap
  wajib ada sbg lapis kedua. Diverifikasi 2026-08-27: baris confirmed yang
  "dipilih" langsung lewat pemanggilan method (skip UI) tetap TIDAK
  terhapus krn guard query ini.
- Diuji end-to-end (bukan cuma "tak ada exception"): `setPerPage`+`gotoPage`
  mengubah jumlah baris/halaman aktual; `bulkDelete` pada campuran draft+
  confirmed hanya menghapus draft, `selectedRows` ke-clear sesudahnya.

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

Jumlah kotak menyesuaikan kebutuhan data (3-5 umum, bisa lebih). **Pakai
class `.grid` + varian TETAP (`two`/`three`/`five`), BUKAN Tailwind
`grid-cols-X` langsung** — `resources/css/app.css` `.grid{}` **unlayered**
(di luar `@layer`, `@import 'tailwindcss'` di baris 1 file), jadi SELALU
menang atas utility Tailwind manapun termasuk `grid-cols-2 lg:grid-cols-4`,
apa pun urutan/specificity-nya (sama kelas masalah dgn reset `<input>` di
§6). Pola `class="grid grid-cols-2 lg:grid-cols-4 ..."` yang dulu dipakai
di beberapa layar **DIAM-DIAM TIDAK PERNAH JALAN** — ketahuan 2026-08-27
lewat laporan user soal 5 kotak yang wrapping jelek jadi 2 baris (4+1).

**Aturan jumlah kotak per baris (2026-08-27)**: N kotak, MAKSIMAL 5 per
baris, lebar menyesuaikan supaya tetap 1 baris kalau N≤5. Baru wrap ke
baris ke-2 kalau N>5 (maks wajar ~10 kotak/2 baris — di luar itu,
pertimbangkan ulang apakah semua metrik itu memang perlu jadi kotak).

| N kotak | Class |
|---|---|
| 2 | `grid two` |
| 3 | `grid three` |
| 4 | `grid` (default, tanpa varian) |
| 5 | `grid five` |
| 6-10 | **`grid five` juga** (bukan class baru) — 5 kolom, sisa kotak otomatis wrap ke baris ke-2 (mis. 8 kotak = 5+3). Ini yang bikin aturan "maks 5/baris" konsisten: row pertama SELALU max 5, tak peduil N berapa. |

Spacing ikut `mb-3`/`mb-3.5`/`mb-4` sesuai konteks sekitar (gap antar-kotak
sudah baku dari `.grid{}` itu sendiri, `gap-x-3.5 gap-y-2.5`, jangan
ditimpa manual).

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
  lama Customer/Supplier sebelum sesi ini — DIHAPUS). **Kecuali** 2 layar pilot
  checkbox-multi-delete di §10 (khusus baris belum-confirm, scope terbatas,
  jangan roll out ke grid lain tanpa ditanya).
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

**Posisi default: kanan (`<td>` terakhir).** Pengecualian pilot 2 layar
(Aksi di kiri, ikon `w-8 h-8` + border) didokumentasikan di §10 — jangan
generalisasi tanpa ditanya user dulu.

```html
<td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
    <button type="button" wire:click.stop="edit({{ $row->id }})" title="Edit"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
    <button type="button" wire:click.stop="toggleActive({{ $row->id }})"
        wire:confirm="{{ $row->activate ? 'Nonaktifkan' : 'Aktifkan' }} {{ $entityLabel }} {{ $row->name }}?"
        title="{{ $row->activate ? 'Nonaktifkan' : 'Aktifkan' }}"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $row->activate ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
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
  punya kolom `activate`, itu tanda modelnya perlu delta dulu, bukan alasan
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
