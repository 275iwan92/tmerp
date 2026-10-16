# Technical Plan: Decisions, Risks and Open Items

Apa yang sudah diputuskan, apa yang diusulkan, dan apa yang masih butuh user sebelum fase terkait dimulai.

## Isi catatan

1. Diputuskan: Work Order dari ProDev; MRP, perkiraan dan draf jadwal oleh engine, officer mengonfirmasi; booking otomatis terkonfirmasi bila stok cukup; kunci = N shift berjalan atur officer; nilai asumsi dipelajari; tier di master Customer.
2. Diusulkan, berlaku kecuali user menyatakan lain: bottleneck dideteksi otomatis dengan penimpaan officer; proses subkon ditandai officer pada proses (flag inhouse), yang menambah hari angkutan.
3. Disetujui user: penambahan kolom pada prd_wo_operation dan tabel baru di database Master dan Production untuk Fase 1 sampai 3. Migration dijalankan dulu di database dummy dan di live hanya saat rilis.
4. Diverifikasi di kode: apakah baris purchase order menyimpan tanggal perkiraan terima (daftar incoming Material Planning adalah sumber rencana), dan apakah tiap produk ProDev punya BOP, routing dan BOM yang cukup lengkap untuk dibuat WO.
5. Risiko: produk dengan routing atau BOM tidak lengkap menghasilkan Work Order buruk. Part seperti itu didaftar untuk diperbaiki PPIC dan ProDev; engine tidak pernah menebak data yang hilang.
6. Risiko: Draft berubah terlalu sering dan officer kehilangan kepercayaan. Diredam oleh kunci, auto-plan hanya di waktu tetap dan saat kejadian, dan ukuran stabilitas rencana di tab Monitoring.
7. Risiko: belajar dari sedikit atau janggal job. Diredam oleh jumlah sampel minimum, batas langkah, flag bekukan, dan pengembalian yang tercatat.
