# Technical Plan: Improvement, Daily Check and PM Calendar

Perilaku yang direncanakan untuk tiga layar yang mockupnya belum disetujui: Improvement, Daily Check dan PM Calendar. Ini usulan.

## Isi catatan

1. Daily Check adalah lembar per mesin per shift yang dibuat dari watch point operator yang aktif. Operator menandai tiap titik OK, NOT_OK atau NA dan bisa mengetik nilai ukur.
2. Titik NOT_OK membuka Maintenance Request dengan prioritas dari kritikalitas mesin. Nilai ukur di luar batas normal ditandai NOT_OK, dan operator bisa mengubahnya.
3. PM Calendar menampilkan order perawatan preventive dan inspeksi per hari: sesuai jadwal, inspeksi tanggal tetap, terlambat, dan mesin terblokir. Klik membuka order perawatan.
4. Improvement dimulai dari request jenis IMPROVEMENT, dari saran kerusakan berulang, atau dari seseorang. Usulan memuat masalah, solusi, estimasi biaya dan penghematan.
5. Evidence (jumlah breakdown, downtime, biaya dan order perawatan terkait untuk komponen yang sama) dihitung sistem dan hanya dibaca.
6. Kepala maintenance mengonfirmasi usulan. Di atas batas biaya di Constants, direksi juga mengonfirmasi.
7. Usulan yang disetujui membuat order perawatan jenis IMPROVEMENT. Saat Done, sistem menjadwalkan review.
8. Saat review sistem mengisi downtime dan biaya sebelum dan sesudah pada periode sama panjang. Orang menyimpulkan: Effective, Partial atau Not effective.
9. Apply to master membuka usulan perubahan (interval plan, watch point atau part mesin). Kepala mencentang dan menyetujui satu per satu. Sistem tidak pernah mengubah master sendiri.

## Istilah dan rincian

- **Repeated failure suggestion**: Kerusakan sama pada komponen yang sama mencapai jumlah di Constants dalam jendela di Constants. Sistem menyarankan, tidak membuat dokumen.
- **Lifecycle**: Improvement memakai lifecycle standar: Draft, Confirmed, Cancelled, Closed.
- **Evidence**: Angka yang hanya dibaca, dihitung dari order perawatan dan downtime yang sudah tercatat.
- **Review**: Jatuh tempo sejumlah hari di Constants setelah Done. Angka sebelum dan sesudah diisi sistem.
- **Apply to master**: Selalu keputusan manual oleh orang, satu perubahan per satu, dengan catatan log untuk tiap perubahan.
