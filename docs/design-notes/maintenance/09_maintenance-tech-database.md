# Technical Plan: Maintenance Tables

Daftar tabel yang direncanakan untuk Maintenance di tm2mnt, per fase, dan hubungannya dengan database lain.

## Isi catatan

1. Semua tabel ada di tm2mnt. Relasi ke mesin, material, pegawai dan data produksi mengarah ke database lain dan dicek oleh Laravel, bukan foreign key.
2. Downtime dan status mesin tetap di database Master (prod_machine_downtime, prod_machine). Maintenance order menulis ke sana lewat satu service, jadi tidak ada tabel downtime kedua.
3. Request atau order perawatan tentang kerusakan produksi menyimpan breakdown_event_id, tautan logis ke prd_breakdown_event di Production.
4. Tabel Fase 1 dicantumkan lebih dulu. Tabel fase berikutnya dicantumkan bersama fase yang membuatnya.

## Istilah dan rincian

- **mnt_code**: Fase 1. Katalog kode: jenis (COMPONENT, PROBLEM, CAUSE, ACTION), kode, nama, urutan, activate.
- **mnt_asset**: Fase 1. Satu baris per aset yang dirawat: kode, nama, kelas (mesin produksi, alat penunjang, kendaraan, fasilitas), tautan logis ke master mesin atau aset tetap Finance, kritikalitas, jenis meter, lokasi.
- **mnt_watch_point**: Fase 1. Hal yang dicek pada aset: kode komponen, teks cek, cara, batas normal min dan max, satuan, frekuensi, peran pemeriksa, urutan.
- **mnt_asset_part**: Fase 1. Spare part milik aset: material, kode komponen, qty per pemakaian, saran stok minimum, umur pakai dalam hari, penanda kritis.
- **mnt_plan, mnt_plan_task, mnt_plan_part**: Fase 2. Plan preventive dengan pemicu (waktu atau meter), jadwal fixed atau floating, checklist tugas dan part standar.
- **mnt_document, mnt_document_line, mnt_asset_document**: Fase 1. Teks SOP dan WI dengan nomor, revisi dan status (DRAFT atau APPROVED), baris isinya, dan tautan yang menetapkan tiap dokumen ke aset. Manual, gambar dan kontak vendor memakai tabel yang sama dengan lokasi file.
- **mnt_meter_reading**: Fase 2. Pembacaan meter mesin, diketik user atau diakumulasi dari output produksi.
- **mnt_daily_check, mnt_daily_check_line**: Fase 2. Satu lembar cek per mesin per shift, dan satu baris hasil per watch point (OK, NOT_OK, NA, nilai ukur).
- **mnt_order, mnt_order_task, mnt_order_part, mnt_order_labor**: Fase 2. Maintenance Order dengan lima jenis: PREVENTIVE dan INSPECTION (prefiks PMO), BREAKDOWN dan CORRECTIVE (prefiks BMO), IMPROVEMENT (prefiks IMO). Tugas, part dan jam teknisi.
- **mnt_request**: Tabel Fase 2, layar Fase 3. Laporan masalah dengan prioritas dan status. Daily Check membuatnya untuk setiap titik Not OK.
- **mnt_log**: Fase 3. Riwayat perubahan status dan tindakan per dokumen.
- **mnt_improvement, mnt_improvement_change**: Fase 4. Usulan improvement dengan angka sebelum dan sesudah, dan usulan perubahan master yang hanya bisa disetujui orang.
