# Technical Plan: Phases

Urutan kerja dalam enam fase. Tiap langkah adalah satu commit, dengan topik Help, angka uji, dan preview yang sudah ditinjau.

## Isi catatan

1. Fase 1 Fondasi: master batch, pemilik, status, satu Average per item, jenis area, dan aturan area.
2. Fase 2 Mutu dan penerimaan: sampling, URGENT, dock to line, Receiving, Putaway, dan permintaan kirim kekurangan.
3. Fase 3 Booking: pre-booking dari costing, substitusi, pick list, dan satu sumber Available untuk barang jadi.
4. Fase 4 Subkon, titipan customer, dan opname: kirim bahan, stok di vendor, stok milik customer, In Transit, kunci bin.
5. Fase 5 Nilai: landed cost, GR/IR, rekonsiliasi harian, jurnal koreksi, dan penyisihan persediaan usang.
6. Fase 6 Penyempurnaan: hot job, booking basi, die dan plat, papan tugas, dan laporan lanjutan.
7. Sudah dibangun: fase 1 sampai 4 (batch, QC dan URGENT, Receiving dan Putaway, Pick List, pre-booking, subkon, material titipan, In Transit, opname, pembalikan) serta booking basi dan register alat (dihapus 2026-10-18) dari fase 6.

## Istilah dan rincian

- **Step S0**: Tentukan cara uji berjalan terhadap database dummy dan catat baseline uji inventory yang ada.
- **Steps S1 to S5**: Skema, backfill batch, mesin Average per item, perintah peralihan, pengaturan, dan jenis area.
- **Steps S6 to S8**: Layar setelah persetujuan, Help dan menu, lalu data dummy baru dan uji penuh.
- **Release**: Migrasi dan perintah sampai ke database live hanya saat rilis, setelah run pemeriksaan ditinjau.
