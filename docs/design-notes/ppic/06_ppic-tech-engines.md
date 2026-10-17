# Technical Plan: Engines and Services

> Catatan 2026-10-18: PdrEngine, CompositePriorityScorer, SchedulingRuleEngine, papan Scheduling Board v1 dan layar Rule Library telah dihapus (audit modul ganda); penjadwal yang berlaku = ScheduleSolver + AutoPlanRunner. Butir di bawah yang menyebutnya adalah rencana lama.

Engine dan service mana yang diperluas dan mana yang baru. Semuanya didaftarkan di katalog engine, dan logika tidak ada di layar.

## Isi catatan

1. Tiap engine baru mendapat entri di EngineRegistry (kegunaan, input, output, cara kerja) dan katalog diperbarui dengan engine:catalog. EngineCatalogTest menjaganya.
2. Tiap engine punya test regresi terhadap database MySQL lokal sungguhan; test yang menyisipkan data membersihkan sendiri dan pembersihannya diverifikasi di database.
3. Engine penghitung (setup, waste, beban, kesiapan) bersifat murni. AutoPlanRunner hanya menulis rencana Draft. Jadwal nyata berubah hanya saat officer mengonfirmasi; konfirmasi dan setiap perubahan masuk log keputusan.

## Istilah dan rincian

- **AutoPlanRunner (new)**: Menjalankan rantai dan menghasilkan rencana Draft: cek Work Order, MRP dan gerbang, perkiraan, jadwal, daftar pengecualian. Pemicu: harian, awal tiap shift dan kejadian (Work Order baru, bahan datang, kerusakan, prioritas officer).
- **WoGenerationService (extend)**: Sudah membuat satu Work Order per part dari rencana kirim. Perluas: simpan id proses pada tiap operasi, isi jumlah awal dari kaskade waste, salin baris BOM, tandai part tanpa routing.
- **SpeedTargetEngine (reuse)**: Memberi perkiraan target per jam untuk setiap operasi: riwayat mesin dulu, lalu spesifikasi model, lalu default proses. Menggantikan pengetikan target manual.
- **MaterialPlanningEngine (reuse)**: Berjalan harian dan sesuai permintaan: menetralkan stok, booking dan pembelian yang datang per Work Order, mengusulkan booking dan purchase request. GateService membaca hasilnya sebagai gerbang bahan.
- **SetupTimeEngine (new)**: Setup antara dua job di satu mesin = setup dasar + changeover (tinta, jenis tinta, bahan, ukuran, plat, pisau) + setup tooling. Mengembalikan menit, waste tambahan dan rincian untuk tampilan.
- **GateService (new)**: Gerbang bahan, tooling, artwork, barang titipan dan kapasitas per operasi: Ready, Waiting dengan tanggal, atau Blocked dengan alasan. Membaca Booking, incoming Material Planning, status tooling dan persetujuan proof.
- **CompositePriorityScorer (extend)**: Mempertahankan lapisan Hot, Critical Ratio dan due date. Menambah tier customer, ageing dan skor sadar setup di dalam jendela lapis 2. Slack memakai seluruh sisa routing.
- **SchedulingRuleEngine (extend)**: Aturan urutan sebagai data: kelompokkan menurut set tinta, bahan, ukuran dan pisau. Menghaluskan urutan hanya di antara task berprioritas setara.
- **PdrEngine (extend)**: Penjadwalan mundur menambah tunggu dari prod_wait_rule, waktu antrean, dan memilih di antara mesin sebuah proses menurut urutan preferensi dan beban.
- **CapacityLoadService (new)**: Beban per grup mesin = jam rencana (termasuk setup) dibagi jam tersedia setelah shift, libur dan order perawatan. Menandai bottleneck di atas ambang.
- **WasteAllowanceService (new)**: Kaskade mundur dari jumlah jadi melalui routing: input = output dibagi (1 dikurangi persen variabel) ditambah tetap. Dipakai pembuatan work order dan Material Planning.
- **LearningEngine (new)**: Membaca log setup dan output produksi, mengusulkan nilai terkoreksi dengan status dan jumlah sampel. Pola sama dengan SpeedTargetEngine dan SpeedSuggestionStore.
- **MonitoringService (new)**: Rencana lawan aktual per operasi: selesai, hasil baik, waste, menit setup, bahan terpakai lawan keluar, alasan telat. Menyuplai KPI dan engine pembelajar.
