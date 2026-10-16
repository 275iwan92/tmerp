# Technical Plan: SOP and Work Instructions per Machine

Cara SOP dan empat instruksi kerja (WI) disusun untuk setiap mesin, diberi nomor, disetujui dan tampil di Asset Maintenance Profile. Semua teks berstatus draf sampai disetujui.

## Isi catatan

1. Mesin dikelompokkan menjadi set menurut jenis mesin. Tiap set punya satu SOP dan empat instruksi kerja: cek harian, perawatan mingguan, perawatan bulanan dan respons awal breakdown.
2. Nomor mengikuti satu pola: SOP-MNT dan WI-MNT, lalu kode set, lalu urutan dua digit, misalnya WI-MNT-OFF-01 untuk cek harian mesin offset.
3. Teks berupa praktik baik yang umum. Setiap batas, interval dan spesifikasi berbunyi sesuai manual pabrikan. Tidak ada spesifikasi mesin yang dikarang.
4. Instruksi perawatan mingguan dan bulanan diawali lock-out tag-out dan diakhiri melepas kunci dan uji jalan.
5. Kepala maintenance meninjau tiap dokumen, menambah batas dari manual, dan mengubah status menjadi APPROVED. Sampai saat itu status tetap DRAFT.
6. Tab Documents pada Asset Maintenance Profile menampilkan SOP dan WI mesin itu. Daily Check mengikuti WI harian, dan Preventive Order mengikuti WI mingguan dan bulanan.
7. Dokumen disimpan dalam bahasa Inggris. Teks Indonesia untuk operator datang lewat kamus dan bahasa cetak.

## Istilah dan rincian

- **Sets**: Dua puluh set mencakup empat puluh lima mesin: CTP, PLT, DIE, DIG, HTR, FGL, AGL, LAM, OFF, CUT, PRS, SEA, SHR, STI, STR, UVC, WBC, WAX, CMP, FKL.
- **Documents per set**: Satu SOP dan empat WI, jadi seratus dokumen seluruhnya.
- **SOP sections**: Scope, Responsibilities, Safety, Procedure, Records, Review.
- **Safety content**: Aturan pelindung umum ditambah bahaya jenis mesin itu, misalnya gaya potong mesin die cut atau tekanan kompresor.
- **Status**: DRAFT sampai disetujui kepala maintenance, lalu APPROVED dengan nomor revisi.
- **Source file**: Isi draf disimpan di resources/data/maintenance/sop_wi.json dan dimasukkan ke database dummy di Fase 1.
