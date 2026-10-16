# Technical Plan: Data Flow Between Modules

Modul mana mengirim data apa ke modul mana, dan jurnal apa yang dibuat, mengikuti pola persediaan perpetual ERPNext dan SAP.

## Isi catatan

1. Procurement ke Inventory: penerimaan pembelian memposting stok dan, bila URGENT, boleh diposting sebelum hasil mutu.
2. Inventory ke Accounting: setiap pergerakan bernilai membuat jurnalnya; buku stok direkonsiliasi ke buku besar tiap hari.
3. Invoice supplier ke Accounting: debit GR/IR, kredit Hutang Usaha. Ini tanggal finansial; penerimaan adalah tanggal fisik.
4. Produksi: issue mendebit WIP dan mengkredit Persediaan; hasil mendebit Barang Jadi dan mengkredit WIP.
5. Sales dan Planning: booking membaca qty dan ukuran dari costing dan layout saja; harga tetap rahasia di sisi costing.
6. Warehouse ke Inventory: bukti scan (qty, bin, batch, pallet) menjadi dasar posting. Warehouse tidak pernah menghitung nilai.

## Istilah dan rincian

- **ERPNext**: Purchase Receipt: debit Stock In Hand, kredit Stock Received But Not Billed. Delivery Note: debit COGS, kredit Stock In Hand.
- **SAP**: Pergerakan 101 mendebit stok dan mengkredit GR/IR; pergerakan 201 mengkredit stok dan mendebit akun biaya.
- **Booking to Warehouse**: Kebutuhan per bahan menjadi pick list; Available dan Reserved kembali dari Inventory sebagai satu sumber untuk bahan baku dan barang jadi.
- **Customer invoice**: Pengiriman memposting HPP; invoice penjualan adalah jurnal terpisah.
- **Reports**: Stock Card dan Stock Summary membaca buku yang sama dan harus tetap identik sebelum dan sesudah perubahan.
