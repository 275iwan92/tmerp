# Inventory & Warehouse v2.01 — Rujukan: Stok, Pergerakan Stok, Alur Kerja, Aliran Data

> **Status: 2026-10-08.** Dasar rujukan untuk Rencana Teknis (`inventorywarehouse_v201_technical_plan.md`).
> Dicari atas permintaan user: *"make sure you have the best references about STOCK, STOCK MOVING and all workflow, data flow."*
> Produk yang dirujuk: **SAP** (MM, EWM), **Odoo** (Inventory, Subcontracting), **ERPNext**, **Microsoft Dynamics 365 Supply Chain**, **Oracle NetSuite**.
> Aturan baca: kolom **Kualitas** = `R` halaman resmi pabrikan, `K` komunitas / artikel pihak ketiga (dipakai hanya sebagai pendukung). Isi halaman resmi diringkas oleh alat baca; angka dan nama tidak saya tambah dari ingatan.
> Daftar tautan ada di bagian 12.

---

## 1. Kesimpulan yang dipakai (ringkas)

| # | Prinsip bersama semua produk besar | Dipakai di 2.01 |
|---|---|---|
| 1 | **Setiap pergerakan stok punya asal dan tujuan** (lokasi ke lokasi), termasuk lokasi virtual: vendor, customer, kerugian, produksi, transit | Ya: lokasi virtual (area WIP, subkon, transit, loss) |
| 2 | **Buku stok (ledger) mencatat pergerakan; saldo adalah hasilnya** | Ya: kartu stok berantai sudah ada |
| 3 | **Satu dokumen / kode tipe pergerakan menentukan efek stok dan akun** (SAP movement type; ERPNext purpose; NetSuite jenis transaksi) | Ya: katalog tipe pergerakan di bagian 3 |
| 4 | **Status stok** memisahkan "ada" dari "boleh dipakai" (NetSuite Inventory Status; SAP stock types; Odoo langkah QC) | Ya: status batch Quarantine / Conditional / Released / Rejected |
| 5 | **Penerimaan bertahap**: terima di area transit, lalu pindah ke penyimpanan (Odoo 1 / 2 / 3 langkah; SAP EWM interim bin; D365 work Pick lalu Put) | Ya: Receiving, Putaway, jalur URGENT melompati putaway |
| 6 | **Reservasi memakai strategi ambil** (FIFO, LIFO, FEFO, terdekat) dan menghasilkan baris per lot / bin | Ya: pick list dengan aturan area |
| 7 | **Transfer tidak mengubah nilai**; transfer jauh memakai lokasi transit | Ya: transfer dua langkah |
| 8 | **Subkon**: bahan dikirim ke lokasi / stok vendor, tetap milik kita; penerimaan hasil mengonsumsi komponen | Ya: vendor sebagai lokasi virtual milik perusahaan |
| 9 | **Opname menghitung selisih lalu memposting penyesuaian sebagai pergerakan** yang terlacak | Ya: penyesuaian = dokumen, bukan edit saldo |
| 10 | **Fisik dan finansial dipisah**: penerimaan fisik lebih dulu, nilai final saat tagihan / penutupan | Ya: GR/IR dan landed cost |
| 11 | **Rata-rata tertimbang** ada dua gaya: berjalan (perpetual) dan periodik dengan penutupan | Dipilih: berjalan per item (D13, D15) |

---

## 2. Model stok: buku pergerakan dan saldo

