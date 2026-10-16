# Technical Plan: Assets, Not Only Machines

Maintenance merawat aset: mesin produksi, alat penunjang, kendaraan dan fasilitas seperti AC. Ini menjelaskan perbedaan antar kelas.

## Isi catatan

1. Cakupannya Aset, diputuskan user pada 2026-10-08. Setiap dokumen Maintenance menunjuk ke aset di mnt_asset, tidak langsung ke mesin.
2. Mesin datang dari Master Machine lewat tombol Add machines from Master Machine. Hanya kode, nama dan kelas yang mengikuti master. Kritikalitas, meter dan catatan milik Maintenance.
3. Kendaraan dan fasilitas diketik di Asset Maintenance Profile. Sebuah aset bisa ditautkan ke catatan aset tetap Finance untuk sisi biayanya.
4. Layar yang sama, order preventive dan breakdown (PMO dan BMO), dan laporan berlaku untuk semua kelas. Yang berbeda adalah meter, dokumen dan indikator.

## Istilah dan rincian

- **Production machine**: Mesin cetak dan finishing. Meter jam jalan atau impresi. OEE berlaku.
- **Support equipment**: Kompresor, forklift, pemanas. Meter jam jalan. Tanggal inspeksi wajib berlaku untuk kompresor dan forklift. Tanpa OEE.
- **Vehicle**: Truk dan mobil. Meter odometer dalam km. Dokumen berjatuh tempo (STNK, KIR, pajak, asuransi) menjadi order inspeksi tanggal tetap. Tanpa OEE.
- **Facility**: AC, genset, pompa, panel listrik, bangunan. Sering tanpa meter, jadwal berbasis waktu seperti bersih filter. Tanpa OEE.
- **Indicators**: Availability, MTBF, MTTR, PM compliance dan biaya berlaku untuk semua kelas. OEE hanya untuk mesin produksi.
