# Technical Plan: Dashboard and KPI

Di mana indikator Maintenance tampil, bentuknya, cara lampu target bekerja, dan posisi OEE. Mockup ini usulan yang menunggu persetujuan.

## Isi catatan

1. Maintenance Dashboard adalah layar pertama modul. Baris pertama berisi enam indikator dengan target. Baris kedua berisi angka tindakan hari ini tanpa target.
2. Setiap kartu indikator punya tepi berwarna: hijau bila target tercapai, kuning bila dekat, merah bila meleset. Target diketik user di Settings, Constants.
3. Klik kartu membuka laporan Maintenance KPI untuk periode dan mesin yang sama. Satu laporan kaya kolom dan grafik, bukan beberapa laporan kecil.
4. Grafik: tren OEE dan availability, rugi OEE dari 100 persen waktu terencana, tren MTBF dan MTTR, downtime menurut jenis, kerusakan per komponen, mesin dengan downtime terbanyak.
5. OEE milik Production. Maintenance menyumbang downtime yang menggerakkan Availability dan hanya menampilkan OEE, hanya dibaca, dari engine Production.
6. OEE hanya berlaku untuk mesin produksi. Kendaraan dan aset lain menampilkan keandalan, downtime dan biaya, tanpa OEE.

## Istilah dan rincian

- **Indicators with targets**: Availability, MTBF, MTTR, PM Compliance, Planned work, Backlog.
- **Action numbers**: Mesin sedang down, request terbuka, PM terlambat, order berjalan, order menunggu part, part kritis di bawah minimum.
- **MNT_TARGET_AVAILABILITY**: Usulan 97 persen. Kuning bila sedikit di bawah target.
- **MNT_TARGET_MTBF_HOURS**: Usulan 120 jam. Makin tinggi makin baik.
- **MNT_TARGET_MTTR_HOURS**: Usulan 4 jam. Makin rendah makin baik.
- **MNT_TARGET_PM_COMPLIANCE**: Usulan 90 persen, target umum di industri.
- **MNT_TARGET_PLANNED_WORK**: Usulan 85 persen, target umum di industri.
- **Backlog range**: Usulan 3 sampai 5 minggu sehat. Di bawah 3 teknisi menganggur, di atas 5 pekerjaan menumpuk.
- **OEE target**: Sudah diketik per mesin di master mesin (target OEE persen). Dashboard membandingkan dengan itu.