| Produk | Konsep inti | Kualitas | Catatan untuk kita |
|---|---|---|---|
| **Odoo** | `stock.move` = satu pergerakan produk dari satu lokasi ke lokasi lain; `stock.move.line` = rincian per lot / serial / lokasi; reservasi mengisi baris lewat "gather" kuant menurut strategi ambil | K (artikel) + R (dokumentasi lokasi) | Pemisahan dokumen (move) dan rincian (line) = padanan **dokumen stok dan baris kartu stok** kita |
| **ERPNext** | **Stock Ledger Entry** mencatat qty dan nilai masuk / keluar / transfer; dibuat oleh Sales Invoice dan Purchase Invoice (Update Stock), Delivery Note, Purchase Receipt, Stock Entry, Stock Reconciliation. Field: Incoming Rate, Balance Value, Valuation Rate | R | Sama dengan `rm_stockcard` kita: satu baris per pergerakan, nilai saldo di baris |
| **SAP MM** | Dokumen material + **tipe pergerakan** tiga karakter; tipe pergerakan mengatur update qty, update akun stok dan konsumsi, pemilihan field, dan cetakan | R | Kita pakai gagasan "tipe pergerakan menentukan akun" lewat `doc_type` dan `doc_cat` |
| **SAP EWM** | **Kuant** = stok satu material di satu bin; meletakkan material di bin kosong membuat kuant | R | Padanan **saldo per item + bin + batch** kita |
| **Dynamics 365** | **Inventory transaction** per pergerakan dengan status; fisik (Received / Deducted) dan finansial (Purchased / Sold) | R | Pemisahan tanggal fisik dan tanggal posting kita sudah ada (`post_date`, `period_id`) |
| **NetSuite** | **Inventory Detail** = rincian lot, serial, bin, status, qty pada baris transaksi | R | Padanan **batch + pallet + bin** pada baris dokumen |

**Yang kita adopsi:** buku pergerakan berantai per posisi (sudah ada), ditambah **rantai nilai per item** (D15). Saldo tetap hasil; koreksi lewat dokumen lawan.

---

## 3. Katalog pergerakan stok (tipe pergerakan)

| Kebutuhan | SAP | ERPNext | Odoo | NetSuite / D365 | Dokumen kita |
|---|---|---|---|---|---|
| Terima dari PO | 101: Dr stok, Cr **GR/IR** | Purchase Receipt: Dr Stock In Hand, Cr **Stock Received But Not Billed** | Receipt (vendor ke stok) | Item Receipt; D365 product receipt = status **Received**, tagihan = **Purchased** | PV / Stock In RM (sudah ada) |
| Terima tanpa PO, saldo awal | 501 s.d. 581 (penerimaan tanpa order, saldo awal) | Material Receipt: Dr Stock In Hand, Cr **Stock Adjustment** | Inventory adjustment | Inventory Adjustment | Stock In, adjustment plus |
| Keluar ke produksi | 261: Cr stok, Dr biaya order | Material Transfer for Manufacture lalu Manufacture | Konsumsi (stok ke lokasi produksi) | Assembly / Work Order Completion | Stock Out ke WO |
| Hasil produksi | Penerimaan order produksi | Manufacture: bahan terpakai, barang jadi masuk | Produksi (lokasi produksi ke stok) | Work Order Completion | Hasil produksi (SFG / FG) |
| Kirim ke customer | Kelompok 600-an (pengiriman) | Delivery Note: Dr **COGS**, Cr Stock In Hand | Stok ke customer | Item Fulfillment; D365 packing slip = **Deducted**, tagihan = **Sold** | Delivery Order dan HPP (sudah ada) |
| Transfer antar lokasi | 301 / 311 (plant ke plant / sloc ke sloc, satu langkah) | Material Transfer: Dr gudang tujuan, Cr gudang asal pada **valuation rate** | Lokasi **Transit** | Inventory Transfer / Bin Transfer: **tanpa untung rugi nilai** | Stock Transfer (sudah satu langkah; dua langkah = baru) |
| Selisih opname | 701 s.d. 717 (selisih persediaan) | Stock Reconciliation / Material Issue ke **Stock Adjustment** | Inventory adjustment menghasilkan stock move line | Inventory Adjustment (qty **dan** nilai) | Adjustment & Physical Count (sudah ada) |
| Scrap / pemusnahan | 551 | Material Issue | Tujuan: lokasi **Inventory Loss** | Inventory Adjustment | Scrap (dengan berita acara) |
| Kirim bahan ke subkon | **541** ke stok vendor (special stock **O**) | **Send to Subcontractor** | Resupply ke lokasi subkon | – | **Send Material** (baru) |
| Terima hasil subkon | 101 + **543** (komponen terkonsumsi otomatis); 545 untuk by-product | Penerimaan barang jadi subkon | Validasi receipt: komponen dikonsumsi di lokasi subkon, barang jadi masuk | – | PV SUBCON (sudah ada) |
| Konsinyasi | Special stock **K** (konsinyasi vendor), **W** (di customer); 411 mengubah special stock jadi stok bernilai | – | Pemilik stok (owner) | – | Stok pemilik Customer (D3) |
| Repack | – | **Repack**: nilai total stok tidak berubah | – | – | Repack (cakupan P3) |
| Ubah status stok | Jenis stok (bebas pakai, inspeksi mutu, blokir) | – | Langkah QC | NetSuite **Inventory Status Change** | Status batch (QC) |

