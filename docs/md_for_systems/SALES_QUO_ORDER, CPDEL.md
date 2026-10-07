# SALES_QUO_ORDER, CPDEL.md — Quotation, Customer Order & Customer Plan Delivery

> Dokumen gabungan (belum ada Tier doc terpisah utk 3 sub-modul ini — dirangkum
> dari `sales_state.yaml`, `inquiryproduct_tier1.md` §3.4, `inquiryproduct_tier2.md`
> §3.4, `dispatch_tier1.md` §5.1, `checklistmockuperp.md (diarsipkan)` MODUL 1, dan kode aktual
> per 2026-09-03). Dibuat atas permintaan user untuk direview — **bukan** Tier doc
> resmi baru, isi masih perlu dicek/dikoreksi user.
>
> **Riwayat rename (2026-09-03):** "Plan Delivery" → **"Customer Plan Delivery"**,
> disingkat **CPDEL** (sebelumnya draf pertama dokumen ini sempat memakai "PDEL" —
> nama file & semua istilah sudah dikoreksi mengikuti keputusan user).

| | |
|---|---|
| **Modul** | 01 — Sales (lanjutan setelah Inquiry/ProDev/ProCost) |
| **DB** | `tm2sdi` (quo_*, co_*) + `tm2mst` (customer, FK logis) |
| **Posisi dalam rantai** | Inquiry → ProDev → ProCost (harga) → **Quotation** → **Customer Order** → **Customer Plan Delivery (CPDEL)** → Delivery Order/Delivery Notes → Invoice |
| **Status umum** | Quotation + CO Mode #1 + CPDEL grid (Split/Gabung) **LIVE & teruji** (2026-08-20). ENGINE auto-generate CPDEL saat CO Confirm **BARU dibangun 2026-09-03**. CO Mode #2 (manual+Master Product) **BELUM ADA**. |

---

## 1. FLOW VITAL & IMPORTANT (dikunci user 2026-09-03)

> Ini bagian paling penting dokumen ini — jangan diubah tanpa persetujuan user,
> sudah dicatat juga di `CLAUDE.md` §4 sebagai `[VITAL]`.

**Prasyarat masuk alur ini:** 1 InqProd sudah melewati ProDev + ProCost
(sudah punya HPP/harga via `iqp_costing`, `published_flag=1`) dan **disetujui
Sales** secara internal (bukan approval Direksi — itu tahap "publish costing",
beda level, lihat CLAUDE.md §4 "Costing rahasia").

