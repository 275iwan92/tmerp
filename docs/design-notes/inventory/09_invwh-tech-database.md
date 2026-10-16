# Technical Plan: Tables and Data

Tabel dan kolom yang baru atau berubah. Aturan: kunci bigint auto number, nama English, tanpa foreign key lintas database.

## Isi catatan

1. Master batch baru berisi nomor batch, lot supplier, tanggal, status mutu, sertifikasi, food grade, arah serat, dan sertifikat analisis.
2. Kartu stok, baris dokumen, dan pallet mendapat kolom batch serta tipe dan id pemilik. Teks lot lama tetap sampai semua pembaca berpindah.
3. Rantai nilai per item menyimpan qty total, nilai, dan Average berurutan tanggal lintas semua bin.
4. Area mendapat kolom virtual dan aturan, ditambah empat jenis baru: WIP, Subkon, In Transit, Loss. Pallet ditautkan ke batch.
5. Fase berikutnya menambah tabel rencana sampling dan keputusan, kolom urgent pada baris penerimaan, permintaan kekurangan, kirim subkon, tugas, pick list, dan register alat. Semuanya sudah ada.
6. Booking mendapat kunci rantai, tahap B0 sampai B4, dan tanggal hold. Data lama dimigrasi oleh perintah yang bisa diperiksa dulu.

## Istilah dan rincian

- **Batch backfill**: Satu batch per item dan lot dari kartu stok dan pallet; lot kosong menjadi NO-LOT. Aman dijalankan dua kali.
- **Per-item cutover**: Run pemeriksaan menampilkan total dulu; run sebenarnya menulis satu baris awal per item dan tidak membuat jurnal.
- **Where it runs**: Database dummy dulu. Database live diubah hanya saat rilis, setelah run pemeriksaan ditinjau.
- **Tests**: Item yang sama di dua bin berbiaya sama; transfer menjaga Average; Stock Card dan Stock Summary tetap identik.
