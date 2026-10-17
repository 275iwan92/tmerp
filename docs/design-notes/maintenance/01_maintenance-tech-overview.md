# Technical Plan: Maintenance Module

Rencana teknis modul Maintenance: ruang lingkup, database, fase dan prinsip. Ini rencana untuk di-review, bukan penjelasan layar yang sudah ada saat ini.

## Isi catatan

1. Maintenance punya database sendiri tm2mnt (salinan dummy ddtmnt) dan kode modul MAINT sendiri. Terpisah dari Production.
2. Menu terdiri dari empat kelompok: Preventive Maintenance, Breakdown Maintenance, Improvement dan Master Data, ditambah kelompok Report.
3. Fase 1 Master Data: profil aset dengan Watch Points, Spare Parts dan Documents, katalog kode, dan grid kesiapan.
4. Fase 2 Preventive: plan, kalender PM, pembacaan meter, Daily Check dan slot mesin terblokir di jadwal produksi (penjadwalan mundur saat WO dibuat; Scheduling Board v1 dihapus 2026-10-18).
5. Fase 3 Breakdown: request, order perawatan, pengeluaran spare part dan permintaan pembelian, serta laporan keandalan.
6. Fase 4 Improvement: usulan, saran kerusakan berulang, review sebelum dan sesudah, dan perubahan master yang disetujui orang.
7. Master data hanya diubah oleh user. Sistem mengubah transaksi dan hanya mengusulkan perubahan pada master data.
8. Setiap nilai yang dibaca modul (ambang, tarif) punya layar input di Settings, Constants. Tidak ada yang dikunci di kode.

## Istilah dan rincian

- **Database**: tm2mnt untuk live, ddtmnt untuk mockup. Nama koneksi sama dengan nama database.
- **Module code**: MAINT. Grup MAINT_TECH (teknisi) dan MAINT_HEAD (kepala maintenance).
- **Tables**: Prefix mnt_. Primary key id_<entitas> BIGINT, kolom audit, tanpa foreign key lintas database.
- **Language**: Database dan rumus dalam bahasa Inggris. Bahasa Indonesia hanya di layar dan cetak lewat kamus.
