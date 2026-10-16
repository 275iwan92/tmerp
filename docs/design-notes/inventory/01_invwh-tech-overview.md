# Technical Plan: Inventory and Warehouse v2.01 Overview

Tujuan, dua modul, dan batasan perbaikan 2.01 untuk Inventory dan Warehouse. Semuanya di-preview dan disetujui dulu sebelum dibangun.

## Isi catatan

1. Inventory menjawab apa, berapa, nilainya berapa, dan milik siapa. Warehouse menjawab di mana barangnya dan bagaimana penanganan fisiknya.
2. Dokumen Transfer dan opname dimiliki Inventory. Pekerjaan lapangan (tugas, scan, hitung) dimiliki Warehouse.
3. Tidak diubah: Stock Card, Stock Summary, dan Warehouse Map. Ketiganya sudah benar; perbaikan apa pun ditunjukkan sebagai preview dulu.
4. Baru di 2.01: master batch, pemilik stok, status batch, satu Average per item, QC sampling dengan jalur URGENT, permintaan kirim kekurangan, alur subkon dan material titipan customer.
5. Booking dimulai sejak product costing sudah menghitung, supaya pembelian bisa bergerak sebelum customer order ada.
6. Dibangun di database dummy (ddt) sampai rilis. Tidak ada yang ditulis ke database live selama pekerjaan ini.

## Istilah dan rincian

- **Inventory**: Dokumen, qty, nilai, pemilik, batch, status mutu, dan laporan.
- **Warehouse**: Peta, area, bin, receiving, putaway, pick list, label, scan, dan tugas hitung.
- **Frozen zone**: Inquiry Product, ProDev, dan ProCost hanya dibaca, tidak pernah diubah.
- **Preview rule**: Setiap layar baru atau perubahan digambar sebagai gambar, diperiksa, dan disetujui user sebelum ada kode.
