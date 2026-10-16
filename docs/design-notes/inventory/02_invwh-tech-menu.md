# Technical Plan: Menu Split Inventory and Warehouse

Bagaimana menu tersusun di 2.01: dua modul, Inventory (dokumen, nilai, pemilik) dan Warehouse (penanganan fisik). Keduanya memakai hak akses yang sama seperti sebelumnya.

## Isi catatan

1. Sebelum 2.01 hanya ada satu modul Inventory dengan Warehouse sebagai sub-grup, sehingga laporan nilai, label, dan scan tercampur. Kini keduanya dua modul.
2. Modul Inventory: Stock In, Out, Return, dan Adjustment per kelas, Stock Transfer, Subcontract, Customer Material, Batch dan Quality, Reports, Related Master Data. Valuation dan Period belum dibangun.
3. Modul Warehouse: Map dan Monitor, Receiving dan Putaway, Pick List, Transfer (In Transit), Count, Labels dan Scan, Tools, Setup. Task Board belum dibangun.
4. Laporan nilai (Stock Card semua item, Recapitulation, Summary, Opname, Loss dan Gain, In Inspection) ada di Inventory Reports. Isinya tidak berubah. Stock by Location tetap di Warehouse.
5. Label dan QR serta Scan Center ada di Warehouse karena merupakan pekerjaan lapangan.
6. Layar dan kodenya tidak berubah; hanya tempatnya di menu. Warehouse memakai hak akses Inventory, jadi tidak ada yang mendapat atau kehilangan layar.

## Istilah dan rincian

- **Stay as they are**: Stock In, Out, Return, Adjustment per kelas, Warehouse Map, Stack Monitor, Stock by Location, Buildings, Storage Areas, Bins.
- **Moved to Inventory**: Stock Transfer beserta approval-nya, laporan nilai, Subcontract, dan Customer Material.
- **Moved to Warehouse**: Cetak label, riwayat label, Scan Center, Tools, dan master Warehouse dan Location (kini di Setup).
- **New screens**: Setiap layar baru membawa fasenya (F1 sampai F6) dan previewnya sendiri.
