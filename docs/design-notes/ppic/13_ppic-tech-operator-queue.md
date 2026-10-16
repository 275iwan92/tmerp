# Technical Plan: Operator Queue, Timer and Quantity Input

Layar tablet di mesin yang menampilkan antrean terkonfirmasi, dengan timer Play, Pause dan Stop per proses dan input qty. Layar ini mencatat waktu setup, jalan dan jeda nyata yang dibutuhkan engine pembelajar.

## Isi catatan

1. Hanya menampilkan antrean terkonfirmasi dan terkunci untuk mesin operator: job yang berjalan, job berikutnya dengan daftar cek (bahan, plat, tinta) dan sisa antrean. Operator tidak dapat mengubah urutan.
2. Play pada job berikutnya memulai timer setup; Play lagi memulai timer jalan. Pause menghentikan timer dan menanyakan alasan: menunggu bahan, istirahat, masalah mesin, tahan mutu, menunggu crew, lainnya.
3. Hanya satu timer berjalan per mesin dan operator pada satu waktu. Tombol Problem, dan alasan jeda Masalah mesin, membuka request Breakdown Maintenance Order yang terisi mesin, work order dan timer.
4. Add quantity mencatat lembar baik selama jalan. Layar menampilkan jumlah baik, kecepatan aktual terhadap target dan waste sejauh ini terhadap toleransi.
5. Stop menutup timer dan menanyakan jumlah baik, waste dan reject beserta alasan reject (tipe scrap). Ditampilkan rencana lawan hasil, waste lawan toleransi, pembagian waktu dan kecepatan.
6. Log waktu menyuplai tab Monitoring (rencana lawan aktual) dan engine pembelajar: menit setup aktual per jenis perubahan, kecepatan aktual (jumlah jalan dibagi waktu jalan) dan waste aktual.
7. Jumlah tetap masuk ke tabel output produksi yang sudah ada (output dan baris cacat per tipe scrap). Yang baru adalah log waktu, sehingga tidak dibuat tabel output kedua.

## Istilah dan rincian

- **tm2prd.prd_operation_time_log**: Baru. Satu baris per segmen timer: operasi, mesin, operator, jenis (SETUP, RUN, PAUSE), alasan jeda, mulai, selesai, menit, jenis perubahan untuk setup. Menggantikan rencana prd_setup_log dengan satu log.
- **tm2prd.prd_wo_operation_output**: Sudah ada. Qty baik per entri. Tambah entri checkpoint selama jalan dan tautan ke segmen log waktu.
- **tm2prd.prd_wo_operation_output_defect**: Sudah ada. Qty per tipe scrap, kategori REJECT atau WASTE. Stop menulis baris waste dan reject di sini.
