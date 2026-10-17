# ProDev: ENGINE saat officer memilih Inquiry Product (serah terima singkat, 2026-10-18)

> Pelengkap `docs/handoff-prodev-session.md` dan `docs/engine-process-template-per-tipe.md`. Tulis ulang bila keputusan berubah. ProDev zona beku: preview + izin Jo sebelum kode.

## Goal (kata Jo, 2026-10-18)
Membangun ENGINE di Product Development: saat user pertama kali memilih Inquiry Product lalu memprosesnya, engine berjalan menyiapkan segala kesiapan (imposisi, BOP, BOM, tinta) supaya officer ProDev tinggal memeriksa dan memutuskan.

## Alur sekarang (FAKTA, dibuktikan 2026-10-18 di mockup.test dengan BOX SLIDER IV RINAL, belum pernah dibuka di ProDev)
1. Pilih baris di grid Pro-Dev New, rute `GET /v2/prodev/open/{inquiry}` -> `InquiryStage::openInProdev` -> `InquiryWriter::sendToProDev` membuat `prod_product` draf dan `prod_part` (ukuran, pack, warna, finishing, artwork disalin). Tidak ada hitungan engine di langkah server ini.
2. Layar workbench terbuka; `workbench.js` memanggil `generateSpec` -> `SpecEngine::generate` (tidak menulis DB). Hasil muncul sendiri: BOM 11 baris, BOP 9 langkah, tinta 4 warna. Langkah Lem dan bahan LEM BOX ikut muncul sejak perbaikan Joint Type (commit 76e8c993).
3. **Celah yang terlihat officer:** imposisi kosong pada pilihan pertama. Accordion Imposition: "Object di canvas 0 up", "Hasil / plano 0 pcs", Auto Up nonaktif. Akibatnya `upCount` = 0 dikirim ke engine: "Lembar cetak dibutuhkan: -", "Qty masuk" kosong di langkah BOP awal, kebutuhan bahan "belum bisa dihitung", kebutuhan tinta 0,0003 KG. Officer harus menekan Suggestion atau Auto Nesting sendiri.
4. ~~`autoNestSoon()` tidak menghasilkan susunan pada Rinal.~~ **SELESAI 2026-10-18 (izin Jo):** pemicu benar; penyebab = `MASK_MAX` 700 mengecilkan bitmap blank 99,7 cm, sedangkan susunan membaca piksel sebagai 0,1 mm (rotate 90° dikira muat 2 up, objek asli keluar canvas, sah 0 up). Perbaikan: `MASK_MAX` 1100 + `autoNest` menolak mask yang diperkecil (`mmPerPx > 1.01`). Sesudah: Rinal 1 up, pemakaian canvas 95,6%, BOP "Qty masuk" terisi (5.553 LBR). Tes `ProdevWorkbenchMaskTest`. Salinan v1 (`prodev.blade.php`) tidak disentuh.
5. **Sisa (baru terlihat):** plano bawaan 65x100 tidak muat canvas Rinal; "Canvas / plano" dan "Hasil / plano" masih 0 sampai officer menekan Suggestion (saran 72x102, yield 97%). Usul berikut: pilih plano otomatis bersama imposisi (butuh preview + izin Jo).

## Usul langkah berikut (butuh preview dan izin Jo)
- **Imposisi draf otomatis saat belum ada layout tersimpan**, dengan alasan tertulis bila gagal (keputusan Jo nomor 3 di `handoff-prodev-session.md` §6, rekomendasi agen). Dua jalur: (a) perbaiki pemicu `autoNest` di klien; (b) hitung susunan di server saat `openInProdev` memakai `ImpositionEngine` dan kirim sebagai kondisi awal layar. Draf tidak menulis DB sebelum Save atau Confirm; hasil officer (OVERRIDE/MANUAL) tidak ditimpa.
- Satu baris status engine di panel atas (apa yang sudah disiapkan, apa yang gagal dan alasannya).
- Sisa keputusan menunggu Jo: lihat `handoff-prodev-session.md` §6 butir 1, 2, 4, 5, 6.

## Sudah beres di sesi ini
- `NEEDS_GLUE` kini dari Joint Type (`join_type_id` GLUE_*), rakitan ditahan: `SpecEngine::needsGlue`, tes `SpecEngineJointGlueTest`.
- Aturan data dummy B.17 (per level 2 set, maks 3, real dan lengkap, bisa di-reset) dan mesin dummy: level draft, `dummy:mastercard --set=produk:level`, booking ikut dibersihkan.
- Lima set real tingkat 1 sampai 3 di ddt\*: draft Naskot IQ2690070 dan Power X IQ2690073; confirm Sales Rinal IQ2690074; ProDev confirm Side Rack IQ2690071 dan Gemmolin IQ2690072.

## Belum dikerjakan (jangan dianggap selesai)
- Merapikan sisa dummy sesuai tabel koreksi (hapus inquiry lemah, kelebihan Quotation, CO, WO, DO). Cadangan penuh: `php artisan ddt:stage restore before-b17 --env=mockup`.
- Set kedua di tingkat 2: Omegamoto Spotlight terhalang kategori master bahan "SHEET E/FLUTE K125/M125/M125 81 x 63 cm" (rantai lamanya sudah terhapus); usul pakai JPC Gold.
- Nama tinta di atas 40 karakter (`ink_name`): dummy memakai nama pemasok pertama; melebarkan kolom belum diputuskan.
