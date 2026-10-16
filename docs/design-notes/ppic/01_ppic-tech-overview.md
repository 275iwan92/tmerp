# Technical Plan: PPIC Scheduling Module

Rencana teknis PPIC: Work Order berasal dari data Product Development, dan engine menyiapkan kesiapan bahan, perkiraan dan draf jadwal secara otomatis. Officer mengonfirmasi rencana dan dapat memberi prioritas pada job.

## Isi catatan

1. Model kerja (diputuskan): Work Order dibuat dari data ProDev (BOP, routing, BOM). Kesiapan bahan (MRP), perkiraan target dan jadwal disiapkan otomatis oleh engine. Officer mengonfirmasi rencana dan dapat memberi prioritas pada job.
2. Layar: satu layar Production Scheduling dengan empat tampilan (Gantt, Kanban, Calendar, Grid), difilter per proses dan mesin, menampilkan setup time, gerbang dan daftar pengecualian lebih dulu, plus tab Monitoring.
3. Dibangun di atas yang sudah ada: WoGenerationService, MaterialPlanningEngine, SpeedTargetEngine, PdrEngine, CompositePriorityScorer, SchedulingRuleEngine, tabel changeover dan objek DevLib.
4. Baru: AutoPlanRunner (menjalankan rantai), master tooling, engine setup time, kaskade toleransi waste, gerbang kesiapan, beban kapasitas, engine pembelajar, service monitoring dan layar v2 dengan perluasan DevLib.
5. Prinsip: engine menyiapkan dan mencatat tiap perubahan; tidak ada yang menjadi jadwal nyata tanpa Confirm officer; engine tidak pernah memindahkan task terkunci; tanpa foreign key lintas database; setiap nilai punya layar input.
6. Konfirmasi: engine berjalan sendiri dan menghasilkan rencana berstatus Draft. Officer meninjau pengecualian dan mengonfirmasi; hanya rencana terkonfirmasi yang menjadi jadwal nyata. Booking dikonfirmasi otomatis bila stok mencukupi seluruh kebutuhan.
7. Zona beku: Inquiry, ProDev dan ProCost tidak disentuh. PPIC hanya membaca BOP, routing dan BOM yang mereka hasilkan. Pengerjaan dalam enam fase, tiap fase ditinjau user.
