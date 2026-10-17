# Technical Plan: Scheduling Screen Design

> Catatan 2026-10-18: PdrEngine, CompositePriorityScorer, SchedulingRuleEngine, papan Scheduling Board v1 dan layar Rule Library telah dihapus (audit modul ganda); penjadwal yang berlaku = ScheduleSolver + AutoPlanRunner. Butir di bawah yang menyebutnya adalah rencana lama.

Cara satu layar penjadwalan disusun dari objek DevLib agar empat tampilan memakai data, filter dan aturan yang sama.

## Isi catatan

1. Layar dibuka dengan banner Draft dan strip pengecualian: waktu auto-plan terakhir, operasi yang dijadwalkan, yang butuh manusia (berisiko telat, bahan menunggu, tooling terblokir, kelebihan beban) dan tombol Confirm plan.
2. Berkas layar tipis: hanya menyerahkan satu array dari kelas data (ProductionSchedule) ke objek. Tanpa HTML mentah, sesuai aturan layar v2.
3. Satu query membangun daftar operasi untuk periode, proses dan mesin terpilih. Gantt, Kanban, Calendar dan Grid adalah empat proyeksi dari daftar itu, jadi perubahan filter memperbarui keempatnya.
4. Objek: x-dl::gantt, x-dl::kanban, x-dl::calendar, x-dl::sheet untuk tampilan; x-dl::tabs untuk tab halaman; x-dl::drawer untuk panel work order; x-dl::kpi untuk Monitoring.
5. Filter mesin: sebuah proses mendaftar mesinnya dari prod_process_machine (urutan preferensi, tersertifikasi). Banyak mesin per proses itu wajar; Gantt mengelompokkan baris proses lalu mesin.
6. AutoPlanRunner mengisi Draft. Pemindahan manual di Gantt adalah penimpaan prioritas: simulasi dulu, dicek PdrEngine::validateManualReschedule, disimpan dengan alasan. Confirm plan membuat Draft menjadi nyata. Edit Grid disimpan sekaligus.
7. Perpindahan Kanban divalidasi server: hanya kartu Pending atau Assigned yang boleh pindah; Running dan Done tidak. Pemindahan yang ditolak membiarkan kartu di tempatnya.
8. Panel work order menampilkan rincian setup, tabel routing (target, waste, spoilage, setup per proses), baris bahan dengan booking dan PO datang, dan output dengan tautan ke Production Output.