**Yang kita adopsi:** satu **katalog tipe pergerakan** (kolom terakhir) dengan efek pasti pada qty, nilai, dan akun; nilai tidak berubah pada transfer dan ubah status; selisih opname dan scrap lewat akun tersendiri.

---

## 4. Lokasi, lokasi virtual, dan status lokasi

| Produk | Konsep | Kualitas | Adopsi |
|---|---|---|---|
| **Odoo** | Tipe lokasi: **Internal, Vendor, Customer, Inventory Loss, Production, Transit, View**. "Setiap pergerakan butuh lokasi asal dan tujuan." Lokasi bersarang (`WH/Stock/Zone A/Refrigerator 1`). Fitur: cycle count, strategi ambil, aturan putaway, kategori penyimpanan (kapasitas) | R | Jenis area baru kita: **WIP, At Subcontractor, In Transit, Loss** (D24 dan layar Storage Areas) |
| **D365** | Status lokasi: **Undetermined, Empty, Picking, Storage**; bidang: item di lokasi, aktivitas terakhir, tanggal aging; bisa dipakai di directive lokasi; hanya berubah lewat transaksi gudang | R | Padanan peta kita (Empty / Partly / Full) dan **Stack Monitor**; usulan: aktivitas terakhir dan aging per bin sebagai bahan laporan |
| **SAP EWM** | Storage type, storage bin, activity area; stok awal diterima di **interim storage bin** (dok terima) lalu dipindah | R | Area Receiving dan QC Hold yang sudah ada |
| **NetSuite** | Bin dan **Inventory Status**; transfer memindahkan item per status per bin | R | Bin + status batch |

---

## 5. Status stok: "ada" tidak sama dengan "boleh dipakai"

| Produk | Mekanisme | Kualitas |
|---|---|---|
| **NetSuite Inventory Status** | Status default **Good**; status lain (mis. Inspection, Damaged). Setiap status punya bendera **Available for Commitment** dan **Available for Allocation**; bila mati, qty tetap on hand tetapi **tidak tersedia**. Status ada di Item Receipt, Bin Transfer, Inventory Adjustment, Inventory Transfer, Inventory Status Change. Tidak bisa dipilih untuk item dengan lot atau serial (keterbatasan) | R |
| **SAP** | Jenis stok: bebas pakai, dalam inspeksi mutu, diblokir; *usage decision* memindahkan stok | R (sebagian dari hasil pencarian) |
| **Odoo** | Penerimaan tiga langkah: Input, **Quality**, Stock; stok baru tersedia setelah dipindah ke stok | R |
| **D365** | Status inventori per lokasi; pesanan karantina dan quality order | R (pencarian sebelumnya) |

**Yang kita adopsi:** status per **batch** (Quarantine, Conditional, Released, Rejected, Blocked) dengan **bendera ketersediaan per status** (apakah dihitung Available, apakah boleh dialokasikan booking dan pick). NetSuite menandai batasan status pada item berlot; karena kita justru bertumpu pada batch, status melekat pada master batch, bukan pada baris stok.

---

## 6. Alur masuk (inbound)

