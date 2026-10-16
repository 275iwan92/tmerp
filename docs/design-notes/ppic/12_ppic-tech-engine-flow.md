# Technical Plan: Engine Flow for Priority Scheduling

Urutan langkah engine menyiapkan jadwal Draft dengan prioritas, dan di mana officer bertindak. Gambar flowchart ada di preview.

## Isi catatan

1. Pemicu: harian, awal shift, Work Order baru, bahan datang atau terlambat, kerusakan, prioritas officer. Tiap pemicu menjalankan AutoPlanRunner, yang membuka run Draft dan satu baris log.
2. Kumpulkan dan gerbang: ambil operasi terbuka di luar kunci terkonfirmasi, lalu cek gerbang (bahan oleh MRP, tooling, artwork, barang titipan, kapasitas). Yang belum siap masuk daftar pengecualian dengan tanggal atau alasan.
3. Perkirakan: kaskade waste memberi jumlah awal, SpeedTargetEngine target per jam, SetupTimeEngine setup, aturan tunggu menambah pengeringan. Waktu jalan = jumlah dibagi target.
4. Peringkat: lapis 0 Hot job; lapis 1 Critical Ratio di bawah 1 (terkecil dulu); lapis 2 menurut due date, diurut ulang dalam jendela satu shift dengan skor sadar setup. Penghematan setup tidak mengubah lapis 0 dan 1.
5. Pilih mesin: untuk tiap operasi bandingkan mesin proses itu (tersertifikasi, urutan preferensi), buang yang tidak tersedia, pilih selesai paling awal termasuk setup, lalu beban lebih rendah, lalu urutan.
6. Cek kecocokan: bila job melewati due date atau mesin kelebihan beban, ia menjadi pengecualian dengan opsi (mesin lain, lembur, pecah lot, subkon, geser tanggal). Bila tidak, haluskan urutan menurut pisau, set tinta dan bahan.
7. Tulis Draft dan biarkan officer meninjau. Bila perlu prioritas (Hot job atau naikkan dengan alasan), dampaknya ditampilkan dan engine mengulang dari langkah peringkat. Lalu officer mengonfirmasi.
8. Confirm menulis jadwal nyata dan mengunci N shift berjalan berikutnya. Lantai produksi mencatat output dan setup; monitoring dan pembelajaran mengoreksi nilai asumsi yang masuk ke langkah perkiraan.
