# SIDEBAR_MENU_STANDARD.md — Acuan Struktur Menu Sidebar per Modul

> Ditulis 2026-08-22 setelah restrukturisasi sidebar Sales (Transaksi dipecah
> 3 sub-grup) dan Inventory (grup laporan diberi nama ringkas + label item
> disamakan dengan nama layar aslinya). **Baca file ini sebelum menambah
> menu baru ke `sys_app_menu` atau menata ulang sidebar modul mana pun.**

---

## 1. Pola wajib: selalu ada grup Input & grup Laporan

Setiap modul (`sys_app_menu.menu_type = 'module'`) yang punya lebih dari
sekadar 1-2 layar **wajib** mengelompokkan child-nya ke dalam minimal 2 grup
(`menu_type = 'group'`):

- **Grup input/transaksi** — layar tempat data dibuat/diubah (form entry,
  approval, worklist). Boleh dipecah jadi beberapa sub-grup kalau jumlahnya
  banyak & punya kategori alami (lihat contoh Sales di bawah) — TAPI setiap
  sub-grup itu tetap kategori "input", bukan campur dengan laporan.
- **Grup Laporan** — layar read-only (print, rekap, grid monitoring). Judul
  grup ini **ringkas, cukup "Laporan"** (bukan "Stok & Laporan", bukan
  "Monitoring & Laporan Penjualan", dst.) — kalau modul itu juga punya layar
  monitoring non-print yang masih masuk akal disebut laporan (grid Daftar
  Sales Order, Monitoring Order), itu tetap masuk grup "Laporan" yang sama,
  tidak perlu grup terpisah "Monitoring" lagi.

**Jangan** taruh item laporan di dalam grup transaksi atau sebaliknya —
walau cuma 1 layar, tetap kelompokkan secara eksplisit supaya polanya
konsisten begitu modul itu tumbuh (item baru tinggal masuk grup yang sudah
ada, bukan menambah grup dadakan tanpa pola).

### Contoh yang sudah diterapkan

**Inventory** (`INV`):
```
Transaksi                    <- grup input
  Stock In / Stock Out / Return from Production /
  Stock Adjustment & Opname / Persetujuan Penyesuaian
Laporan                      <- grup laporan (2026-08-22: dulu "Stok & Laporan")
  Master Stok                <- INV_RAW, live, internal 3 kategori RM/FG/SFG (lihat §5)
  Kartu Stok                 <- INV_CARD, live, internal 3 kategori RM/FG/SFG (lihat §5)
```
**HANYA 2 item, titik** — `INV_FG` ("Stok Barang Jadi"), `INV_HIST` ("Riwayat
Mutasi"), `INV_PERIOD` ("Periode Stok") di-retire (`is_active=false`,
migration `2026_08_22_000006`) 2026-08-22 atas instruksi eksplisit user,
**BUKAN placeholder roadmap yang dibiarkan tampil** — ini PENGECUALIAN dari
aturan umum §7 (NOT_BUILT biasanya tetap tampil abu-abu). Alasan: begitu
Master Stok & Kartu Stok sama-sama dapat selector 3 kategori (RM/FG/SFG,
§5), "Stok Barang Jadi" jadi 100% redundan (fungsinya = Master Stok filter
FG) — membiarkannya tampil sbg placeholder terpisah JUSTRU menyesatkan
(kesannya bakal ada layar lain, padahal sudah tercakup). Kalau modul lain
menghadapi situasi serupa (placeholder yang jadi redundan karena fitur
sejenis sudah tercakup di layar lain), retire juga — jangan otomatis
"biarkan abu-abu" tanpa mengecek dulu apakah placeholder itu masih
mewakili sesuatu yang benar-benar belum ada.

