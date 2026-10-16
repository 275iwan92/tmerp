# Technical Plan: Phase 1: Master Data

Apa yang dikirim Fase 1, bagaimana dibangun langkah demi langkah, dan bagaimana diuji.

## Isi catatan

1. Buat koneksi dan database lebih dulu (selesai), lalu tabel Fase 1 di ddtmnt: mnt_code, mnt_asset, mnt_watch_point, mnt_asset_part, mnt_document, mnt_document_line, mnt_asset_document dan mnt_log.
2. Migration juga membuat modul MAINT, grup MAINT_TECH dan MAINT_HEAD, baris menu dan pengaturan, dan melewati yang sudah ada.
3. Bangun Maintenance Codes lebih dulu, lalu Asset Maintenance Profile satu tab per tab, lalu grid Asset Readiness, lalu menu dan topik Help.
4. Data contoh hanya masuk ke ddtmnt lewat seeder, tanpa batas normal. Data mesin nyata tidak pernah dikarang di tm2mnt.
5. Setiap langkah di-commit terpisah sehingga tiap langkah bisa di-rollback.
6. Test memakai MySQL lokal sungguhan dan membersihkan barisnya sendiri, lalu database dicek langsung, bukan hanya percaya skrip test.

## Istilah dan rincian

- **Test: completeness**: Kelengkapan profil dihitung benar untuk beberapa kombinasi item terisi dan kosong.
- **Test: copy**: Copy from machine tidak menimpa baris yang sudah ada pada mesin tujuan.
- **Test: access**: Teknisi tidak bisa menyimpan. Kepala maintenance dan administrator bisa.
- **Test: spare part**: Hanya material kategori Spare Part Machine yang bisa dipilih sebagai part mesin.
- **Test: menu**: Menu hanya menampilkan layar yang ada.
- **Deviation: PM Plans tab**: Tab PM Plans pada mockup disembunyikan sampai Fase 2, saat plan sudah ada.
- **Deliverables**: Layar, migration, seeder, topik Help, entri build catalog, entri kamus, test.
