# Technical Plan: Work Order, Gantt Drag and Drop, Re-schedule and BMO

> Catatan 2026-10-18: PdrEngine, CompositePriorityScorer, SchedulingRuleEngine, papan Scheduling Board v1 dan layar Rule Library telah dihapus (audit modul ganda); penjadwal yang berlaku = ScheduleSolver + AutoPlanRunner. Butir di bawah yang menyebutnya adalah rencana lama.

Layar Work Order dengan BOP dan BOM, tautannya ke Gantt, cara kerja drag and drop, bagaimana layar menunjukkan apa yang harus di-re-schedule, dan bagaimana masalah mesin membuka Breakdown Maintenance Order.

## Isi catatan

1. Layar Work Order: header (customer, produk dan revisi, jumlah, order sumber, due date, slack), Gantt mini dari operasinya, lalu BOP dan routing serta BOM dan material dalam dua tab ditambah Output dan History.
2. Tabel BOP dan routing: proses, mesin (dengan alternatif), qty masuk dan hasil baik dari kaskade waste, target per jam, waste, spoilage, setup, jalan, tanggal rencana, status dan output sejauh ini. Perkiraan berasal dari engine.
3. Tabel BOM: tiap material dengan tempat pemakaiannya, kebutuhan termasuk waste, stok, booking, yang datang dan gerbangnya, plus tooling (plat, pisau) dengan status. Tautan membuka Material Planning, Material Booking dan purchase order.
4. Tautan ke Gantt: Open in Gantt di header atau pada satu operasi membuka Gantt yang difilter ke Work Order itu dan terfokus pada barnya. Mengeklik bar di Gantt membuka panel Work Order.
5. Drag and drop: pindahkan bar dalam waktu atau ke mesin lain yang bersertifikat untuk proses yang sama. Bayangan hijau dan kotak dampak menunjukkan tanggal baru, perubahan setup dan job yang telat. Pemindahan masuk Draft; Confirm plan menjadikannya nyata.
6. Drop ditolak (merah): di dalam jendela terkunci, gerbang terblokir, mesin yang rusak, atau mesin tidak bersertifikat. Membuka kunci butuh Hot job dan alasan. Server memeriksa tiap drop dengan PdrEngine::validateManualReschedule.
7. Monitoring di Gantt: bar rencana, strip progres biru aktual dari output produksi dan timer, dan ekor merah putus-putus saat perkiraan selesai melewati rencana. Pita Down mesin berasal dari Breakdown Maintenance Order.
8. Daftar Perlu re-schedule: operasi tertinggal dari rencana, di mesin rusak, terblokir tooling atau bahan, atau bertabrakan dengan hot job. Tiap item menampilkan alasan, perkiraan dan Re-plan, yang meminta Draft baru dari engine di sekitar job terkunci.

## Istilah dan rincian

- **Output entry by PPIC**: Output dapat diinput dari Work Order (tautan Output pada operasi) atau dari panel Gantt oleh PPIC, selain dari layar timer operator. Semuanya menulis ke tabel output produksi yang sama.
- **Problem button to BMO**: Tombol Problem di layar operator, dan alasan jeda Masalah mesin, membuka request Breakdown Maintenance Order yang sudah terisi mesin, work order, operasi dan timer. Prioritas P1 pada mesin berhenti memulai downtime.
- **BMO back to scheduling**: Selama order terbuka, mesin tampil Down di Gantt dan job-nya muncul di Perlu re-schedule. Saat maintenance menekan Done, downtime ditutup dan engine membuat Draft baru.
