# Technical Plan: Rules and Formulas

Aturan dan rumus di balik valuasi, kebutuhan bahan, kurang kirim, dan aturan area. Setiap batas adalah pengaturan yang bisa diubah user.

## Isi catatan

1. Average adalah rata-rata berjalan per item untuk seluruh perusahaan. Issue tidak pernah mengubah average. Qty nol memaksa nilai nol.
2. Saldo awal pindah ke rantai per item tanpa jurnal: Average awal sama dengan total nilai dibagi total qty semua bin.
3. Landed cost terlambat masuk ke qty yang masih ada; bagian yang sudah keluar masuk akun selisih landed cost, ditutup ke HPP di akhir periode.
4. Dokumen bertanggal mundur disisipkan berurutan dan baris sesudahnya dihitung ulang. Dibuat satu jurnal koreksi bersusun; jurnal lama tidak berubah.
5. Lembar dibutuhkan berasal dari layout: qty dibagi ups, dibulatkan ke atas, dikali (1 + persen waste), ditambah lembar make ready tiap proses.
6. LIFO tidak dipakai untuk pembukuan (tidak diperbolehkan PSAK 14). FIFO ditunda. Stok minus ditolak.

## Istilah dan rincian

- **Average after receipt**: (qty lama x average lama + qty masuk x harga masuk) dibagi (qty lama + qty masuk).
- **Price rise example**: 1.000 kg @15.000 lalu 500 kg @16.500 menjadi 15.500; lalu 600 kg @18.000 setelah issue 800 kg menjadi 16.653,85.
- **Weight of a sheet**: Panjang m x lebar m x gsm menghasilkan gram, misalnya 0,79 x 1,09 x 300 = 258,3 g.
- **Area rules**: Mixing (satu item per bin, campur item, satu batch per bin), pick order (FIFO atau FEFO), hitung tiap N hari.
- **Starting settings**: Eskalasi QC 4 jam dan tenggat 8 jam kerja, peringatan booking basi 7 hari dan lepas 30 hari, dead stock 365 hari.
- **Shortage tolerance**: Mengikuti toleransi penerimaan PO yang sudah ada pada baris penerimaan.