**Sales** (`SALES`) — contoh modul dengan input dipecah beberapa sub-grup
karena kategorinya beda tahap alur (lihat [[project-sidebar-groups-collapsible]]):
```
Inquiry & Quotation          <- grup input (tahap sebelum order)
  Inquiry Product / Imposition Engine / Product Costing / Quotation
Sales Order                  <- grup input (order sudah jadi)
  Customer Order
Delivery                     <- grup input (pengiriman)
  Plan Delivery / Delivery Order
Monitoring & Laporan         <- grup laporan (belum di-generic-kan jadi "Laporan"
                                 saja — evaluasi lagi kalau modul lain sudah konsisten)
```

**Catatan konsistensi**: Sales masih pakai label "Monitoring & Laporan" (label
lama), belum diseragamkan ke "Laporan" polos seperti Inventory. Ini SENGAJA
belum disentuh saat sesi 2026-08-22 (scope waktu itu cuma Inventory) — kalau
menyentuh Sales lagi, pertimbangkan menyamakan ke "Laporan" saja untuk
konsistensi lintas modul, tapi konfirmasi ke user dulu (bukan keputusan
otomatis, karena label itu terlihat langsung oleh user tiap hari).

---

## 2. Nama item laporan = nama asli layarnya, tanpa awalan "Laporan" berulang

Karena grup sudah bertajuk "Laporan", item DI DALAMNYA jangan mengulang kata
itu lagi (`Laporan > Laporan Master Stok` itu redundan). Aturan:

- Pakai potongan nama yang sama dengan `#[Title]`/heading asli layarnya
  (bukan istilah baru yang tidak muncul di mana pun di kode) — supaya user
  yang buka menu & yang lihat judul halaman melihat nama yang sama persis.
  Contoh: layar `inventory/print/master-stock.blade.php` selalu menyebut
  dirinya "Laporan Master Stok" (title bar, header, kop cetak) → label
  sidebar cukup **"Master Stok"** (buang "Laporan" karena sudah jadi nama
  grup), BUKAN label lama "Stok Bahan Baku" yang tidak match nama layarnya
  sama sekali dan menyesatkan (kesannya snapshot RM doang, padahal ini
  rekap periodik lintas item begitu SFG/FG live — lihat komentar migration
  `2026_08_22_000004_rename_inventory_report_menu_labels.php`).
- Kalau namanya sudah istilah baku industri (mis. **"Kartu Stok"** / stock
  card — sudah dipahami universal), tidak perlu diubah meski secara teknis
  tidak persis sama dengan sesuatu di kode.
- Kalau ragu nama sidebar sudah pas atau belum, cek dulu `#[Title]` &
  heading di file layarnya — itu sumber kebenaran, jangan menebak dari nama
  kode/kolom.

---

## 3. Grup sidebar bisa diciutkan (built-in, generik semua modul)

Sejak 2026-08-22, SETIAP caption grup di sidebar (`.side-cap`) otomatis jadi
tombol yang bisa diklik untuk ciutkan/buka (Alpine `x-show` + chevron) — lihat
`resources/views/pages/main-menu.blade.php` (loop `sidebarGroups`) dan
[[project-sidebar-groups-collapsible]]. Ini BUKAN sesuatu yang perlu
diaktifkan per modul — begitu sebuah grup mendaftar di `sys_app_menu`, dia
otomatis collapsible. Tidak perlu kerjaan tambahan saat menambah grup baru.

---

## 5. Inventory — 3 kelas stok = 3 MENU terpisah, di Laporan MAUPUN Input

**(Direvisi 2026-08-22, sudah 2x dikoreksi user — baca versi final ini, jangan
ikuti draft lama.)** 3 kelas stok WAJIB tercermin sbg menu terpisah di sidebar
— bukan toggle/tab tersembunyi di dalam 1 halaman. Alasan eksplisit dari
user: recordnya memang masuk ke TABEL FISIK yang berbeda per kelas, jadi
strukturnya harus kelihatan di menu, bukan disembunyikan.

1. **Bahan Baku & Bahan Pembantu** (`stock_class = 'RM'`) — master
   `MRawMaterial` (`m_raw_material`), sumber transaksi `rm_stock`/`rm_stock_d`/`rm_stockcard`.
