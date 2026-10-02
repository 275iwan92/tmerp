# Sales — Inquiry & Quotation, Order, CRM (Tier 1)

> Dibuat 2026-10-02 atas keputusan user. Dokumen ini adalah **peta Sales**: apa tujuan tiap bagian, layar dan
> tabelnya, kontrak antar bagian, dan apa yang masih terbuka. Rincian alur per dokumen tetap di dokumen acuannya
> (lihat §8); dokumen ini tidak mengulanginya.
>
> Status: Inquiry & Quotation dan Order sudah berjalan. **CRM baru dibangun 2026-10-02 di database dummy
> (`ddt*`, mockup.test) dan menunggu penilaian user.** Menu Sales yang baru juga baru di `ddtsys`.

---

## 1. Tiga bagian Sales (kata user)

| Bagian | Tujuan (user 2026-10-02) | Pertanyaan yang dijawab |
|---|---|---|
| **Inquiry & Quotation** | *Tools* untuk **mempercepat keluarnya harga jual** | "Berapa harganya, dan kapan bisa saya tawarkan?" |
| **Order** | **Pencatat** | "Apa yang dipesan, berapa, kapan dijanjikan?" |
| **CRM** | **Membangun hubungan dengan customer** | "Siapa yang harus saya hubungi hari ini, dan apa riwayatnya?" |

Inquiry dan Order adalah **alur dokumen** yang berurutan. CRM bukan tahap ketiga: CRM adalah **lapisan di samping**
keduanya yang melihat dari sisi customer. Karena itu CRM hampir seluruhnya **membaca** data Inquiry dan Order, dan
hanya memiliki sedikit data sendiri (aktivitas dan komplain).

## 2. Susunan menu: satu grup besar, grup kecil yang bisa dilepas-pasang

Keputusan user 2026-10-02: *"Sales adalah big group, dan small group-nya Inquiry & Quotation, Order dan CRM —
dijadikan satu grup besar, tetapi tetap punya grup kecil yang modular yang bisa dilepas-pasang."*

```
Sales                              (modul menu SALES)
├── Inquiry & Quotation            (grup PREORDER)      Inquiry Product, Pro-Dev, Pro-Cost, Quotation, Master Data, Report
├── Order                          (grup SALES_ORDGRP)  Customer Order, Customer Plan Delivery, CO Approval Center, Report
├── Delivery                       (grup SALES_DLVGRP)  Delivery Order, Delivery Notes, Subcontract Delivery Note, Proof of Delivery
└── CRM                            (grup SALES_CRMGRP)  Customer 360, Activities & Follow-up, Complaint & Return, Master Data, Report
```

- **Lepas-pasang = saklar `activate` di baris grup** (`sys_app_menu`). Grup dimatikan: menunya hilang, layarnya
  tertutup (`AppMenu::open`), rute simpan CRM ikut tertutup, **datanya tetap**. Dinyalakan lagi: semuanya kembali.
- **Menu = data.** Layar tidak tahu ada di grup mana; pintasan = baris menu baru ber-`target` sama (mis. Master
  Customer dan Sales Person di CRM › Master Data, Win-Rate Sales di CRM › Report). Tidak ada salinan layar.
- **Delivery** tetap grup tersendiri di dalam Sales (isinya tidak diubah). Tempatnya masih pertanyaan terbuka (§9.1).
- Migration: `2026_10_02_000131_menu_sales_big_group_crm_ddt.php` (hanya `ddtsys`).

## 3. Kontrak antar bagian

Bagian satu tidak menulis ke tabel bagian lain. Hubungannya hanya lewat kontrak berikut (snapshot + status akhir):

| Kontrak | Dari → Ke | Pemicu | Yang diserahkan |
|---|---|---|---|
| **A** | Inquiry & Quotation → Order | Quotation terbit dan **WIN** | Baris quotation menjadi baris Customer Order (snapshot) |
| **B** | Order → Production | CO Confirmed + approved, baris CPDEL **Post to Production** | Work Order, tanpa harga |
| **C** | CRM → QA (Production) | Komplain **Confirmed** dengan *Send to QA for review* | Dokumen komplain (dibaca QA); QA menjawab di tabelnya sendiri |
| **D** | QA → CRM | Jawaban QA **Confirmed** | Keputusan, akar masalah, tindakan, nasib barang kembali (dibaca Sales) |

CO direct (INSTANT, tanpa Quotation) selalu tersedia walau grup Inquiry & Quotation dimatikan (rulebook A.2).
Aktivitas CRM boleh menunjuk satu quotation (follow-up quotation); selain itu CRM hanya membaca.

## 4. Inquiry & Quotation — mempercepat harga jual

Alur: **Inquiry** (New / Revise / Recalc) → **Pro-Dev** (layout produksi) → **Pro-Cost** (HPP + tangga harga) →
**Quotation** → WIN / LOST.

- Satu tabel `iqp_inquiry` dengan tiga tanda: `stat_confirm_sales`, `stat_confirm_prodev`, `stat_confirm_procost`.
  Tiap meja kerja = saringan jenis dokumen DAN tanda (rulebook A.20). Recalc langsung ke Pro-Cost.
