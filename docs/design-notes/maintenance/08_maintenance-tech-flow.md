# Technical Plan: Maintenance Data Flow

Menunjukkan bagaimana laporan, plan atau kerusakan berulang menjadi order perawatan, dan apa yang ditulis tiap order perawatan ke modul lain.

## Isi catatan

1. Daily Check operator dengan hasil NOT_OK membuka Maintenance Request, bukan order perawatan, sehingga kepala maintenance yang menilai.
2. Siapa pun juga bisa melapor masalah sebagai Maintenance Request. Kepala menerimanya sehingga order perawatan dibuat, atau menolak dengan alasan.
3. Request tentang mesin berhenti pada mesin kritis langsung menjadi order perawatan tanpa menunggu persetujuan.
4. Tiap pagi perintah terjadwal mengubah plan preventive yang jatuh tempo menjadi draft order perawatan. Kepala mengatur jadwal dan mengonfirmasinya.
5. Memulai order perawatan yang butuh mesin berhenti membuka baris downtime dan mengatur status mesin. Done menutup downtime dan mengembalikan status ke ready.
6. Spare part sebuah order perawatan dikeluarkan dari Inventory. Part yang kurang membuat permintaan pembelian dan order perawatan menunggu part.
7. Kerusakan berulang pada komponen yang sama memunculkan saran Improvement. Usulan yang diterima menjadi order perawatan jenis IMPROVEMENT.
8. Beberapa hari setelah improvement selesai, review membandingkan downtime dan biaya sebelum dan sesudah. Orang yang memutuskan perubahan master mana yang diterapkan.
9. Master data memberi makan fitur lain: Daily Check membaca watch point, order perawatan membaca spare part dan kode, dan laporan membaca semuanya.

## Istilah dan rincian

- **Writes to Master**: Baris downtime mesin dan status mesin, hanya lewat service order perawatan.
- **Writes to Inventory**: Dokumen pengeluaran stok untuk spare part, merujuk ke order perawatan.
- **Writes to Procurement**: Permintaan pembelian untuk part yang kurang, ditandai berasal dari Maintenance.
- **Reads from Production**: Output produksi untuk meter, dan kejadian breakdown pada order perawatan.
- **Reads from Scheduling**: Maintenance order preventive yang Confirmed memblokir mesin di Scheduling Board.
- **Master data readers**: Daily Check membaca mnt_watch_point. Maintenance order membaca mnt_asset_part dan mnt_code. Laporan membaca semuanya. Stok dibaca dari Inventory.
