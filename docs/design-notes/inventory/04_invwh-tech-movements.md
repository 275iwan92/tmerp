# Technical Plan: Catalogue of Stock Movements

Semua cara stok bergerak, dengan efeknya pada qty, nilai, dan jurnal. Berdasarkan tipe pergerakan SAP, purpose stock entry ERPNext, dan transaksi NetSuite.

## Isi catatan

1. Penerimaan dari PO menambah stok pada harga beli ditambah landed cost. Jurnal: debit Persediaan, kredit GR/IR.
2. Issue ke produksi dan pengiriman ke customer mengambil stok pada Average item. Jurnal: debit WIP atau HPP, kredit Persediaan.
3. Transfer, kirim ke subkon, dan ubah status batch tidak mengubah nilai dan tidak membuat jurnal.
4. Material titipan customer adalah stok milik customer: tanpa nilai dan tanpa jurnal. Hasil subkon menambah nilai bahan yang dikirim ditambah ongkos jasa.
5. Adjustment, scrap, dan koreksi kurang kirim memakai akun sendiri sehingga bisa dilaporkan terpisah.
6. Reservasi dan alokasi bukan pergerakan. Keduanya dicatat terpisah dari buku stok.

## Istilah dan rincian

- **SAP 101 and 261**: Penerimaan barang untuk PO dan pengeluaran barang untuk order.
- **SAP 301 and 311**: Transfer antar plant dan antar storage location dalam satu langkah.
- **SAP 541 and 543**: Material ke vendor (subkon) dan konsumsi komponen saat hasil diterima.
- **SAP 551 and 701**: Scrap dan selisih persediaan.
- **ERPNext purposes**: Material Receipt, Issue, Transfer, Transfer for Manufacture, Manufacture, Repack, Send to Subcontractor.
- **NetSuite**: Item Receipt, Inventory Transfer tanpa untung rugi nilai, Inventory Adjustment untuk qty dan nilai, Inventory Status Change.