2. **Barang Jadi** (`stock_class = 'FG'`) — master `ProdProduct` (`prod_product`),
   sumber transaksi `fg_stock`/`fg_stock_d`/`fg_stockcard`.
3. **Barang Setengah Jadi** (`stock_class = 'SFG'`) — master `ProdPart`
   (`prod_part`), sumber transaksi `sfg_stock`/`sfg_stock_d`/`sfg_stockcard`.

### Struktur final (Inventory, sudah diterapkan 2026-08-22)

Shell cuma render 2 level (Modul → Grup → Item flat, TIDAK ada sub-grup
bersarang — lihat `main-menu.blade.php` `sidebarGroups` loop), jadi "Input
dibagi 3" dan "Laporan dibagi 3" masing-masing diwujudkan sbg **3 caption
grup terpisah**, total 6 grup:

```
Input — Bahan Baku & Bahan Pembantu     <- INV_TRX_RM
  Stock In / Stock Out / Return from Production /
  Stock Adjustment & Opname / Persetujuan Penyesuaian   (semua NOT_BUILT)
Input — Barang Jadi                     <- INV_TRX_FG   (5 item sama, NOT_BUILT)
Input — Barang Setengah Jadi            <- INV_TRX_SFG  (5 item sama, NOT_BUILT)
Laporan — Bahan Baku & Bahan Pembantu   <- INV_RPT_RM
  Master Stok (INV_RAW_RM) / Kartu Stok (INV_CARD_RM)   (live)
Laporan — Barang Jadi                   <- INV_RPT_FG   (Master Stok/Kartu Stok, live)
Laporan — Barang Setengah Jadi          <- INV_RPT_SFG  (Master Stok/Kartu Stok, live)
```

**Laporan (live, pola rujukan)**: `stock-card.blade.php`/`master-stock.blade.php`
TETAP 1 file Livewire per jenis laporan (reuse kode penuh, TIDAK di-copy 3x)
— yang berubah cuma route-nya: `{category}` sekarang **wajib jadi segmen URL**
(`/inventory/print/master-stock/{category}`), bukan Livewire state yang
di-toggle via `wire:click`. 6 menu code (`INV_RAW_RM/FG/SFG`,
`INV_CARD_RM/FG/SFG`) di `itemRoutes()` (`main-menu.blade.php`) semua
menunjuk ke route yang sama, cuma parameter `category` beda. Tombol pilihan
kategori di dalam halaman (untuk lompat cepat antar kelas tanpa balik ke
sidebar) jadi `<a href="{{ route(..., ['category'=>$code]) }}">` — navigasi
halaman penuh, BUKAN `wire:click` state change, supaya URL & isi halaman
selalu sinkron (bisa di-bookmark, tidak nyasar kalau di-refresh).
`v_inv_movement` (view `UNION ALL` yang menormalkan `item_id`/`item_code`/
`item_name` lintas 3 kelas) tetap dipakai utk BACA; utk TULIS transaksi baru
tetap ke tabel per-kelas yang sesuai (`rm_stock_d` utk RM, dst.), bukan ke view.

**Input (belum dibangun, placeholder murni)**: 5 jenis transaksi (Stock In/
Stock Out/Return from Production/Stock Adjustment & Opname/Persetujuan
Penyesuaian) di-duplikasi jadi 15 baris `sys_app_menu` (5 × 3 kelas, semua
`NOT_BUILT`) — BUKAN 5 layar yang di-reuse pakai route param seperti
Laporan, karena layarnya memang belum ada sama sekali; keputusan reuse-code
vs 3-route-param yang sama seperti Laporan baru relevan begitu Stock In/Out
benar-benar digarap. Label sengaja disamakan lintas kelas utk sekarang (mis.
"Return from Production" dipakai identik di RM/FG/SFG) — **exact kesesuaian
istilah per kelas belum dikonfirmasi user** (mis. apakah "Return from
Production" utk FG/SFG maknanya sama dgn RM, atau harus jadi "Production
Output/Receiving" — beda konsep: RM = sisa material balik ke gudang, FG/SFG
= barang jadi/setengah-jadi KELUAR dari produksi masuk stok). Jangan
asumsikan label ini final saat mulai membangun — konfirmasi dulu ke user.

