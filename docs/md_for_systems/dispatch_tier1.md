# TIER 1 — DISPATCH · BUSINESS CONCEPT & SCOPE
**ERP Cetak Offset & Packaging — PT. Telasih Makmur** · Modul 05 · DB `tm2sdi`

| | |
|---|---|
| **Tier** | 1 — Business Concept & Scope |
| **Basis** | Sesi arsitektur 2026-08-26 + riset komparatif SAP/Oracle/D365/Odoo (`Delivery Order and Surat Jalan Module Design for TMERP`) + `co_order`/`co_order_item`/`co_order_delivery` yang sudah live di `tm2sdi` |
| **Deliverable** | `dispatch_tier1..4.md` + `dispatch_rules.yaml` + `dispatch_state.yaml` + `mockup_delivery_order.html` |
| **Cakupan Tier 1** | §1 Ruang lingkup · §2 Dokumen & prefix · §3 Rantai dokumen · §4 Aktor · §5 Integrasi lintas modul · §6 Nota · §7 Istilah · §8 Di luar scope |

---

## 1. RUANG LINGKUP

Dispatch mengelola siklus **jadwal kirim → keluar gudang → sampai customer**, sambungan terakhir Sales sebelum Invoicing. Modul 5 di `checklistmockuperp.md` terbagi 3 sub-area (5.1 Picking & Packing, 5.2 Delivery Order, 5.3 Shipment) — **Tier 1-4 ini hanya menuntaskan 5.2 (Delivery Order/Delivery Note) + irisan minimal 5.1 (penandaan qty siap kirim)**. 5.3 (Load Planner, Route Planner, Shipment Manifest) sengaja **ditunda** — itu baru relevan begitu volume pengiriman butuh konsolidasi multi-DO ke satu truk, dan belum ada urgensi bisnis yang dikonfirmasi. Lihat §8.

### 1.1 Masalah inti yang diselesaikan
Setelah CO `CONFIRMED`, jadwal kirim per item tercatat di `co_order_delivery` (sudah live, murni penjadwalan — lihat `checklistmockuperp.md` MODUL 1). Yang **belum ada**: dokumen eksekusi pengiriman sungguhan yang:
1. Bisa **mengonsolidasi baris dari beberapa CO berbeda** ke satu pengiriman, selama customer sama (satu truk boleh bawa barang dari CO2600011 dan CO2600014 sekaligus).
2. **Tidak pernah menggabung baris** hanya karena nama produk sama — kalau harga jual beda (karena beda CO/beda negosiasi), baris **tetap terpisah**, masing-masing membawa jejak CO asal + harga asalnya sendiri.
3. Menghasilkan **dua cetakan** dari satu dokumen: Delivery Order (internal, berharga, untuk Sales/Finance) dan Surat Jalan (eksternal, dibawa kurir, ditandatangani penerima, tanpa harga).

### 1.2 Kenapa satu objek data, bukan dua dokumen terpisah
`flowengine_tier1.md` §4 sudah mereservasi `module_code` **DO** dan **DN** sebagai baris terpisah ("Aktif" keduanya) — ini ditulis sebelum riset komparatif dilakukan. **Keputusan Tier 1 ini meng-override asumsi tersebut**: berdasarkan riset SAP (*"you do not use the delivery document to replicate order-related conditions"* — satu Outbound Delivery, banyak output cetak), D365 (satu Packing Slip = padanan Surat Jalan, tanpa dokumen "DO" terpisah), dan praktik Indonesia (DO internal vs Surat Jalan eksternal = dua *lembar cetak* dari transaksi fisik yang sama, bukan dua transaksi berbeda) — **DO dan Surat Jalan adalah SATU tabel transaksi (`do_delivery`/`do_line`) dengan DUA template cetak**. `module_code` **DN** di flow engine **tidak dipakai** (tidak ada flow/approval terpisah untuk cetakan Surat Jalan — itu murni output). `module_code` **DO** dipakai kalau kelak price-override butuh approval (lihat §8). — **[DISPATCH-00]**

---

## 2. DOKUMEN & PREFIX

| Kode | Dokumen | Prefix tabel | Approval | Nota (Bahasa Indonesia) |
|---|---|---|---|---|
| **DO** | Delivery Order | `do_delivery`, `do_line` | ✖ di v1 (kolom reserved, lihat §8) | **Delivery Order (DO)** — cetakan internal berharga |
| — | Surat Jalan | *(cetakan dari `do_delivery`/`do_line` yang sama, bukan tabel baru)* | — | **Surat Jalan (SJ)** — cetakan eksternal tanpa harga, tanda tangan penerima |

Prefix `do_` sudah diusulkan di `prefixlist.md` §B (`do_delivery, do_line`) — dipakai apa adanya, hanya nama kolom di dalamnya yang baru dirancang di sini.

---

## 3. RANTAI DOKUMEN