```
InqProd (costing published, Sales setuju)
        │
        ▼
┌────────────────────────────────────────────────────────────────┐
│ LANGKAH 1 — SALES QUOTATION                                    │
│ Sales PILIH-PILIH InqProd mana yang masuk 1 quotation           │
│ (customer sama, boleh gabung banyak inquiry + baris jasa bebas).│
│ Semua field lain (harga, qty, diskon, header) ke-ISI OTOMATIS    │
│ dari snapshot InqProd — TAPI boleh diedit sebelum Terbitkan.    │
│ SUDAH ADA & LIVE: quotation/entry.blade.php +                   │
│ App\Services\Sales\QuotationBuilder                             │
└────────────────────────────────────────────────────────────────┘
        │  customer OK (quotation Terbitkan/PUBLISHED)
        ▼
┌────────────────────────────────────────────────────────────────┐
│ LANGKAH 2 — CUSTOMER ORDER — Mode #1 (OTOMATIS dari Quotation)  │
│ "1 CO = 1 Quotation" (co_order.quotation_id NOT NULL + UNIQUE). │
│ Item CO = SNAPSHOT item Quotation saat CO lahir (bukan live-    │
│ linked). Terkunci kecuali due_date per baris.                   │
│ SUDAH ADA & LIVE: App\Services\Sales\CoOrderBuilder::            │
│ findOrCreateFromQuotation() — dipicu tombol di Quotation Entry  │
│ begitu status PUBLISHED.                                        │
└────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────┐
│ JALUR ALTERNATIF — CUSTOMER ORDER — Mode #2 (MANUAL)            │
│ Dipakai kalau TIDAK lewat proses Inquiry→Quotation penuh —      │
│ meniru alur manual versi sebelumnya (ScriptCase V1).            │
│ SYARAT MUTLAK: Master Product (prod_product) harus SUDAH ADA    │
│ dulu — baris CO diisi dari situ, BUKAN free-text seperti baris  │
│ manual Mode #1 sekarang (co/entry.blade.php::addItem()).        │
│ STATUS: ❌ BELUM ADA. 0 jalur kode. Skema co_order.quotation_id │
│ saat ini NOT NULL — CO tanpa Quotation MUSTAHIL tanpa migration │
│ (perlu: quotation_id nullable + validasi baris wajib product_id │
│ saat quotation_id null).                                        │
└────────────────────────────────────────────────────────────────┘
        │  (Mode #1 atau Mode #2) CO → doc_status CONFIRMED
        ▼
┌────────────────────────────────────────────────────────────────┐
│ LANGKAH 3 — CUSTOMER PLAN DELIVERY (CPDEL) — ENGINE + grid      │
│ ENGINE (BARU 2026-09-03): begitu CO di-Confirm, tiap baris item │
│ PRODUCT yang BELUM punya jadwal manual di-auto-buat 1 baris     │
│ co_order_delivery: qty PENUH @ due_date item, per part/produk.  │
│ SUDAH ADA & LIVE: co/entry.blade.php::confirmCo()                │
│ (item yang sudah diisi manual lewat "+ Jadwal Kirim" saat CO    │
│ masih Draft TIDAK ditimpa — engine cuma mengisi yang kosong).    │
│                                                                  │
│ GRID CPDEL — layar KHUSUS (co/delivery-plan.blade.php): tampilkan│
│ SEMUA baris hasil engine, lintas-CO, Sales bisa:                │
│   • SPLIT baris (pecah qty + pindah tanggal)                    │
│   • JOIN/GABUNG baris (item & CO sama, ke tanggal paling awal)  │
│   • atau BIARKAN seperti hasil default engine                   │
│ SUDAH ADA & LIVE (Split + Gabung sudah dibangun 2026-08-20,     │
│ cocok mockup — lihat §4 di bawah).                               │
└────────────────────────────────────────────────────────────────┘
        │
        ▼
   Delivery Order / Delivery Notes (dispatch_tier1-4.md — modul terpisah,
   menarik co_order_delivery ke do_delivery/do_line, bisa
   konsolidasi lintas-CO, no-merge rule per co_order_item_id)
```

**Prinsip kunci (jangan dilanggar):**
- **2 mode CO = 2 JALUR INPUT ke dokumen yang sama, bukan 2 alur bisnis
  berbeda** — pola sama seperti ProDev/ProCost (CLAUDE.md §4). Tujuan akhir
  (CO valid siap masuk CPDEL) harus sama dari mode manapun.
- **Snapshot, bukan live-link**, di tiap simpul: Quotation membekukan harga
  InqProd; CO membekukan item Quotation; CPDEL membekukan qty/harga baris CO
  (engine cuma menetapkan qty & tanggal AWAL, bukan re-hitung harga).
- **Mode #1 tidak butuh Master Product** (item CO snapshot dari Quotation,
  bisa jadi produk yang belum pernah "resmi" di Master Product). **Mode #2
  wajib Master Product** — jangan tertukar syaratnya.
- **ENGINE mengisi, tidak menimpa** — kalau Sales sudah atur jadwal manual
  sebelum CO Confirm (tab "Delivery" di CO Entry), engine tidak menyentuh
  baris itu. Ini pola sama seperti "engine suggests, officer confirms"
  (CLAUDE.md §4).

---

## 2. QUOTATION

- **Skema:** `quo_quotation` (customer_id, header) + `quo_quotation_item`
  (redesain total 2026-08-20 dari draf 1:1-per-inquiry).
- **Keputusan terkunci:** 1 Quotation = N inquiry (customer sama) + N baris
  jasa/tooling bebas. Tooling (PISAU+MATRAS/PLATE/FILM) di-split jadi baris
  terpisah per jenis, bukan digabung.
