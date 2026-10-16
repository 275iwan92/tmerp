# Technical Plan: PPIC Tables and Columns

Perubahan tabel yang direncanakan, dikelompokkan per database. Nama dalam bahasa Inggris; relasi lintas database bersifat logis dan dicek Laravel, bukan foreign key.

## Isi catatan

1. Tiap tabel baru memakai satu primary key BIGINT auto number. Migration mencerminkan dump live, dijalankan dulu di database dummy, dan butuh persetujuan user sebelum mengubah skema live.
2. prd_wo_operation saat ini tidak punya id proses dan kolom setup. Menambahkannya memungkinkan layar menggabungkan ke tarif proses dan mesin dan menyimpan rencana di samping aktual.
3. Kolom yang sudah ada dipakai ulang: qty waste dan spoilage di operasi, waste default dan waste tetap di proses dan proses-mesin, target per jam di tabel tarif part, is_bottleneck di proses.
4. Nilai yang bisa dipelajari membawa status (Assumed, Learning, Calibrated), jumlah sampel dan flag bekukan, agar engine pembelajar tahu apa yang boleh disesuaikan.
5. Pengaturan penjadwalan (kunci dalam shift, bobot, ambang bottleneck, batas pembelajaran) adalah baris di Settings > Constants, sehingga setiap nilai punya layar input. Jumlah shift per mesin berasal dari kalender mesin.

## Istilah dan rincian

- **tm2mst.prod_tooling_asset**: Baru. Kode, jenis (set plat, pisau pond, matras emboss, foil), referensi produk atau dieline, ukuran, lokasi, status, impresi rencana dan terpakai, menit setup run pertama dan run ulang.
- **tm2mst.prod_machine_changeover**: Sudah ada (mesin, jenis, dari, ke, menit tambahan, waste tambahan). Tambah status nilai, jumlah sampel dan flag bekukan.
- **tm2mst.prod_process_machine**: Sudah ada dengan menit setup, kecepatan, persen waste dan waste tetap. Tambah status nilai, jumlah sampel dan flag bekukan untuk tiap nilai yang bisa dipelajari.
- **tm2mst.prod_wait_rule**: Baru. Waktu tunggu minimum dalam menit setelah sebuah proses untuk jenis coating atau tinta sebelum proses berikutnya (kering, curing).
- **tm2mst.cust_customer**: Tambah tier customer (1 sampai 3, default 2). Dirawat di master Customer oleh Sales.
- **tm2prd.prd_wo_operation**: Tambah id proses, menit setup dasar, changeover dan tooling, target per jam, menit jalan rencana, status rencana (DRAFT atau CONFIRMED), flag terkunci dan alasan, dijadwalkan oleh (ENGINE atau OFFICER), menit setup aktual.
- **tm2prd.prd_operation_time_log**: Baru. Satu baris per segmen timer (setup, jalan, jeda dengan alasan) dari layar operator, dengan jenis perubahan dan waste tambahan untuk setup. Sumber monitoring dan pembelajaran.
- **tm2prd.prd_learning_proposal**: Baru. Tabel dan baris sasaran, field, nilai lama, nilai baru, jumlah sampel, dasar, status (Proposed, Applied, Rejected, Reverted), diputuskan oleh dan pada.
- **tm2prd.prd_plan_run**: Baru. Satu baris per auto-plan: waktu, pemicu (harian, awal shift, kejadian), operasi yang dijadwalkan, pengecualian yang ditemukan, durasi, dan siapa yang mengonfirmasi serta kapan.