**Kode menu**: grup pakai pola `INV_{TRX|RPT}_{RM|FG|SFG}`, item Laporan
pakai `INV_{RAW|CARD}_{RM|FG|SFG}`, item Input pakai `INV_{RM|FG|SFG}_ITEM{n}`
(1..5, urutan sama dgn daftar 5 jenis transaksi di atas) — migration
`2026_08_22_000007_split_inventory_menu_by_stock_class.php` adalah rujukan
lengkapnya kalau perlu nambah/ubah lagi.

---

## 6. Procurement — Purchase Receive punya 3 jenis penerimaan berbeda

Ditulis 2026-08-22 dari instruksi user, **belum dibangun** — layar
`pur.pv.list`/`pur.pv.entry` yang sudah ada (menu `PROC_RCV`, live) baru
menangani jenis #1 saja. Catatan ini acuan scope kalau/ketika 2 jenis
lainnya digarap:

1. **Penerimaan dari PO** (sudah ada) — barang masuk dari supplier atas
   dasar Purchase Order yang sudah di-approve. Ini yang selama ini disebut
   "Purchase Receive" / PV / SPB (`pur.pv.*`, multi-PO per dokumen). Masuk
   ke stok **RM** (`stock_class='RM'`) seperti biasa.
2. **Penerimaan dari Customer (bahan milik customer)** — customer order
   dengan syarat bawa bahan baku sendiri (consignment/customer-supplied
   material — lihat juga `prod_product`/inquiry punya konsep
   `material_source` OWN/CUSTOMER, kolom ini sudah ada di `iqp_part_material`).
   Barang masuk BUKAN dari supplier & BUKAN atas dasar PO — dari Customer
   Order langsung. **Masuk ke "stok khusus"** (2026-08-22, klarifikasi
   user) — BUKAN dicampur ke pool RM umum milik perusahaan, karena bahan
   ini secara hukum/akuntansi tetap milik customer (consignment), bukan
   inventory perusahaan. Kemungkinan butuh `warehouse_type` atau flag
   kepemilikan tersendiri di `m_wh_loc`/`rm_stock_d` (mis. `ownership_type`
   OWN/CUSTOMER) supaya laporan RM tidak salah menghitungnya sebagai aset
   sendiri — desain persis kolomnya belum diputuskan, perlu dibahas lagi
   saat digarap, jangan diasumsikan sama dengan RM biasa.
3. **Penerimaan dari Subcon (barang setengah jadi kembali dari pihak
   ketiga)** — pekerjaan yang di-outsource ke subkontraktor (mis. proses
   finishing/laminasi/dsb. yang tidak dikerjakan sendiri), barang yang
   kembali adalah **SFG** (`stock_class='SFG'`, bukan RM maupun FG,
   dikonfirmasi user 2026-08-22). Ini beririsan dengan kelas stok SFG di
   §5 di atas — penerimaan dari subcon adalah salah satu SUMBER transaksi
   yang menulis ke `sfg_stock`/`sfg_stock_d`/`sfg_stockcard`.

**Jangan gabung ketiganya jadi satu form generik "Penerimaan Barang"** —
sumber datanya beda (PO vs Customer Order vs Subcon PO/kontrak kerja),
validasi & dokumen pendukungnya beda, dan siapa yang bertanggung jawab atas
barangnya juga beda (supplier vs customer vs subcontractor). Perlakukan
sebagai 3 alur/layar terpisah di bawah grup input Procurement, bukan 3
tab dalam satu form.

### 6a. Alur konfirmasi: semua jenis penerimaan otomatis jadi "Stock IN" setelah confirm + approval atasan

Ditegaskan user 2026-08-22: ketiga jenis penerimaan di atas (PO/Customer/Subcon)
sama-sama berujung ke **Stock IN** secara otomatis begitu dokumennya
di-confirm oleh pembuatnya DAN disetujui atasan (approval) — bukan proses
manual terpisah "habis approve, baru input Stock In sendiri".