- Quotation **wajib** dari inquiry yang costing-nya sudah published (rulebook A.1). 1 Quotation = 1 Customer Order.
- HPP dan margin hanya untuk Costing + Direksi; harga jual hanya untuk yang berhak (rulebook A.8–A.9).
- **Zona beku:** Inquiry Product, Pro-Dev, Pro-Cost tidak diubah esensi dan alurnya tanpa izin user.
- Tabel (`tm2sdi`): `iqp_*`, `quo_quotation`, `quo_quotation_item`, `quo_quotation_cc`. Master di `tm2mst`: `prod_*`.

## 5. Order — pencatat

Alur: **Customer Order** (dari Quotation WIN, atau direct) → approval (F&A SPV → Operational Manager) →
**Customer Plan Delivery** (janji kirim per baris; split / gabung) → **Post to Production** → **Delivery Order** →
**Delivery Notes** (Surat Jalan) → **Proof of Delivery**.

- Customer memilih satu qty per product di CO; due date wajib sebelum Confirm (rulebook A.10, A.15).
- CPDEL: dua janji (slot customer vs siap produksi); baris berwarna menurut jatuh tempo (rulebook C.32).
- Tabel (`tm2sdi`): `co_order`, `co_order_item`, `co_order_delivery`, `co_delivery_negotiation`, `do_delivery`,
  `do_line`, `do_delivery_pod`. Arsip sistem lama (hanya dibaca): `co_order_legacy(+_detail)`,
  `co_delivery_legacy`, `co_invoice_legacy`.

## 6. CRM — membangun hubungan dengan customer

CRM di pabrik packaging make-to-order ini **berbasis customer yang sudah ada** (order berulang), bukan corong lead.
Corong penjualannya sudah ada: Inquiry → Quotation → WIN / LOST.

### 6.1 Customer 360 (`crm-customer`) — hanya membaca

Perjalanan user: buka **Customer 360** → lihat daftar customer; yang **menunggu tindakan ada di paling atas**
(follow-up lewat / hari ini / segera, lalu komplain terbuka, lalu yang paling sering order) → klik dua kali satu
customer → dapat **satu halaman** berisi:

- Angka kunci: order pertama, order terakhir, total order, total nilai order (hanya yang berhak melihat harga),
  kontak terakhir, quotation / order / komplain / follow-up terbuka, follow-up berikutnya.
- Tab **Overview**: grafik order per bulan 24 bulan terakhir, **product yang paling sering dipesan** (bahan
  pembicaraan repeat order), profil dari Master Customer.
- Tab **Activities, Quotations, Orders, Deliveries, Complaints, Contacts**. Orders dan Deliveries menggabungkan
  sistem baru dan **arsip sistem lama** (kolom Source).
- Tombol **+ Activity**, **+ Complaint**, **Master Customer** (mengubah data customer tetap di master).

Segmen customer: **Active**, **Sleeping** (pernah order, order terakhir lebih lama dari `CRM_SLEEPING_DAYS` hari),
**Prospect**, **No order**. Sumber: `App\Support\V2\Data\Crm`.

### 6.2 Activities & Follow-up (`crm-activity`)

Perjalanan user: buka **Activities & Follow-up** → lihat semua kontak dengan customer; follow-up yang lewat berwarna
merah, hari ini oranye, segera kuning → **+ New Record** → pilih customer → isi jenis, topik, kontak, hasil,
catatan, dan (bila ada) tanggal follow-up + tindak lanjut → **Save**. Saat tanggalnya tiba: buka → **Log Result**
(mencatat aktivitas baru; follow-up asalnya tertutup sendiri) atau **Follow-up Done**.

- Tabel `tm2sdi.crm_activity` = **perluasan `quo_activity`** (keputusan user: diperluas, bukan tabel kedua):
  `customer_id` wajib, `quotation_id` boleh kosong, ditambah `contact_id`, `topic`, `next_done(+_at, _by)`.
  Follow-up di form Quotation tetap berjalan (`QuoActivity` = turunan `CrmActivity`); customer-nya diisi otomatis.
- Siklus: dicatat → (bila ada tindak lanjut) **follow-up terbuka** → **selesai**. Ubah / hapus = pencatatnya.
- Jatuh tempo dihitung hari kerja; batas kuning = konstanta `CRM_FOLLOWUP_SOON_DAYS`.
- Sumber: `App\Support\V2\Data\CrmActivities`.

### 6.3 Complaint & Return (`crm-complaint`)

Keputusan user: *"Retur penjualan, informasinya harus ke Sales, tetapi mutu kualitas masuk ke QA."*

Perjalanan user (Sales): **+ New Record** → pilih customer + jenis (**Complaint** / **Sales Return**) → isi kategori,
keluhan, permintaan customer, product + qty dikeluhkan + qty kembali → **Confirm** (nomor `CCyy#####` lahir) →
menunggu QA → baca jawaban di tab **QA Review** → isi tab **Resolution** → **Close**.