- **Guard saat ini:** `availableInquiries()` di `quotation/entry.blade.php`
  cuma cek customer sama + `iqp_costing.published_flag=1` + belum ditarik ke
  quotation ini. **Tidak** ada guard lokasi kirim sama (TIER_1 §3.4.3 poin 6
  minta ini, belum diimplementasi — `iqp_inquiry` belum punya field lokasi
  kirim; lokasi kirim baru muncul di level CO).
- **Gate lebih ketat dari desain awal:** `QuotationBuilder::addInquiry()`
  menolak (return null) inquiry yang costing-nya belum published — desain
  Tier awal sebenarnya mengizinkan baris draft tanpa harga dulu. Deviasi
  disengaja (hindari baris harga kosong/0 yang membingungkan).
- **Prefix:** `QUO` + `YY` + 5-digit (dikonfirmasi 2026-08-20, bukan `QT`).
- **Nomor terbit** hanya kalau costing `published_flag=1` (Sisi A/B).
- **Status:** ✅ List, Entry, Print — semua live & teruji.

## 3. CUSTOMER ORDER (CO)

- **Skema:** `co_order` + `co_order_item` + `co_order_delivery` +
  `co_order_external_cost` — dibangun dari nol 2026-08-20, TIDAK ada di
  `inquiryproduct_tier1/2.md` versi lama (ditemukan setelah implementasi
  bahwa §3.4 sebenarnya sudah mengunci desain many-to-one+1:1 ini duluan,
  hasil akhir cocok struktur inti tapi ada beberapa deviasi — lihat
  `sales_state.yaml` bagian `deviasi_dari_tier1_locked`).
- **Mode #1 (dari Quotation) — LIVE:**
  - `CoOrderBuilder::findOrCreateFromQuotation()`, gated `PUBLISHED`.
  - Item snapshot `quo_quotation_item`, terkunci kecuali `due_date`.
  - 4 tab di CO Entry: Product / Delivery / External Cost / History Flow.
  - Shipment Location pakai `AddressPicker` existing (bukan komponen baru).
  - PPN 11% + PPh 23 2% (asumsi rate standar, **belum dikonfirmasi** user/
    dokumen resmi — gampang diubah, 1 baris di `CoOrder::totals()`).
  - Baris manual (`addItem()`) bebas free-text description, tidak butuh
    Master Product — ini yang beda dari syarat Mode #2.
  - `co_order_item.line_kind` = PRODUCT/SERVICE, Order Type dihitung
    otomatis (Product Only/Service Only/Mixed) — turunan mockup, bukan
    konsep dari Tier doc.
  - **`confirmCo()` (2026-09-03): sekarang juga menjalankan ENGINE CPDEL** —
    lihat §4.
- **Mode #2 (manual/standalone) — ❌ BELUM ADA:**
  - Tidak ada jalur kode buat CO tanpa Quotation.
  - `co_order.quotation_id` **NOT NULL + UNIQUE** di DDL — perlu migration
    (nullable) sebelum Mode #2 bisa dibangun.
  - Syarat: Master Product (`prod_product`) harus ada dulu, baris CO ambil
    dari situ.
  - **Belum ada keputusan desain** soal: bagaimana harga ditentukan di Mode
    #2 (manual full seperti ProCost Mode #2? atau tarik dari
    `prod_product`/costing terakhir?), apakah tetap ada Quotation-nya
    (opsional) atau langsung CO tanpa Quotation sama sekali. **Cek/tanya
    user dulu sebelum mulai bangun** (ikut §7.1 CLAUDE.md — ini ambigu &
    arsitektural).
