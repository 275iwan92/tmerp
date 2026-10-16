# PPIC: Review of the Source Specification and Open Decisions

Apa yang dipertahankan, diubah dan ditambah dibanding spesifikasi Strategic Scheduling, serta keputusan yang harus diambil user sebelum layar apa pun direncanakan.

## Isi catatan

1. Dipertahankan: ruang lingkup setelah Inquiry dan ProDev, matriks setup, pengelompokan tinta, kertas dan ukuran, kapasitas terbatas dengan bottleneck, gerbang tooling, toleransi waste per proses, job jangkar dan kunci 8 sampai 12 jam.
2. Diubah: rumus prioritas (satuan, nilai sama untuk semua job telat); slack menghitung seluruh sisa routing dan waktu tunggu; pengelompokan setup hanya mengurutkan job yang urgensinya mirip; waste = tetap + variabel, dihitung mundur.
3. Ditambah: waktu tunggu kering dan curing, tinta UV lawan konvensional, caliper dan arah serat, penghitung pemakaian tooling, pelepasan WIP (rope), pecah lot, subkon, jendela perawatan, rencana ulang berbasis kejadian dan KPI.
4. Dikoreksi: Yellow, Magenta, Cyan, Black adalah urutan warna di dalam satu job yang diatur prepress, bukan aturan antar job. Antar job aturannya terang sebelum gelap dan spot color yang sama dikelompokkan.
5. Diputuskan 1: mulai dari menit setup, rentang waste dan bobot (0,50 / 0,35 / 0,15) asumsi; lalu mesin pembelajar menyesuaikannya dari hasil aktual.
6. Diputuskan 2: kelas pelanggan (Tier 1 sampai 3) disimpan di master Customer dan dirawat Sales. Diputuskan 3: master tooling dibangun, dengan waktu setup per tooling.
7. Masih terbuka: grup mesin mana yang kandidat bottleneck, panjang masa kunci (8 atau 12 jam), dan kebijakan die-cutting yang disubkontrakkan.
8. Referensi: SMED (pengurangan changeover), aturan ATCS Lee dkk. 1997 untuk penjadwalan dengan setup bergantung urutan, Drum-Buffer-Rope dari Theory of Constraints, critical ratio, dan praktik makeready serta spoilage industri cetak.