Perjalanan user (QA): **Production › Quality › Complaint Review (QA)** → daftar dibuka di *To review* → buka → isi
Verdict, Root Cause, Corrective / Preventive Action, Returned Goods → **Confirm** (final).

| Status | Arti | Tombol |
|---|---|---|
| Draft | sedang diisi Sales | Save, Delete, Confirm |
| Confirmed + *Waiting QA* | menunggu jawaban QA | Cancel (alasan wajib), Print |
| Confirmed + *QA answered* / tanpa QA | Sales mengisi penyelesaian | Save, **Close**, Cancel, Print |
| Closed | selesai dengan customer | Print |
| Cancelled | dibatalkan, alasan tercatat | Print |

- Kategori **Product quality** selalu diteruskan ke QA; kategori lain hanya bila dicentang.
- Close ditolak selama QA belum menjawab, dan selama Resolution belum dipilih.
- Tabel: `tm2sdi.crm_complaint` + `crm_complaint_item` (milik Sales), `tm2prd.qc_complaint_review` (milik QA,
  1 komplain = 1 jawaban, `complaint_id` relasi logis lintas database).
- Cetakan: `customer-complaint` (objek DevLib `Document`, A5 / A4), penanda tangan `SIGN_CCR_*`. **Belum A.**
- **Qty Returned hanya informasi**: belum menggerakkan stok (§9.4).
- Sumber: `App\Support\V2\Data\CrmComplaints`.

### 6.4 Yang sengaja tidak dibangun

Kanban lead, kampanye e-mail, dan scoring: berlebihan untuk bisnis ini. Laporan baru untuk hal yang sudah terlihat
di layar dokumennya juga tidak dibuat (rulebook changelog 2026-10-02).

## 7. Hak akses, konstanta, bahasa

- Ketiga grup memakai izin modul **`SALES`** (belum ada izin tersendiri per grup, §9.3). Complaint Review memakai `PROD`.
- Nilai rupiah di Customer 360 disaring di server lewat `SecUser::canViewSalesPrice()`.
- Konstanta (Settings › Constants, kategori Sales): `CRM_FOLLOWUP_SOON_DAYS` (2), `CRM_SLEEPING_DAYS` (180).
- Semua label ditulis English; terjemahan Indonesia lewat kamus (migration `2026_10_02_000133`). Help keempat layar
  ada di `resources/data/help/sales.json` dan `production.json`.

## 8. Dokumen acuan

| Topik | Dokumen |
|---|---|
| Aturan user (wajib dibaca dulu) | `docs/globaluserrules.md` |
| Alur product: Quotation WIN → Master Product, Revisi, Rekalkulasi, ACC Digital | `docs/flow_product_master.md` |
| Quotation, Customer Order, CPDEL | `docs/md_for_systems/SALES_QUO_ORDER, CPDEL.md` |
| Inquiry / Pro-Dev / Pro-Cost | `docs/mockups/inquiry_product_costing/inquiryproduct_tier1.md`, `inquiryproduct_tier2.md`, `inquiryproduct_rules.md` |
| Pratinjau layar CRM (2026-10-02) | `docs/mockups/crm/` |

## 9. Yang masih terbuka (menunggu keputusan user)

1. **Tempat Delivery (DO, Surat Jalan, POD).** Sekarang grup tersendiri di dalam Sales. Pilihan: tetap begitu,
   digabung ke Order ("Order & Delivery"), atau pindah ke Inventory / gudang karena yang mengerjakan bagian gudang.
2. **Menu Sales baru dan CRM di tmerp.test (`tm2sys`).** Tabel CRM sudah ada di `tm2*` (kodenya sama), tetapi menu,
   Help, dan data contoh baru di `ddt*`. Dipasang ke `tm2sys` setelah user menyetujui pratinjau.
3. **Izin per grup kecil.** Saat ini semua memakai `SALES`. Bila Pro-Dev / Costing / admin kirim / Sales perlu
   dibedakan, dibuat izin `PREORDER` dan `CRM` sendiri.
4. **Barang retur masuk stok.** Sales Return mencatat qty kembali dan QA memutuskan nasibnya (Rework / Scrap / Back
   to stock), tetapi belum ada dokumen stok masuk dari customer di Inventory.
5. **Nota kredit / potongan harga** atas komplain: belum ada hubungan ke Finance.
6. **Target Sales vs pencapaian** per Sales Person, dan **pengingat repeat order** (customer yang biasanya pesan
   ulang tiap N hari tetapi belum pesan): belum dibangun; segmen Sleeping adalah langkah pertamanya.
7. **Sales PIC per customer.** Customer belum punya kolom Sales penanggung jawab, jadi daftar belum bisa disaring
   "customer saya".
8. **QA sebagai modul.** Complaint Review adalah layar QA pertama; QC inspeksi proses dan barang jadi belum ada.

## 10. Changelog

| Tanggal | Perubahan |
|---|---|
| 2026-10-02 | Dokumen dibuat. Menu Sales = satu grup besar dengan grup kecil Inquiry & Quotation, Order, Delivery, CRM. CRM dibangun di `ddt*`: Customer 360, Activities & Follow-up, Complaint & Return, Complaint Review (QA). |
