# PPIC: What Is Built and How to Use It

Autopilot seperti yang berjalan sekarang di situs dummy: di mana mengklik, apa yang dikerjakan engine sendiri, dan apa yang masih akan datang.

## Isi catatan

1. Production > Work Order > Create Work Order mendaftar baris rencana kirim dari Customer Order Confirmed yang belum punya Work Order. Create Work Order (satu) atau Create all membuatnya dari Product Development.
2. Setelah dibuat, engine menambah mesin, target, setup, dan gerbang bahan dan tooling, lalu meninggalkan rencana Draft. Tidak ada yang menjadi jadwal nyata sebelum officer mengonfirmasi.
3. Production > Production Scheduling: tinjau Needs attention, beri prioritas (Hot job atau Move up dengan alasan) bila perlu, lalu Confirm plan. N shift berikutnya lalu dikunci.
4. Engine juga membuat Draft baru tiap malam, tiap awal shift, saat Work Order dibuat, dan saat mesin rusak atau pulih.
5. Layar Work Order: dieline asli dari Product Development, BOP dan routing dengan mesin yang bisa mengantre tiap langkah, BOM dengan gerbang dan tautan substitusi, jadwal dan output.
6. Production > Output > Operator Queue (tablet di mesin): pilih mesin, Play untuk setup, Play lagi untuk jalan, Pause dengan alasan, Add quantity, Stop dengan jumlah baik, waste dan reject.
7. Tombol Problem, dan alasan jeda Machine problem, membuka Maintenance Request yang sudah terisi mesin dan Work Order.
8. Production > Planning > Learning Changes: engine mengoreksi menit changeover Assumed dari waktu setup nyata dalam langkah kecil yang tercatat. Kembalikan dan bekukan perubahan mana pun.
9. Master: Tooling Master, Setup and Changeover, Wait Rules (pengeringan), dan Tier Customer di Master Customer (Sales). Pengaturan penjadwalan adalah baris PPIC_* di Settings > Constants.
10. Flag akses di Security: PPIC_SCHEDULING (jalankan, konfirmasi, prioritas, pindah) dan PPIC_INVENTORY (booking, kekurangan, tanggal siap). Administrator memegang keduanya.
11. Belum ada: waste tetap (make-ready) di rantai waste ProDev butuh persetujuan user untuk mengubah ProDev, dan format cetak Work Order belum disetujui.
12. Semua nilai awal tetap Assumed sampai job nyata tercatat. Rilis ke live (tm2*) menunggu user.