- **Open item lama (belum digarap, di luar scope Mode #2):**
  - Materialisasi Master Product otomatis saat CO Confirm (TIER_1 §5, trigger
    setara `PO_RECEIVED`) — **belum diimplementasi**. Confirm CO cuma ubah
    `doc_status` (+ jalankan engine CPDEL sejak 2026-09-03), tidak auto-insert
    `prod_product`.
  - `fin_invoice.co_id` & `fin_voucher_line.co_id` masih manual teks, belum
    di-rewiring ke FK asli.

## 4. CUSTOMER PLAN DELIVERY (CPDEL)

> Nama lama "Plan Delivery" (PDEL) — diganti **"Customer Plan Delivery"
> (CPDEL)** 2026-09-03, DAN dipindah dari grup menu "Delivery" ke grup
> **"Sales Order"** (satu grup dengan Customer Order) — lihat §6 Menu.

### 4.1 ENGINE (baru 2026-09-03)

- **Trigger:** `co/entry.blade.php::confirmCo()`, tepat saat `doc_status`
  CO berubah DRAFT → CONFIRMED (1 transaksi DB bersama).
- **Aturan:** untuk tiap `co_order_item` berjenis PRODUCT yang **belum**
  punya baris `co_order_delivery` sama sekali, buat 1 baris otomatis:
  `shipment_type=FULL`, `ship_qty` = qty penuh item, `ship_date` =
  `due_date` item, `status=PLANNED`.
- **Tidak menimpa manual:** item yang Sales sudah atur duluan lewat tombol
  "+ Jadwal Kirim" di tab Delivery (CO Entry, saat CO masih Draft) —
  termasuk kalau sudah di-split jadi beberapa baris tanggal berbeda — TIDAK
  disentuh engine sama sekali (deteksi: item itu sudah punya ≥1 baris
  `co_order_delivery`, apapun isinya).
- **`due_date` WAJIB terisi sebelum CO bisa Confirm sama sekali** (keputusan
  user 2026-09-03, guard tambahan setelah gap ini ditemukan saat testing).
  Tombol "✓ Confirm CO" otomatis **disabled** kalau masih ada baris PRODUCT
  dengan `due_date` kosong — muncul label kuning "⚠ N item belum ada Due
  Date" di sebelah tombol, dan input Due Date baris yang kosong ditandai
  merah + teks "wajib diisi" di tabel item. Guard diulang lagi di server
  (`itemsMissingDueDate` computed, dicek juga di `confirmCo()`) — bukan cuma
  disable tombol di UI. **Akibatnya: TIDAK ADA LAGI kasus CO CONFIRMED
  dengan due_date kosong** — jadi engine (di atas) tidak perlu lagi
  menangani kasus due_date NULL, karena kasusnya sudah dicegah sebelum
  Confirm bisa terjadi.
- **Kenapa begini:** meniru persis `seedAll()` di
  `docs/mockups/co_delivery_plan_mockup.html` (baris awal berlabel "DEFAULT
  (auto, full qty @ due date)"), TAPI tetap menghormati kerja manual Sales
  yang sudah dilakukan lebih dulu di CO Entry (fitur itu sudah ada sejak
  2026-08-20, jangan dibuang).

### 4.2 GRID CPDEL — layar khusus

File: `co/delivery-plan.blade.php` (mockup acuan:
`docs/mockups/co_delivery_plan_mockup.html`).

- **Sumber baris:** hasil ENGINE (§4.1) + baris manual dari tab Delivery CO
  Entry. Hanya CO **CONFIRMED** yang muncul.
- **Fungsi:** hub logistik LINTAS-CO, murni penjadwalan. **Tidak** menyentuh
  stok/COGS — itu domain Delivery Order (HPP diakui saat DO terbit,
  `financeaccounting_tier1.md` Keputusan B).
- **3 aksi yang tersedia per baris (persis seperti diminta user — SPLIT, JOIN,
  atau biarkan seperti hasil engine):**
  1. **Biarkan** — baris default engine tetap apa adanya, tidak perlu aksi.
  2. **Split** (tombol ✂ per baris, status PLANNED saja) — modal 1 form:
     `qty pecah` (opsional) + `tanggal baru`. Qty kosong = cuma pindah
     tanggal baris yang sama (tidak membuat baris baru). Qty diisi = baris
     asal berkurang, baris `PARTIAL` baru terbentuk dgn `split_from_id`
     menunjuk baris asal.
  3. **Gabung/Join** (mode toggle "⇄ Gabung Baris" di toolbar) — centang
     ≥2 baris PLANNED dari **item & CO yang sama**, sistem otomatis pilih
     baris bertanggal PALING AWAL sebagai "pemenang" (qty dijumlahkan ke
     situ), baris lain dihapus.
  4. Baris yang sudah `SHIPPED` (DO terbit) **tidak bisa** di-split/gabung.
- **SUDAH ADA & LIVE** — dibangun 2026-08-20 (`confirmSplit()`/
  `confirmMerge()` di `co/delivery-plan.blade.php`), sudah cocok mockup
  hampir 1:1. Bagian yang BARU 2026-09-03 murni ENGINE (§4.1) — grid & aksi
  split/join TIDAK berubah.

### 4.3 Rekomendasi UX Split (diminta user, "sarankan cara user-friendly")

Sudah dicek ke `docs/mockups/co_delivery_plan_mockup.html` — implementasi
saat ini **sudah mengikuti pola mockup dengan baik**:
- **1 modal, bukan 2 form terpisah** untuk "pindah tanggal" vs "split qty" —
  field qty pecah dibuat **opsional**: kosong = pindah tanggal saja, diisi =
  pecah baris baru. Ini bagus karena user tidak perlu belajar 2 alur beda,
  cukup 1 keputusan ("saya mau ubah qty atau tidak").
- Tombol konfirmasi labelnya **berubah dinamis** ("Pindah Tanggal" ↔ "Pecah
  jadi Baris Baru") mengikuti isi field qty — feedback langsung, user tidak
  perlu menebak apa yang akan terjadi.
- Qty tersedia baris ditampilkan eksplisit di modal sebelum user mengisi
  angka — mencegah salah ketik qty yang tidak masuk akal.

**Saran perbaikan kecil (belum diimplementasikan, opsional):**
1. **Live remaining-qty** di dalam modal: begitu user mengetik qty pecah,
   tampilkan langsung "sisa di baris asal: X" secara real-time (saat ini
   baru muncul setelah submit) — mengurangi trial-error.
2. **Preset tombol qty cepat** (½, ¼, atau "custom") di samping input qty
   manual — pola split pengiriman biasanya rutin (mis. selalu dibagi 2
   pengiriman), preset mempercepat kasus umum tanpa menghilangkan opsi bebas.
3. **Split langsung dari baris grid** (inline, tanpa modal) untuk kasus
   super simpel (cuma pindah tanggal) — modal tetap dipakai kalau user perlu
   pecah qty. Trade-off: kompleksitas UI tambahan vs micro-speed gain — TIDAK
   direkomendasikan kecuali user eksplisit merasa modal saat ini terlalu
   lambat, karena modal yang ada sudah cukup ringan (1 klik buka, 2 field).
4. Kolom **"Asal"** grid sekarang selalu bilang "Awal (dari CO Entry)" utk
   baris pertama — tidak membedakan apakah baris itu lahir dari ENGINE
   (auto) atau manual Sales. Kalau user mau audit trail lebih detail, perlu
   1 kolom baru (`source` ENUM AUTO/MANUAL) — **belum dibangun, tanya user
   dulu apakah perlu.**

**Kesimpulan rekomendasi:** UX split yang sudah ada **sudah user-friendly**
dan cocok pola mockup — tidak perlu dirombak. Saran #1-2 di atas murni
polish opsional, bukan gap fungsional.

## 5. DELIVERY ORDER & DELIVERY NOTES (hilir CPDEL)

- Modul terpisah: `dispatch_tier1-4.md` — `do_delivery`/`do_line` menarik
  baris `co_order_delivery` (boleh partial, boleh lintas-CO dalam 1 DO
  selama customer sama), kunci identitas baris DO = `co_order_item_id`
  (no-merge rule — 2 baris nama sama tapi harga beda dari CO berbeda TIDAK
  PERNAH digabung).
- **Delivery Order** & **Delivery Notes** (Surat Jalan) = **1 tabel, 2
  cetakan** (`dispatch.do.print` vs `dispatch.do.print-surat-jalan`) —
  keputusan lama ini TETAP berlaku secara data. Yang berubah 2026-09-03
  cuma di level **menu**: sebelumnya Surat Jalan sengaja TANPA menu sendiri
  (cuma tombol print di dalam DO Entry); sekarang, atas instruksi eksplisit
  user, grup menu "Delivery" wajib punya 2 item terpisah — lihat §6.
- Baris `co_order_delivery` berubah `SHIPPED` otomatis begitu DO terbit.

## 6. MENU (diubah 2026-09-03)

**Sebelum:**
```
Sales
 ├─ Inquiry & Quotation        (SALES_INQGRP)
 ├─ Sales Order                (SALES_ORDGRP)
 │   └─ Customer Order         (SALES_SO)
 └─ Delivery                   (SALES_DLVGRP)
     ├─ Plan Delivery          (SALES_PLAN)
     └─ Delivery Order         (SALES_DO)
```

**Sesudah:**
```
Sales
 ├─ Inquiry & Quotation        (SALES_INQGRP)
 ├─ Sales Order                (SALES_ORDGRP)
 │   ├─ Customer Order         (SALES_SO)
 │   └─ Customer Plan Delivery (SALES_CPDEL)   ← pindah + rename dari SALES_PLAN
 └─ Delivery                   (SALES_DLVGRP)
     ├─ Delivery Order         (SALES_DO)
     └─ Delivery Notes         (SALES_DN)      ← BARU
```

- Migration: `2026_09_03_000011_rename_cpdel_and_regroup_delivery_menu.php`.
- Route mapping (`main-menu.blade.php`): `SALES_CPDEL` → `co.delivery-plan`
  (route Livewire tidak diganti nama, cuma label/menu yang berubah).
  `SALES_DN` → `dispatch.do.index` (**reuse** grid yang sama dengan Delivery
  Order untuk saat ini — belum ada layar/filter terpisah khusus Surat Jalan;
  user cetak Surat Jalan dari dalam baris DO yang sama seperti sebelumnya).
- **Catatan:** ini meng-override keputusan `move_dispatch_menu_into_sales_delivery`
  (2026-08-28) yang tadinya sengaja TIDAK memberi Surat Jalan menu sendiri
  ("DO+Surat Jalan tetap 1 tabel 2 cetakan... tidak perlu 2 menu item
  terpisah"). Override ini eksplisit dari user sesi ini (2026-09-03) — kalau
  nanti "Delivery Notes" perlu jadi layar/filter yang benar-benar berbeda
  dari "Delivery Order" (bukan cuma reuse grid), itu scope baru yang perlu
  didesain terpisah.

---

## 7. REFERENSI

- `sales_state.yaml` — progress & keputusan_terkunci A-H, deviasi dari Tier
  lama, artefak selesai, open_items.
- `docs/inquiry_product_costing/inquiryproduct_tier1.md` §3.4 (relasi
  dokumen Inquiry→Quotation→CO) & §5.9.
- `docs/inquiry_product_costing/inquiryproduct_tier2.md` §3.4 (pseudocode
  convert_to_quotation, sebagian dideviasi — lihat sales_state.yaml).
- `dispatch_tier1.md` §3, §5.1 (rantai `co_order` → `co_order_delivery` →
  DO/Surat Jalan, no-merge rule detail Tier 2 §B).
- `checklistmockuperp.md (diarsipkan)` MODUL 1 (status per-layar, cross-check ke
  `routes/web.php` & `resources/views/pages/`).
- Kode: `App\Services\Sales\QuotationBuilder`,
  `App\Services\Sales\CoOrderBuilder`, `App\Models\Sdi\{QuoQuotation,
  QuoQuotationItem, CoOrder, CoOrderItem, CoOrderDelivery}`,
  `co/entry.blade.php::confirmCo()` (ENGINE CPDEL).
- Mockup: `mockup_sales_quotation_entry.html`,
  `mockup_sales_quotation_letter_print.html`, `mockup_co_entry.html`,
  `co_delivery_plan_mockup.html` (acuan ENGINE + Split/Gabung).
- Migration menu: `2026_09_03_000011_rename_cpdel_and_regroup_delivery_menu.php`.
- Memory: `project_quotation_co_flow.md`, `project_cpdel_engine.md`.