| Produk | Urutan | Kualitas |
|---|---|---|
| **Odoo** | **1 langkah**: langsung ke stok. **2 langkah**: bongkar di area input lalu transfer ke stok (belum tersedia sebelum dipindah). **3 langkah**: input, **kontrol kualitas**, stok | R |
| **SAP EWM** | Inbound delivery (dari PO / ASN) menjadi **warehouse request**; sistem membuat **warehouse task** (apa dipindah ke bin mana); task dikelompokkan ke **warehouse order** (paket kerja); stok diterima ke **interim bin**, lalu putaway ke bin tujuan; stok tersedia setelah di bin tujuan. S/4HANA menyediakan versi sinkron yang membuat task putaway otomatis | R |
| **D365 WMS** | PO dikonfirmasi; penerimaan lewat aplikasi mobile menghasilkan **work** dengan tipe **Pick** (dari dok) dan **Put** (ke lokasi) beserta license plate; status lokasi berubah menjadi Storage | R |
| **ERPNext** | Purchase Receipt (Dr Stock In Hand, Cr Stock Received But Not Billed); **Putaway Rule** menentukan gudang atau kapasitas tujuan | R |

**Yang kita adopsi:** *Receiving* (dok terima), *Putaway* (tugas dengan usulan bin; petugas mengonfirmasi lewat scan), dan **jalur URGENT** yang melompati putaway (dock-to-line). Padanan sampling QC: langkah Quality Odoo / interim bin SAP. Tidak mengadopsi warehouse order berlapis (terlalu berat untuk pabrik ini); **Task Board** (Fase 6) cukup.

---

## 7. Reservasi, alokasi, dan alur keluar (outbound)

| Produk | Urutan | Kualitas |
|---|---|---|
| **Odoo** | Cek ketersediaan memanggil `gather`: mengambil kuant tersedia **menurut strategi ambil**; membuat move line; berhenti saat kuant habis atau kebutuhan terpenuhi. Strategi: FIFO, LIFO, FEFO, Closest Location, Least Packages | K + R (strategi) |
| **D365** | Reservasi menurut **hierarki reservasi** (dimensi wajib site, warehouse, inventory status, location, license plate; batch dan serial bila dipakai); batch bertanggal kedaluwarsa paling awal dicadangkan lebih dulu; lalu **Release to warehouse** membuat work Picking; picking memperbarui status lokasi menjadi Picking | R |
| **SAP EWM** | Pengiriman keluar menjadi warehouse request; warehouse task picking (task produk pertama), dikelompokkan di warehouse order | R |
| **NetSuite** | Commit / allocation menurut status yang boleh; Reallocate Items manual | R |

**Yang kita adopsi:** booking (Production > Planning) menghasilkan **kebutuhan per bahan**; **pick list** (Warehouse) mengalokasikan **pallet, batch, bin** menurut **aturan area** (FIFO / FEFO) dan syarat bahan (serat, sertifikasi, status batch yang boleh); konfirmasi dengan scan dua kali.

---

## 8. Transfer

| Produk | Perilaku | Kualitas |
|---|---|---|
| **NetSuite** | Inventory Transfer memindahkan item per status per bin ke lokasi baru **atau status baru** di lokasi yang sama; **tidak ada untung rugi nilai** | R |
| **ERPNext** | Material Transfer: Dr gudang tujuan, Cr gudang asal, pada **valuation rate** | R |
| **Odoo** | Lokasi **Transit** untuk transfer antar gudang | R |
| **SAP** | 301 plant ke plant, 311 sloc ke sloc (satu langkah) | R |

**Yang kita adopsi:** nilai ikut pindah pada **Average item** (satu angka, jadi tidak ada selisih); transfer dua langkah lewat **In Transit**; selisih terima berstatus Discrepancy; ubah status (bukan lokasi) juga **tanpa perubahan nilai**.

---

## 9. Subkon dan stok milik pihak lain

