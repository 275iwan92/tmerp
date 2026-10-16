# Technical Plan: PPIC Data Flow

Bagaimana data mengalir dari Customer Order terkonfirmasi ke operasi terjadwal, ke hasil aktual, dan kembali memperbaiki nilai asumsi.

## Isi catatan

1. Rencana kirim dari Customer Order terkonfirmasi diposting: WoGenerationService membuat satu Work Order per part, dengan operasi dari BOP dan routing dan baris bahan dari BOM. Id proses disimpan.
2. Perkiraan, otomatis: WasteAllowanceService menentukan jumlah awal, SpeedTargetEngine target per jam, SetupTimeEngine menit setup tiap operasi.
3. Kesiapan bahan, otomatis: MaterialPlanningEngine menetralkan stok, booking dan pembelian yang datang dan mengusulkan booking dan purchase request; GateService menandai tiap operasi Ready, Waiting atau Blocked.
4. AutoPlanRunner menjadwalkan operasi yang Ready sebagai rencana Draft: skor prioritas, penjadwalan mundur dengan waktu tunggu, pemilihan mesin di antara mesin proses. Ia tidak pernah menyentuh jendela terkonfirmasi yang terkunci.
5. Officer meninjau pengecualian (berisiko telat, bahan menunggu, tooling terblokir, kelebihan beban), boleh memberi prioritas pada job (Hot atau naikkan, dengan alasan), lalu mengonfirmasi. Confirm menulis mulai dan selesai nyata dan mengunci shift pertama.
6. Lantai produksi mencatat output, cacat dan menit setup aktual. MonitoringService membandingkan rencana dan aktual; LearningEngine mengoreksi nilai asumsi dalam batasnya dan mencatat tiap perubahan.
