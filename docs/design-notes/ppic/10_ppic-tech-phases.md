# Technical Plan: Phases and Acceptance

Urutan pembangunan, apa yang dihasilkan tiap fase dan cara penerimaannya. Tidak ada yang mulai sebelum fase sebelumnya ditinjau user.

## Isi catatan

1. Fase 1, Work Order dari ProDev: periksa WoGenerationService dari ujung ke ujung terhadap BOP, routing dan BOM; simpan id proses; jumlah awal; salin baris BOM; daftarkan part tanpa routing. Diterima bila tiap Work Order dummy cocok dengan data ProDev.
2. Fase 2, kesiapan bahan: MRP per Work Order, GateService, usulan booking dan purchase request, tanggal datang. Diterima bila Work Order dengan bahan kurang tampil Waiting dengan tanggalnya tanpa input officer.
3. Fase 3, perkiraan: WasteAllowanceService, SpeedTargetEngine per operasi, SetupTimeEngine, master tooling, aturan tunggu, konstanta. Diterima bila tiap operasi punya target, waste, spoilage dan setup yang diisi engine.
4. Fase 4, penjadwal otomatis: skor prioritas, PDR dengan pilihan mesin, kunci dalam shift, AutoPlanRunner membuat Draft, daftar pengecualian, prioritas officer dan Confirm, log keputusan. Diterima lewat test regresi dan satu minggu dummy yang dikonfirmasi.
5. Fase 5, layar: perluasan DevLib, lalu tab Schedule (strip pengecualian, Gantt, Kanban, Calendar, Grid), panel Work Order, tab Monitoring. Diterima user di situs dummy.
6. Fase 6, pembelajaran: pencatatan log setup di entri lantai produksi, LearningEngine dengan batas, log perubahan dengan pengembalian. Butuh cukup job nyata; sampai saat itu nilai tetap Assumed.
7. Tiap fase: test di MySQL sungguhan dengan pembersihan terverifikasi, build:catalog, preview JPEG atau screenshot, commit dan push dengan tanda agen, dan rilis live hanya setelah user setuju.