| Produk | Perilaku | Kualitas |
|---|---|---|
| **Odoo** | Resupply komponen ke subkon (lokasi subkon, perlu multi-lokasi); penerimaan hasil memvalidasi receipt: **komponen dikonsumsi di lokasi subkon** dan barang jadi masuk stok | R / K |
| **ERPNext** | Stock Entry **Send to Subcontractor**: bahan pindah dari gudang perusahaan ke gudang subkon | R |
| **SAP** | **541** memindahkan bahan ke stok vendor (special stock **O**, milik sendiri); **543** mengonsumsi komponen saat penerimaan; **545** untuk by-product; special stock **K** (konsinyasi vendor), **W** (konsinyasi di customer), **E** (stok pesanan penjualan); **411** mengubah special stock menjadi stok bernilai dan menimbulkan hutang | R |

**Yang kita adopsi:** vendor = **lokasi virtual milik perusahaan** (nilai tetap aset); penerimaan hasil subkon sudah menjurnal HPP Jasa Subkon; **pemilik stok** (Company / Customer / Supplier) sebagai atribut (padanan special stock SAP); stok pemilik pihak lain **tidak dinilai**.

---

## 10. Opname dan cycle count

| Sumber | Praktik | Kualitas |
|---|---|---|
| **Odoo** | Halaman Physical Inventory: qty tercatat vs qty hitung, selisih otomatis (hijau lebih, merah kurang), tanggal hitung terjadwal, frekuensi hari per produk; **Apply** menghasilkan stock move line di riwayat; riwayat dan pembalikan | R |
| **SAP** | Tipe pergerakan 701 s.d. 717 untuk selisih persediaan | R |
| **Praktik umum** | **Cycle count**: hitung subset terjadwal; metode ABC (kelas A lebih sering, kelas C setahun sekali); pindai bin, bandingkan, catat selisih, picu rekonsiliasi; tinjau klasifikasi berkala | K |

**Yang kita adopsi:** hitung terjadwal per area (**hitung tiap N hari**, D24), **bin dikunci selama dihitung** (belum ada), selisih menunggu persetujuan lalu diposting sebagai penyesuaian bernomor dengan jurnal; ABC untuk frekuensi (P3).

---

## 11. Nilai, akuntansi, dan aliran data

| Produk | Aliran | Kualitas |
|---|---|---|
| **ERPNext perpetual** | Purchase Receipt: **Dr Stock In Hand / Cr Stock Received But Not Billed**. Delivery Note dan Sales Invoice (Update Stock): **Dr COGS / Cr Stock In Hand** pada FIFO atau Moving Average. Material Receipt: Dr Stock In Hand / Cr Stock Adjustment; Material Issue kebalikannya; **Material Transfer: Dr gudang tujuan / Cr gudang asal** | R |
| **SAP** | 101: Dr stok / Cr **GR/IR**. 201: Cr stok / Dr akun biaya (cost center). Akun ditentukan oleh tipe pergerakan | R |
| **D365** | Tanggal **fisik** (penerimaan, packing slip) dan **finansial** (tagihan, penutupan). Status Received / Purchased dan Deducted / Sold. **Weighted average** = (Q1xP1 + Q2xP2 + ... ) / (Q1 + Q2 + ...); penutupan periode menyelesaikan penerimaan terhadap issue virtual sehingga **semua issue periode itu memakai rata-rata yang sama**; opsi **Include physical value** memakai penerimaan fisik untuk taksiran harga issue selama periode; ada **recalculation** | R |
| **Odoo** | Metode biaya standar, FIFO, **AVCO**, diatur per kategori produk | K |
| **ERPNext Repost Item Valuation** | Transaksi bertanggal mundur memicu hitung ulang SLE dan **posting ulang GL** bila perlu | R |
| **NetSuite** | Inventory Adjustment mengubah **qty dan nilai** tanpa PO | R |

**Dua gaya rata-rata (penting untuk keputusan D13):**