```
co_order (CONFIRMED) ──1:N──> co_order_item ──1:N──> co_order_delivery (jadwal, PLANNED)
                                                              │
                                                   ditarik (partial diperbolehkan,
                                                   lintas-CO dalam 1 DO, TIDAK PERNAH
                                                   digabung lintas co_order_item_id)
                                                              ▼
                                                        do_line ──N:1──> do_delivery (header, per-customer)
                                                              │
                                                   status PLANNED → SHIPPED
                                                              │
                                          co_order_delivery direkalkulasi: SHIPPED
                                          begitu SUM(do_line.qty_do WHERE SHIPPED) >= ship_qty
                                                              │
                                                              ▼
                                          (Fase 2, luar scope Tier ini) FG Stock Out
                                          + Invoice line -> do_line
```

### 3.1 Kenapa konsolidasi ada di level baris, bukan header
Meniru SAP (VBFA di level item, bukan dokumen), Oracle (`wsh_delivery_details.source_line_id`), D365 (`OrigSalesId` per baris packing slip): `do_delivery` (header) dikunci ke **satu customer**, tapi setiap `do_line` membawa `co_order_id` + `co_order_item_id` sendiri. Ini yang memungkinkan satu DO memuat baris dari CO2600011 dan CO2600014 sekaligus tanpa kehilangan jejak harga/asal per baris.

### 3.2 Kenapa item sama nama tidak pernah digabung
Prinsip locked dari riset: **harga tidak pernah dihitung ulang di dokumen pengiriman — selalu snapshot dari order line asal.** Kalau dua baris CO (nama item identik, harga beda) digabung jadi satu baris DO, salah satu harga pasti hilang atau harus di-rata-rata — keduanya merusak audit trail ke CO asal. Maka **kunci identitas baris DO = `co_order_item_id`**, bukan `item_id`/nama produk. Detail lengkap di Tier 2 §B.

### 3.3 Satu DO = satu shipping event
V1 sengaja **tidak** mendesain status per-baris granular (sebagian baris `SHIPPED`, sebagian belum, dalam satu DO yang sama) — itu kompleksitas yang belum terbukti dibutuhkan. Kalau sebagian item belum siap kirim saat sebagian lain sudah siap, solusinya: **buat DO terpisah** untuk sisanya (didukung native karena `co_order_delivery` bisa ditarik parsial ke banyak DO berbeda — lihat Tier 2 §D). Satu `do_delivery` yang sudah `CONFIRMED` selalu berangkat sebagai satu paket pada satu tanggal.

---

## 4. AKTOR & VISIBILITAS

| Aktor | Peran |
|---|---|
| **Admin Sales / Dispatch** | Membuat DO, menarik baris dari CO, Confirm, tandai Terkirim |
| **Petugas Gudang/Ekspedisi** | Mencetak & membawa Surat Jalan, konfirmasi fisik barang keluar |
| **Customer (penerima)** | Tanda tangan Surat Jalan sebagai bukti terima (v1: dicatat manual di lembar fisik, belum ada capture digital — lihat §8) |
| **Finance/Sales (monitor)** | Melihat nilai DO (Sisi A), dasar Invoicing berikutnya |

### 4.1 Visibilitas harga — Sisi A / Sisi B
Sama pola dengan Procurement PV (`procurement_tier1.md` §4.1):
- **Cetakan Delivery Order (internal)** = **Sisi A** — harga per baris + subtotal + total nilai tampil penuh.
- **Cetakan Surat Jalan (eksternal, dibawa kurir)** = **Sisi B** — kolom harga **tidak disertakan di query cetak sama sekali** (bukan cuma disembunyikan CSS), hanya qty/UoM/deskripsi + kolom tanda tangan. Data harga tetap tersimpan utuh di `do_line`, hanya cetakannya yang dibedakan.

---

## 5. INTEGRASI LINTAS MODUL

### 5.1 Sales (`co_order`, `co_order_item`, `co_order_delivery`) — hulu
`do_line` mereferensikan `co_order_item_id` (harga & deskripsi) dan `co_delivery_id` (baris jadwal yang dipenuhi). CO **wajib** berstatus `CONFIRMED` sebelum barisnya bisa ditarik ke DO — CO `DRAFT` masih bisa berubah harga/qty kapan saja, menarik barisnya ke DO akan menciptakan snapshot yang tidak valid.

### 5.2 Inventory (`tm2inv`) — Fase 2, di luar Tier ini
`inventory_state.yaml` sudah mencatat aturan **INV-06**: *"FG keluar HANYA lewat Delivery Note"* — artinya transisi DO ke `SHIPPED` **seharusnya** memicu dokumen Stock Out FG di Inventory. Tier 1-4 ini **tidak** merancang integrasi tersebut (butuh keputusan valuasi FG/COGS tersendiri) — dicatat sebagai open item `DISPATCH-STOCK-OUT-FG` di `dispatch_state.yaml`. v1 DO **tidak menyentuh stok** sama sekali, murni dokumen administratif pengiriman (paralel dengan bagaimana `co_order_delivery` "MURNI penjadwalan, TIDAK menyentuh stok" sebelum DO ada).

