# Technical Plan: Features and Their Functions

Fungsi tiap fitur Maintenance yang direncanakan, siapa pemakainya, dan fase mana yang mengirimnya.

## Isi catatan

1. Fitur dikelompokkan seperti menu. Fitur Master Data menjelaskan mesin, fitur Preventive menjaga mesin tetap sehat, fitur Breakdown memperbaiki kerusakan, fitur Improvement mencegah pengulangan.
2. Setiap fitur punya satu tugas dan menyerahkan hasilnya ke fitur berikutnya. Topik Data Flow dan Work Flow menunjukkan hubungannya.

## Istilah dan rincian

- **Asset Maintenance Profile**: Fase 1. Satu tempat untuk menjelaskan kebutuhan mesin: cek, part dan dokumen. Dipakai semua fitur berikutnya.
- **Watch Points**: Fase 1. Daftar hal yang dicek pada mesin, dengan cara, batas normal, frekuensi dan siapa yang mengecek.
- **Spare Parts per asset**: Fase 1. Menunjukkan part yang dibutuhkan mesin dan kapan stok terlalu rendah, sehingga part yang kurang diketahui sebelum kerusakan.
- **Maintenance Codes**: Fase 1. Satu katalog kode komponen, masalah, penyebab dan tindakan, sehingga laporan bisa dijumlahkan.
- **Asset Readiness**: Fase 1. Grid yang menunjukkan mesin mana yang profilnya belum lengkap, sehingga pengisian data bisa dikejar.
- **Preventive Plan**: Fase 2. Menentukan kapan mesin butuh pekerjaan terjadwal: menurut kalender atau meter, dengan checklist dan part standar.
- **PM Calendar**: Fase 2. Menampilkan hari terjadwal, terlambat dan terblokir sehingga kepala maintenance melihat satu bulan sekaligus.
- **Meter Readings**: Fase 2. Mencatat jam jalan atau impresi mesin sehingga plan berbasis meter jatuh tempo pada waktu yang tepat.
- **Daily Check**: Fase 2. Membuat operator mengecek mesin tiap shift dan melaporkan hal tidak normal sebelum rusak.
- **Maintenance Request**: Fase 3. Laporan singkat masalah yang bisa dibuat siapa pun. Menjaga laporan ditolak atau dobel keluar dari data pekerjaan.
- **Maintenance Order (PMO, BMO, IMO)**: Fase 3. Pekerjaan resmi dengan jadwal, teknisi, checklist, part, jam, downtime dan biaya. PMO untuk preventive, BMO untuk breakdown, IMO untuk improvement.
- **My Work**: Fase 3. Tampilan teknisi untuk pekerjaan yang ditugaskan hari ini, dibuat untuk HP.
- **Improvement Proposal**: Fase 4. Mengubah masalah berulang menjadi usulan beralasan dengan bukti, biaya dan penghematan.
- **Improvement Review**: Fase 4. Membandingkan downtime dan biaya sebelum dan sesudah, sehingga user tahu apakah improvement berhasil.
- **Reports**: Maintenance Order Report, Machine Reliability (MTBF, MTTR, Availability) dan PM Compliance. Masing-masing dikirim bersama fase yang datanya dibaca.