| | Berjalan (perpetual / moving) | Periodik dengan penutupan |
|---|---|---|
| Rumus | Avg baru = (qty saldo x Avg lama + qty masuk x harga masuk) / (qty saldo + qty masuk), tiap penerimaan | Rata-rata tertimbang seluruh penerimaan periode; semua issue periode memakai angka yang sama |
| Nilai issue | Diketahui seketika | Taksiran selama periode, **final saat tutup** |
| Contoh produk | Odoo AVCO, ERPNext Moving Average, SAP MAP | D365 weighted average dengan inventory close |
| Kita | **Dipilih: berjalan, per item** | Tidak dipakai |

Rumus dan contoh kenaikan harga yang kita pakai: cakupan 2.01 bagian 8.2 sampai 8.4 dan Help `guide-inv-valuation`.

**Aliran data lintas modul yang kita adopsi** (padanan ERPNext dan SAP):

```
Procurement (PO)  -> Warehouse: Receiving -> Inventory: GRN  -> Accounting: Dr Persediaan / Cr GR/IR
Supplier invoice  -> Accounting: Dr GR/IR / Cr Hutang           (tanggal finansial)
Production (WO)   -> Inventory: issue (Dr WIP / Cr Persediaan RM); hasil (Dr FG / Cr WIP)
Sales (CO)        -> Booking -> Warehouse: Pick list -> Inventory: issue -> Delivery -> Accounting: Dr HPP / Cr Persediaan FG
Inventory: transfer -> tanpa jurnal;  opname / scrap -> Dr/Cr Selisih Persediaan / Beban Scrap
```

---

## 12. Batch, kedaluwarsa, dan telusur

| Sumber | Praktik | Kualitas |
|---|---|---|
| **Odoo** | Strategi ambil **FEFO** memakai tanggal kedaluwarsa, bukan tanggal terima | R |
| **D365** | Batch bertanggal kedaluwarsa paling awal dicadangkan lebih dulu | R |
| **Modul pihak ketiga** (Odoo, ERPNext) | Silsilah batch maju dan mundur, recall satu klik, peringatan hampir kedaluwarsa | K (hanya pendukung; bukan dasar keputusan) |

**Yang kita adopsi:** master batch, FEFO untuk zona bertanggal kedaluwarsa, **telusur maju dan mundur** lewat rantai batch, dokumen issue, work order, dan pengiriman (Q-08).

---

## 13. Apa yang sengaja BERBEDA dari rujukan

| Rujukan | Kita | Alasan |
|---|---|---|
| SAP / EWM: warehouse order berlapis, task otomatis penuh | Putaway dan pick list ringan, **petugas mengonfirmasi** | Skala pabrik; "engine suggests, officer confirms" |
| NetSuite: status tidak untuk item berlot | Status **di master batch** | Batch adalah unit mutu kita |
| D365: periodik dengan penutupan | **Berjalan per item** | Keputusan user (D13, D15); angka biaya diketahui seketika untuk costing aktual |
| Odoo: QC menahan penerimaan (3 langkah) | **Sampling + jalur URGENT** | Keputusan user: QC sambil proses, URGENT langsung ke produksi |
| Pemisahan dokumen Odoo move dan move line | Dokumen stok + baris kartu stok yang sudah ada | Sudah dibangun dan benar |

---

## 14. Tautan sumber