### 5.3 Invoicing/Billing (AR-lite) — Fase 2, di luar Tier ini
Rekomendasi riset: `invoice_line` mereferensikan `do_line_id` (bukan `do_delivery_id` atau `co_order_id`) agar partial invoicing terhadap partial delivery, serta 1-DO-banyak-invoice/banyak-DO-1-invoice, sama-sama mungkin tanpa perubahan struktur. Kolom `do_line.qty_invoiced` sudah disiapkan (nullable/default 0) di Tier 3 untuk pijakan Fase 2, tapi Invoicing sendiri **tidak** dirancang di Tier ini.

### 5.4 Master & sistem
- `cust_customer`/`cust_address` (`tm2mst`) — identitas & alamat kirim. FK **logical** (lintas-DB, `tm2mst` ↔ `tm2sdi`).
- `co_order`, `co_order_item`, `co_order_delivery` (`tm2sdi`, satu DB yang sama) — FK **fisik**.

---

## 6. NOTA (CETAKAN)

| Cetakan | Judul | Sisi | Fungsi |
|---|---|---|---|
| DO print | **Delivery Order** | A (berharga) | Dokumen internal Sales/Finance, dasar verifikasi sebelum barang berangkat |
| Surat Jalan print | **Surat Jalan** | B (tanpa harga) | Dokumen fisik dibawa kurir, ditandatangani penerima sebagai bukti serah-terima |

Keduanya dari `do_delivery`/`do_line` yang sama (lihat §1.2). Layout **TERPUSAT** (dokumen), bukan full-page — konsisten dengan pola cetakan modul lain.

---

## 7. ISTILAH BAKU

| Istilah | Definisi |
|---|---|
| **DO (Delivery Order)** | Dokumen eksekusi pengiriman; header per-customer, baris membawa referensi CO asal + harga snapshot |
| **Surat Jalan** | Cetakan eksternal dari DO yang sama, tanpa harga, untuk ditandatangani penerima |
| **Konsolidasi lintas-CO** | Satu DO memuat baris dari beberapa CO berbeda milik customer yang sama |
| **No-merge rule** | Baris DO tidak pernah digabung lintas `co_order_item_id`, meski nama item identik — lihat Tier 2 §B |
| **Qty tersedia (qty_available)** | Sisa `ship_qty` sebuah baris `co_order_delivery` yang belum dialokasikan ke DO manapun (PLANNED/SHIPPED) — dihitung live via VIEW, bukan kolom fisik |
| **Shipping event** | Satu aksi "Tandai Terkirim" yang men-transisikan seluruh baris satu DO sekaligus ke SHIPPED |
| **Price override** | Harga baris DO diedit manual berbeda dari snapshot CO asal; kolom reserved, tanpa approval di v1 |

---

## 8. DI LUAR SCOPE (fase ini)

| Hal | Alasan |
|---|---|
| **5.1 Picking List Entry granular (per-lokasi/bin, scan barcode)** | Belum ada kebutuhan WMS sungguhan; v1 anggap barang selalu siap begitu DO Confirmed |
| **5.1 Packing List (koli/tonase/m³)** | Menunggu 5.3 Shipment; `do_package`/`do_package_line` didesain di riset kemarin tapi belum dibangun di Tier ini |
| **5.3 Load Planner, Route Planner, Shipment Manifest** | Belum ada urgensi bisnis terkonfirmasi; menyusul spec terpisah |
| **Price-change approval 3-party (Costing+Sales+Finance)** | Sudah dicatat di `checklistmockuperp.md` sebagai item terbuka terpisah; kolom `price_overridden`/`original_unit_price`/`price_change_approval_status` disiapkan di Tier 3 tapi **tidak** ada flow approval aktif di v1 |
| **FG Stock Out otomatis saat SHIPPED** | Butuh keputusan valuasi COGS FG tersendiri (AVG/FIFO), lihat §5.2 |
| **Proof of Delivery digital (foto/tanda tangan elektronik)** | v1: tanda tangan fisik di lembar Surat Jalan, tidak di-capture ke sistem |
| **Delivery Return / retur setelah SHIPPED** | Modul terpisah setara PRT vs PV, menyusul kalau dibutuhkan |
| **Invoicing/Billing (AR-lite)** | Modul 6, menyusul — `do_line.qty_invoiced` disiapkan sebagai pijakan saja |

---

## 9. REFERENSI

- `checklistmockuperp.md` MODUL 1 (§ `co_order_delivery`) & MODUL 5
- `flowengine_tier1.md` §4 (module_code DO/DN, di-override sebagian oleh §1.2 di atas)
- `prefixlist.md` §B (`do_` prefix)
- `procurement_tier1.md` (pola Sisi A/B, hard-lock dokumen)
- `tm2sdi.sql` (`co_order`, `co_order_item`, `co_order_delivery`)
- Riset: *Delivery Order and Surat Jalan Module Design for TMERP: Comparative ERP Research and Recommendations* (2026-08-26)
- `mockup_delivery_order.html` (simulasi visual, lihat Tier 4 §7)
