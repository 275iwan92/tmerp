# Laporan Stok & Gudang — referensi dari ERP lain

Permintaan user 2026-10-02: *"reference from all application about warehouse and stock, include refer to popular ERP like
SAP, odoo, dynamics 365, zahir, accurate, Oracle and other apps"*. Riset dilakukan lewat dokumentasi resmi vendor dan
panduan pihak ketiga yang bisa dibuka. Yang dicatat di sini hanya yang benar-benar tertulis di halaman sumbernya; yang
tidak bisa dibuka ditandai **[belum terverifikasi]** dan tidak dijadikan dasar keputusan.

Dokumen pasangannya: `inventoryreport_review.md` (yang dibangun dan yang menunggu keputusan user).

## 1. Yang TIDAK berhasil dikonfirmasi (jangan dijadikan acuan)

- **help.sap.com** tidak mengembalikan isi yang terbaca dan SAP Community menolak (403). Nama transaksi SAP di bawah
  berasal dari SAP Learning dan situs daftar t-code; **susunan kolom** MB51, MB5B, MC.9, dan MI20 belum terverifikasi.
- **Oracle Fusion Cloud Inventory, Sage, Epicor:** tidak diriset. Oracle diwakili **NetSuite**.
- **Accurate 5 (desktop):** tidak diriset; yang dicatat = Accurate Online.
- **Jurnal by Mekari:** pusat bantuannya menolak (403). Nama laporannya (Ringkasan Persediaan Barang, Nilai Persediaan
  Barang, Rincian Persediaan Barang, Kuantitas Stok Gudang, Nilai Stok Gudang, Pergerakan Barang Gudang, Perputaran
  Persediaan) hanya terlihat di ringkasan hasil pencarian.
- **Zahir:** hanya tiga laporan yang terkonfirmasi dari situsnya sendiri; daftar resmi untuk nilai persediaan, stok
  minimum, dan umur stok tidak ditemukan.
- **Rekap selisih stok per bulan:** tidak ada produk yang terverifikasi punya laporan khusus "loss and gain per bulan".
  Jawaban standarnya = daftar selisih hitung / penyesuaian yang disaring per periode. Rekap per bulan di aplikasi ini
  adalah tambahan kita sendiri di atas daftar itu.

## 2. Laporan per produk

Kebutuhan: 1 kartu stok, 2 saldo periode, 3 nilai persediaan, 4 per gudang / bin, 5 stok inspeksi / diblokir,
6 selisih hitung fisik / penyesuaian, 7 lambat bergerak / umur / kedaluwarsa, 8 lainnya.

### SAP (ECC / S/4HANA MM-IM, WM, EWM)

| Kebutuhan | Transaksi | Isi |
|---|---|---|
| 1 | **MB51** Material Document List; **MBGR** (dengan alasan gerak); **MBSM** (dokumen yang dibatalkan) | Dokumen pergerakan barang per material |
| 2 | **MB5B** Stocks for Posting Date | Stok pada tanggal / rentang tanggal; pilihan material, plant, storage location |
| 3 | **MB5L** List of Stock Values: Balances; **MC.9** (juga MC.1 plant, MC.5 storage location, MC.L material group); **MC48** | Nilai stok terhadap saldo akun; analisis stok per material / kelompok |
| 4 | **MMBE** Stock Overview; **MB52** List of Warehouse Stocks on Hand | Satu material lintas plant / storage location / batch; banyak material per storage location |
| 4 (WM / EWM) | **LX02**, **LS24**, **LS26**, **LS25**, **LX03** Bin Status Report, **LX04** Capacity Load Utilization; EWM **/SCWM/MON** | Stok per bin dan keterisian bin |
| 5 | **MB5OA** Valuated GR Blocked Stock | Stok penerimaan yang diblokir |
| 6 | **MI20** Print List of Differences; **MI24** Physical Inventory List; **MI22 / MI23**; **MIDO** | Selisih hitung per dokumen, plant, material |
| 7 | **MC46** Slow-moving items; **MC50** Dead stock; **MB5M** (daftar umur simpan); **LX27** | Material tanpa pemakaian; nilai dead stock; sisa umur simpan per batch |
| 8 | **MB5T** Stock in Transit; **MB53**; **MD04**; **MC44** Inventory Turnover; **MC40 / MC41** ABC; **MB5S** GR/IR; **MBLB** stok di subkon; **MB5K** | Sesuai namanya |

