# Technical Plan: Stock Model (Ledger, Balance, Status, Owner)

Bagaimana stok dimodelkan, mengikuti SAP, Odoo, ERPNext, Dynamics 365, dan NetSuite: buku pergerakan, saldo sebagai hasil, status, dan pemilik.

## Isi catatan

1. Setiap pergerakan adalah baris buku dengan asal dan tujuan, termasuk tempat virtual seperti vendor, customer, loss, produksi, dan transit.
2. Saldo adalah hasil buku. Kesalahan dikoreksi dengan dokumen lawan, tidak pernah dengan mengubah baris.
3. Qty tetap per bin lewat kartu stok berantai yang ada. Nilai dan Average pindah ke satu rantai per item.
4. Status melekat pada batch: Quarantine, Conditional, Released, Rejected, Blocked. Tiap status menentukan apakah stok dihitung Available dan boleh dialokasikan.
5. Pemilik adalah Company, Customer, atau Supplier. Stok milik pihak lain tampil di gudang tetapi tidak pernah dinilai atau dijurnal.
6. Tanggal: tanggal input dan tanggal posting terpisah, dan periode yang sudah ditutup menolak posting baru.

## Istilah dan rincian

- **Odoo**: Stock move punya baris per lot atau lokasi; reservasi memilih quant menurut strategi ambil.
- **ERPNext**: Stock ledger entry memuat qty, incoming rate, balance value, dan valuation rate tiap pergerakan.
- **SAP**: Tipe pergerakan menentukan update qty dan akun; quant adalah stok satu material di satu bin.
- **NetSuite**: Inventory status punya bendera ketersediaan, sehingga qty on hand bisa ada sementara qty available nol.
- **Dynamics 365**: Update fisik dan finansial terpisah, dengan status Received dan Purchased, Deducted dan Sold.
- **Our choice**: Pertahankan kartu stok yang ada, tambah batch, pemilik, status, dan rantai nilai per item.
