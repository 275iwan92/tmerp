# Technical Plan: Maintenance Rules and Formulas

Aturan, rumus indikator dan pengaturan yang akan dibaca Maintenance. Nilai berlabel usulan adalah nilai awal yang boleh diubah user.

## Isi catatan

1. Kelengkapan profil menghitung lima hal yang terisi: watch point aktif, plan aktif, spare part, dokumen, dan jenis meter. Dihitung, tidak disimpan.
2. Watch point tanpa batas normal tidak pernah dinilai sistem. Batas berasal dari manual pabrikan dan diketik oleh kepala maintenance.
3. Spare part dipilih dari lookup material. Merapikan spare part lama yang masih di kategori Others adalah tugas terpisah untuk user.
4. Teknisi bisa melihat master data. Hanya kepala maintenance dan administrator yang bisa mengubahnya.
5. Apply to master tidak pernah otomatis. Kepala mencentang dan menyetujui tiap usulan satu per satu, dan masing-masing dicatat.
6. Nomor dokumen mengikuti satu pola: prefiks, tahun dan bulan (yymm), dan urutan empat digit, misalnya PMO-2610-0001. Tiap prefiks punya urutan sendiri yang mulai lagi tiap bulan.

## Istilah dan rincian

- **MTBF**: (Jam periode dikurangi jam downtime breakdown) dibagi jumlah breakdown, dari log downtime. Perhitungannya sama dengan engine Production.
- **MTTR**: Total jam downtime breakdown dibagi jumlah breakdown, dari log downtime.
- **Availability**: Dalam OEE: (menit terencana dikurangi downtime tak terencana) dibagi menit terencana. Availability inheren di laporan keandalan: MTBF dibagi MTBF ditambah MTTR.
- **PM Compliance**: Maintenance order preventive dan inspeksi yang selesai tepat waktu dibagi yang jatuh tempo dalam periode.
- **MNT_RECURRENCE_COUNT**: Usulan 3. Kerusakan sama pada komponen yang sama yang memicu saran Improvement.
- **MNT_RECURRENCE_DAYS**: Usulan 90. Jendela hari untuk menghitung kerusakan berulang.
- **MNT_IMPROVEMENT_REVIEW_DAYS**: Usulan 60. Hari setelah Done saat review improvement jatuh tempo.
- **MNT_IMPROVEMENT_DIRECTOR_LIMIT**: Usulan 10.000.000 IDR. Di atas estimasi biaya ini direksi ikut menyetujui.
- **PMO**: Preventive Maintenance Order. Jenis order PREVENTIVE dan INSPECTION. Contoh PMO-2610-0001.
- **BMO**: Breakdown Maintenance Order. Jenis order BREAKDOWN dan CORRECTIVE. Contoh BMO-2610-0001.
- **IMO**: Improvement Maintenance Order. Jenis order IMPROVEMENT, dibuka dari Improvement Proposal. Contoh IMO-2610-0001.
- **MR, IMP, DC**: Maintenance Request MR, Improvement Proposal IMP, Daily Check DC. Pola sama dengan order.
- **OEE**: Availability kali Performance kali Quality, dihitung Production. Bila satu faktor tidak bisa dihitung, OEE tampil belum dihitung, bukan 0 persen.
- **Performance**: Output bagus dibagi jam jalan kali target kecepatan per jam, paling tinggi 100 persen.
- **Quality**: Output bagus dibagi output bagus ditambah spoilage ditambah waste.
- **Backlog**: Estimasi jam order yang masih terbuka dibagi jam teknisi tersedia per minggu, tampil dalam minggu.
- **Planned work**: Order yang bukan BREAKDOWN dibagi semua order dalam periode.