### Odoo (Inventory > Reporting)

| Kebutuhan | Laporan | Isi |
|---|---|---|
| 1 | **Moves History**; **Moves Analysis** | Tanggal, referensi, produk, lot, dari, ke, qty, satuan, status; saring masuk / keluar / internal |
| 2, 3 | **Stock**; **Valuation** | Produk, biaya satuan, nilai total, on hand, free to use, incoming, outgoing. Tanpa susunan awal / masuk / keluar / akhir |
| 4 | **Locations** | Produk, lokasi, package, lot, on hand, reserved; "Inventory at Date" |
| 6 | **Physical Inventory** | Lokasi, produk, lot, on hand, counted, difference, user |
| 7 | **Stock aging** (pivot Valuation) | Qty dan nilai menurut tanggal beli |
| 8 | **Performance**; **Forecasted** | Waktu siklus; proyeksi stok per produk |

### Microsoft Dynamics 365 Business Central

| Kebutuhan | Laporan (ID) | Isi |
|---|---|---|
| 1 | **Inventory - Transaction Detail** (704) | Per item: qty awal, tiap entri naik / turun dengan saldo berjalan, qty akhir |
| 2, 3 | **Inventory Valuation** (1001) | Saldo awal (qty dan nilai), kenaikan, penurunan, saldo akhir; saringan lokasi |
| 4 | **Warehouse Bin List** (7319); **Warehouse Adjustment Bin** (7320); **Status** (706) | Bin dan isinya |
| 6 | **Phys. Invt. Recording**; **Phys. Inventory Order Diff.**; **Posted Phys. Invt. Orders** | Qty harapan, qty tercatat, selisih positif / negatif per item, lokasi, bin |
| 7 | **Item Age Composition - Value** (5808); **Item Expiration - Quantity** (5809) | Nilai stok menurut umur terima; qty yang akan kedaluwarsa |
| 8 | **Item - ABC Analysis** (723); **Inventory Availability** (705); **Inventory - Availability Plan** (719) | Sesuai namanya |

### Microsoft Dynamics 365 Supply Chain Management

| Kebutuhan | Laporan | Isi |
|---|---|---|
| 2, 3 | **Inventory value**; **Inventory value report storage** | Qty dan nilai finansial / fisik, biaya rata-rata, saldo awal opsional; per item, kelompok, site, gudang |
| 4 | **On-hand list** | Fisik, dipesan, tersedia; dimensi sampai lokasi / batch / licence plate |
| 5 | **Inventory blocking**, quality orders | Qty yang diblokir selama inspeksi |
| 7 | **Inventory aging report storage** | Sampai empat rentang umur, qty dan nilai |

### Oracle NetSuite (Reports > Inventory/Items)

| Kebutuhan | Laporan | Isi |
|---|---|---|
| 1 | **Inventory Activity Detail** | Qty per transaksi per item |
| 2 | **Stock Ledger** | Qty dan nilai awal, penerimaan, pengeluaran, qty dan nilai akhir; saring tanggal, lokasi |
| 3 | **Inventory Valuation Summary / Detail** | Item, nilai, **% dari total nilai**, qty on hand, nilai satuan |
| 6 | **Physical Inventory Worksheet** | Lembar hitung terhadap qty sistem |
| 7, 8 | **Inventory Turnover**; **Current Inventory Snapshot** | Biaya penjualan, rata-rata nilai stok, perputaran, hari stok; titik pesan ulang |

### Zahir Accounting