**Resmi (R)**
- ERPNext: [Stock Ledger](https://docs.frappe.io/erpnext/stock-ledger), [Stock Entry purpose](https://docs.frappe.io/erpnext/stock-entry-purpose), [Perpetual Inventory](https://docs.frappe.io/erpnext/perpetual-inventory), [Repost Item Valuation](https://docs.frappe.io/erpnext/repost-item-valuation), [Stock Reservation](https://docs.frappe.io/erpnext/stock-reservation)
- Odoo: [Locations](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/warehouses_storage/inventory_management/use_locations.html), [Removal strategies](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/removal_strategies.html), [Reservation methods](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/reservation_methods.html), [Counting](https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/warehouses_storage/inventory_management/count_products.html), [Receipts in three steps](https://www.odoo.com/documentation/user/13.0/nl/inventory/management/incoming/three_steps.html), [Subcontracting](https://www.odoo.com/documentation/user/14.0/manufacturing/management/subcontracting.html)
- SAP: [Movement types and special stock](https://help.sap.com/saphelp_46C/helpdata/EN/fc/6cec6eb435d1118b3f0060b03ca329/content.htm), [EWM goods receipt](https://learning.sap.com/courses/processes-in-sap-s-4hana-ewm/processing-goods-receipt), [EWM warehouse orders](https://learning.sap.com/learning-journeys/applying-supply-chain-execution-in-sap-s-4hana-cloud-private-edition/processing-warehouse-orders-in-ewm), [Backorder processing](https://learning.sap.com/courses/experiencing-supply-chain-business-scenarios-in-sap-s-4hana-cloud-public-edition/exploring-backorder-processing_fed6ddd5-39be-41ab-a977-e41a1c3715fe), [Reservation management](https://help.sap.com/saphelp_46C/helpdata/EN/a5/63374743a211d189410000e829fbbd/content.htm), [PRT availability](https://help.sap.com/saphelp_46C/helpdata/EN/5b/ae35a44b8611d182ba0000e829fbfe/content.htm)
- Dynamics 365: [Warehouse location status](https://learn.microsoft.com/en-us/dynamics365/supply-chain/warehousing/warehouse-location-status), [Reservation hierarchies](https://learn.microsoft.com/en-us/training/modules/configure-warehouse-management-dyn365-supply-chain-mgmt/6-reservation-hierarchies), [Inventory transaction details](https://learn.microsoft.com/en-us/dynamics365/supply-chain/inventory/inventory-transactions-details), [Weighted average with physical value](https://learn.microsoft.com/en-us/dynamics365/supply-chain/cost-management/weighted-average-physical-value-marking), [Quality associations](https://learn.microsoft.com/en-us/dynamics365/supply-chain/inventory/quality-associations)
- NetSuite: [Inventory Status](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_1515696627.html), [Reallocating Items](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_N2263567.html), [Committing Orders](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_3765928460.html), [Entering an Inventory Adjustment](https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_161981111273.html)

**Komunitas / pihak ketiga (K), hanya pendukung**
- [Odoo stock move model guide](https://www.dasolo.ai/en/blog/odoo-data-api-5/odoo-stock-move-model-guide-160), [Odoo stock move line walkthrough](https://write.agrevolution.in/odoos-inventory-module-walkthrough-stock-move-line-part-2-8930cbe751cb), [SAP movement types overview](https://www.sapdocs.info/631/goods-movement-types-in-sap-mm/amp/), [Cycle count guide](https://www.cleverence.com/articles/business-blogs/guide-cycle-count-4729/), [Odoo landed cost / AVCO](https://www.cleverence.com/articles/for-business/landed-cost-odoo-4927/), [Short shipment claims](https://racklify.com/encyclopedia/short-shipment-claims-liability-documentation-and-recovery/), [SAP MAP and price difference](https://blogs.sap.com/2021/12/09/sap-mm-change-of-material-moving-average-price-after-goods-receipt-and-invoice-verification-posting-for-po/)

## 15. Batas rujukan ini (jujur)

- Halaman resmi diringkas oleh alat baca; **detail konfigurasi tiap produk tidak diverifikasi di produknya**.
- Beberapa halaman tidak memuat topik yang saya cari (ERPNext Stock Ledger tidak membahas tanggal mundur atau kekekalan; dokumen ERPNext reservasi tidak membahas perubahan qty). Itu tidak saya isi dari ingatan.
- Angka tipe pergerakan SAP di tabel bagian 3 hanya yang muncul di sumber (101, 261, 301, 311, 541, 543, 545, 551, 701, 201, 411); kelompok lain disebut per rentang.
- Odoo AVCO tercatat dari artikel pihak ketiga (K), bukan dokumentasi resmi.
- Pola **Conditional**, **dock-to-line**, dan **deteksi kurang kirim** adalah adaptasi kita, bukan fitur baku produk mana pun.
