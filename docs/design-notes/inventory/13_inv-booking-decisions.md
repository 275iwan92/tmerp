# Booking: Seven Decisions With Reference Answers

Tujuh pertanyaan terbuka tentang booking, masing-masing dengan cara SAP, Oracle NetSuite, Odoo, Dynamics 365 dan ERPNext menanganinya, serta jawaban yang disarankan. Saran adalah usulan untuk dikonfirmasi user.

## Isi catatan

1. Over-run. SAP menerima penerimaan barang hanya sampai qty order ditambah toleransi overdelivery, dan scrap menaikkan kebutuhan material di planning. Saran: booking memuat waste tetapi tanpa over-run tersembunyi; toleransi adalah batas penerimaan.
2. Booking basi. Pembersihan reservasi SAP menandai item yang tanggal butuhnya lebih tua dari tanggal dasar dikurangi sekian hari (standar 30). Odoo mereservasi hanya sekian hari sebelum tanggal. Saran: peringatan dulu, lepas setelah batas tertentu.
3. Order berubah setelah Confirm. Di SAP perubahan qty menjalankan ulang cek ketersediaan dan qty terkonfirmasi mengikuti stok. Saran: selisih otomatis; lepas kelebihan segera dan coba booking tambahannya, bagian terkunci tetap.
4. Hot job. Backorder processing SAP mengurutkan menurut prioritas customer, prioritas rush, tanggal dibuat atau ketersediaan. NetSuite mengikat order tertua dulu, dengan halaman Reallocate Items manual. Saran: aturan plus ambil alih terbatas, ber-log.
5. Pemberitahuan ke order yang kehilangan stok. Sumber yang dibaca tidak menjelaskannya, jadi ini tambahan kita sendiri. Saran: pemberitahuan popup ke PPIC dan pemilik order, berisi siapa yang mengambil stok dan alasannya.
6. Pengganti saat Short. Planning SAP bisa memperhitungkan grup item alternatif menurut probabilitas pemakaian; Odoo memakai komponen cadangan berprioritas dan membolehkan menambah pengganti di order. Saran: sistem mengusulkan berurutan, PPIC mengonfirmasi.
7. Die tidak tersedia. SAP memeriksa alat produksi untuk sebuah order dan mencatat status kekurangan alat bila ada yang hilang. Saran: peringatan saat booking, dan work order tidak dirilis tanpa die atau tanpa override beralasan.
8. Order dibatalkan setelah issue. SAP membalik komponen yang sudah keluar lewat pembalikan yang merujuk ke order, sehingga reservasi ikut diperbarui. Saran: material tak terpakai kembali ke stok bebas; yang tercetak atau terpakai dihapus buku atau ditagih.

## Istilah dan rincian

- **Reference: SAP**: Requirement, cek ketersediaan dengan schedule line terkonfirmasi, backorder processing, grup item alternatif, ketersediaan alat, pembersihan reservasi, pembalikan goods issue.
- **Reference: NetSuite**: Pengikatan inventory ke sales order, tertua dulu, dan halaman Reallocate Items manual. Tidak ada pemberitahuan yang didokumentasikan.
- **Reference: Odoo**: Metode reservasi: saat konfirmasi, manual, atau sebelum tanggal terjadwal. Komponen cadangan berprioritas. Pengganti bisa ditambah di order.
- **Reference: Dynamics 365**: Hierarki reservasi menentukan urutan dimensi yang direservasi, dengan kedaluwarsa terdekat dulu untuk batch. Priority based planning mengurutkan order menurut jenis.
- **Reference: ERPNext**: Stock reservation entry per item sales order, dilepas dari order atau dengan membatalkan entry.
- **Limits of these answers**: Dari dokumentasi dan halaman komunitas hasil pencarian; tidak semua detail diverifikasi di produknya. Konfirmasikan tiap saran dengan user.