- **Laporan Kartu Stok** dan **Kartu Stok Per Gudang** (kebutuhan 1, 4): masuk, keluar, saldo, dengan harga pokok.
- **Laporan Mutasi Barang** (kebutuhan 2): pergerakan persediaan dalam satu periode.
- **Stock Opname** adalah transaksi di modul Persediaan; selisihnya diposting ke akun yang dipilih (mis. "selisih stock
  opname"). Laporan selisih yang bernama tidak ditemukan.

### Accurate Online (Laporan > Daftar Laporan > Persediaan / Gudang)

| Kebutuhan | Laporan | Isi |
|---|---|---|
| 1 | **Kartu Stok** | Rincian masuk, keluar, stok akhir; per periode dan gudang |
| 2 | **Mutasi per Barang**; **Mutasi per Gudang**; **Ringkasan Pemindahan Barang** | Mutasi per item, per gudang; ringkasan transfer |
| 3 | **Nilai Persediaan**; **Rincian Nilai Persediaan** | Nilai persediaan pada tanggal tertentu, bisa per Kategori Barang |
| 4 | **Kuantitas Barang per Gudang**; **Barang per Gudang** | Qty per gudang |
| 6 | **Penyesuaian Stok Barang** | Baris penyesuaian; kuantitas lama, tipe penyesuaian, stok baru lewat Modify |
| 7 | **Umur Persediaan**; **Nomor Produksi Segera Kadaluarsa** | Barang lambat; batch yang akan kedaluwarsa |
| 8 | **Barang Stok Minimum**; **Ketersediaan Stok Penjualan**; **Histori Nomor Seri/Produksi** | Di bawah minimum; tersedia untuk dijual; riwayat batch |

### ERPNext dan QuickBooks Online

- **ERPNext:** Stock Ledger (1); **Stock Balance** (2, 3: opening / in / out / balance, qty dan nilai, per item dan
  gudang); Warehouse Wise Stock Balance (4); Stock Ageing (7); Stock Projected Qty, Itemwise Recommended Reorder Level,
  Negative Batch Report, Stock and Account Value Comparison (8).
- **QuickBooks Online:** Inventory Valuation Summary / Detail (3), Physical Inventory Worksheet (6).

## 3. Set standar, dan padanannya di aplikasi ini

"Ada" = terkonfirmasi di halaman yang dibuka. Kolom terakhir = laporan kita (menu Inventory).

| # | Jenis laporan | Ada di | Laporan kita | Status |
|---|---|---|---|---|
| 1 | Kartu stok / buku item: tanggal, dokumen, masuk, keluar, saldo berjalan | SAP, Odoo, Business Central, D365 SCM, NetSuite, Zahir, Accurate, ERPNext | **Stock Card** (semua kelas, berperiode, saldo awal + saldo berjalan); Stock Card per kelas | dibangun |
| 2 | Saldo periode: awal, masuk, keluar, akhir (qty dan nilai) per item | Business Central, D365 SCM, NetSuite, Zahir, Accurate, ERPNext | **Stock Recapitulation** | dibangun |
| 3 | Nilai persediaan per kategori, dengan % dari total | SAP, Odoo, Business Central, NetSuite, Accurate, ERPNext, QuickBooks | **Stock Summary** | dibangun |
| 4 | Stok per gudang / lokasi / bin | SAP, Odoo, Business Central, D365 SCM, Zahir, Accurate, ERPNext | **Stock by Location** (terbuka terkelompok per gedung) | sudah ada, grup awal diubah |
| 5 | Stok inspeksi / diblokir / karantina | SAP, D365 SCM | **Stock in Inspection** | dibangun |
| 6 | Lembar hitung dan selisih hitung fisik / penyesuaian | SAP, Odoo, Business Central, Accurate | **Stock Opname** | dibangun |
| 6b | Rekap selisih per bulan | tidak ada yang terverifikasi | **Stock Loss & Gain** (tambahan kita) | dibangun |
| 7a | Umur stok (rentang hari) | Odoo, Business Central, D365 SCM, Accurate, ERPNext | — | belum; backlog 05.30 |
| 7b | Lambat bergerak / dead stock | SAP, Accurate | Slow Moving Item per kelas; dead stock merah di Stock by Location | sudah ada |
| 7c | Kedaluwarsa / umur simpan | SAP, Odoo, Business Central, Accurate, ERPNext | — | belum; menunggu FEFO (backlog 05.7) |
| 8a | Di bawah minimum / pesan ulang | NetSuite, Accurate, ERPNext | Stock Balance per kelas (tanda Below minimum); Material Planning | sudah ada per kelas |
| 8b | Ketersediaan / proyeksi stok | SAP, Odoo, Business Central, D365 SCM, NetSuite, Accurate, ERPNext | Material Planning, Material Booking | sudah ada (modul Planning) |
| 8c | Stok dalam perjalanan | SAP, Accurate | — | belum; hanya perlu bila ada gudang beda kota (backlog 05.13) |
| 8d | Perputaran persediaan | SAP, NetSuite | kolom Turnover di Stock Recapitulation | dibangun sebagai kolom |
| 8e | Analisis ABC | SAP, Business Central | — | belum; backlog 05.31 |
| 8f | Barang diterima belum ditagih | SAP | — | belum diperiksa padanannya di Purchase / Finance; backlog 05.33 |
| 8g | Stok negatif | SAP, ERPNext | saringan Negative di Stock Recapitulation | dibangun sebagai saringan |
| 8i | Nilai stok vs saldo akun persediaan | SAP, Odoo, Business Central, D365 SCM, ERPNext | — | belum; backlog 05.32 (perlu keputusan accounting) |

Kesimpulan riset: **set inti** yang ada di hampir semua produk = kartu stok (1), nilai persediaan per tanggal (3), stok per
gudang (4), lembar hitung dengan selisih (6); saldo periode (2) ada di semua produk yang punya pelaporan periode. Keenam
laporan yang dibangun menutup set inti itu ditambah stok inspeksi (hanya ada di ERP besar) dan rekap selisih bulanan.

## 4. Sumber

SAP
- https://learning.sap.com/courses/inventory-management-and-physical-inventory-in-sap-s-4hana/using-classic-stock-lists-and-document-lists
- https://learning.sap.com/courses/inventory-management-and-physical-inventory-in-sap-s-4hana/using-analytical-apps-for-inventory-management
- https://learning.sap.com/courses/processes-in-sap-s-4hana-ewm/managing-the-warehouse
- https://vkprabakaranblog.wordpress.com/2018/09/08/sap-materials-management-standard-reports/
- https://www.erpgreat.com/warehouse/standard-reports-found-in-sap-warehouse.htm
- https://www.sap-tcodes.org/tcode/mc.9.html

Odoo
- https://www.odoo.com/documentation/17.0/applications/inventory_and_mrp/inventory/warehouses_storage/reporting/moves_history.html
- https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/warehouses_storage/reporting/stock.html
- https://www.odoo.com/documentation/17.0/applications/inventory_and_mrp/inventory/warehouses_storage/reporting/locations.html
- https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/warehouses_storage/reporting/aging.html
- https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/warehouses_storage/inventory_management/count_products.html

Microsoft Dynamics 365
- https://learn.microsoft.com/en-us/dynamics365/business-central/inventory-wms-reports
- https://learn.microsoft.com/en-us/dynamics365/business-central/reports/report-704
- https://learn.microsoft.com/en-us/dynamics365/business-central/reports/report-1001
- https://learn.microsoft.com/en-us/dynamics365/business-central/reports/report-5809
- https://learn.microsoft.com/en-us/dynamics365/business-central/inventory-how-count-inventory-with-documents
- https://learn.microsoft.com/en-us/dynamics365/supply-chain/cost-management/inventory-value-report-storage
- https://learn.microsoft.com/en-us/dynamics365/supply-chain/cost-management/inventory-aging-report-storage
- https://learn.microsoft.com/en-us/dynamics365/supply-chain/inventory/inventory-on-hand-list
- https://learn.microsoft.com/en-us/dynamics365/supply-chain/inventory/inventory-blocking

Oracle NetSuite
- https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/chapter_N2353200.html
- https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_4521145431.html
- https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_N2358933.html
- https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_N2356560.html

Zahir
- https://zahiraccounting.com/en/contoh-laporan-persediaan/
- https://zahiraccounting.com/id/blog/cara-mudah-mengontrol-stok-barang/
- https://www.zahironline.com/cara-mudah-stock-opname-otomatis-melalui-zahir-accounting-6/

Accurate Online
- https://help.accurate.id/product/accurate-online/fitur-aol/laporan/daftar-laporan/persediaan-kategori/
- https://help.accurate.id/product/accurate-online/fitur-aol/laporan/daftar-laporan/penyesuaian-stok/
- https://www.doniirvan.id/accurate-online/fitur-accurate-online/persediaan/
- https://penjualanonline.id/cara-melihat-selisih-stok-opname-di-accurate-online/

ERPNext dan QuickBooks
- https://github.com/frappe/erpnext/tree/develop/erpnext/stock/report
- https://docs.frappe.io/erpnext/user/manual/en/stock-ledger
- https://quickbooks.intuit.com/learn-support/en-us/help-article/report-management/use-reports-see-sales-inventory-status/L7ocoLmqP_US_en_US