**Ada referensi implementasi nyata dari sistem lama, ditemukan saat cek
`database/legacy/tminv_20260722_2018_sd.sql`**: 3 SQL TRIGGER —
`trg_rm_stock_confirm` (baris ~1347), `trg_fg_stock_confirm` (baris ~1278),
`trg_sfg_stock_confirm` (baris ~1430) — masing-masing `AFTER UPDATE` pada
`rm_stock`/`fg_stock`/`sfg_stock`. Begitu `stat_confirm` berubah dari 0→1
(dan `stat_cancel=0`), trigger otomatis loop semua baris `*_stock_d` dokumen
itu dan INSERT baris `*_stockcard` baru (saldo awal diambil dari stockcard
terakhir item+lokasi yang sama, saldo akhir = saldo awal ± qty sesuai
`doc_type`/`doc_cat` — IN/OUT/ADJ/RETURN/BB berbeda tanda).

**PENTING — trigger ini TIDAK ikut ter-import ke database live** (dicek
`SHOW TRIGGERS` di `tm2inv`, hasilnya kosong) — hanya 13 tabel dasar yang
di-import saat migrasi Tier 3 Inventory, 12 stored procedure & 3 trigger
legacy sengaja dilewati (lihat [[project_inventory_module_status]]). Data
`rm_stockcard` yang sudah ada sekarang adalah hasil trigger ini yang PERNAH
jalan di sistem lama (datanya sudah jadi/materialized, ikut ter-dump), bukan
hasil trigger yang jalan di sistem baru.

**Keputusan arsitektur saat Stock In/Out/Purchase Receive digarap**: PORT
logika 3 trigger ini jadi **PHP Service** (mis. `App\Services\Inventory\StockConfirmService`),
BUKAN pasang ulang trigger SQL mentah-mentah. Alasan: seluruh business logic
lain di codebase ini (Costing, Quotation, Flow Engine approval) sudah
konsisten hidup di `App\Services\*`, bisa di-test, di-debug, dan dipanggil
eksplisit dari Livewire component — trigger DB tersembunyi dari kode PHP,
menyulitkan debugging & tidak konsisten dgn pola arsitektur yang sudah
berjalan. Logika intinya (hitung saldo berjalan per item+lokasi, insert
baris ledger baru) bisa disalin lurus dari SQL trigger di atas ke PHP, cuma
pemicunya diganti: bukan `AFTER UPDATE` trigger, tapi dipanggil eksplisit
dari method "Confirm" milik masing-masing layar Stock In/Out setelah approval
atasan selesai (kemungkinan lewat `FlowEngineService` yang sudah dipakai
PR/PO, lihat [[project_procurement_module_status]]).

---

## 7. Checklist saat menambah/menata ulang menu modul

- [ ] Ada minimal 1 grup input (`menu_type='group'`, isinya layar create/edit/approve).
- [ ] Ada tepat 1 grup Laporan berlabel **"Laporan"** (bukan variasi lain,
      kecuali modul lama yang belum diseragamkan — lihat catatan §1).
- [ ] Label tiap item laporan cocok dengan nama asli layarnya (`#[Title]`),
      tanpa mengulang kata "Laporan".
- [ ] `sort_order` grup input < grup Laporan (grup input tampil duluan).
- [ ] Item yang belum dibangun (`status_code='NOT_BUILT'`) TETAP ditampilkan
      abu-abu sebagai placeholder roadmap — jangan disembunyikan
      (`is_active=false`) hanya karena belum jadi; itu cuma dipakai utk
      menu yang benar-benar retired/digantikan (lihat pola `INV_MOVE`/
      `INV_ADJ`/`SALES_NOTA`/`SALES_TRX` di riwayat migration).
- [ ] `sys_app_menu.id` BUKAN auto_increment — selalu `MAX(id)+1` manual
      saat insert baris baru (lihat migration mana pun sesi ini sbg contoh).
