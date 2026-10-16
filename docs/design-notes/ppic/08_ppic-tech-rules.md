# Technical Plan: PPIC Rules and Formulas

Rumus dan aturan yang diimplementasikan engine, dengan nilai awal yang tersimpan di Settings > Constants.

## Isi catatan

1. Sisa pekerjaan = jumlah semua operasi tersisa dari setup, waktu jalan, antrean dan tunggu. Slack = (due date dikurangi sekarang) dikurangi sisa pekerjaan. Critical Ratio = (due date dikurangi sekarang) dibagi sisa pekerjaan.
2. Lapisan: 0 Hot; 1 Critical Ratio di bawah 1; 2 normal. Di dalam lapis 2, job yang selisih slack-nya kurang dari jendela (default satu shift) diurutkan dengan skor sadar setup.
3. Skor = Wdue x urgensi + Wsetup x (1 dikurangi setup / setup terpanjang) + Wcust x tier + Wage x umur, tiap nilai 0 sampai 1; default 0,50, 0,35, 0,15 dan bobot ageing kecil. Disimpan sebagai konstanta.
4. Setup = dasar + changeover + tooling. Total setup job dihitung sebagai waktu tidak produktif pada beban dan tanggal. Job tidak pernah ditunda penghematan setup lebih dari satu masa kunci.
5. Kaskade waste: input = output / (1 - persen variabel) + tetap, dari proses terakhir mundur ke yang pertama. Bahan yang dikeluarkan = input pertama. Dibulatkan ke atas ke satuan utuh.
6. Beban = jam rencana termasuk setup / jam tersedia. Bottleneck bila beban minimal ambang (default 90 persen) dan tertinggi di horizonnya; flag is_bottleneck pada proses adalah penimpaan manual.
7. Pembelajaran: aktual tipikal = median N job terakhir (default 5, ekstrem diabaikan). Nilai baru = lama + alpha x (tipikal dikurangi lama), alpha default 0,3, langkah dibatasi 20 persen. Nilai beku dilewati.
8. Auto-plan berjalan harian, tiap awal shift dan saat kejadian dan membuat Draft; Confirm officer membuatnya nyata. Kunci = N shift berjalan berikutnya (N diatur officer, default 1). Engine tidak memindahkan task terkunci; membuka kunci butuh alasan.
