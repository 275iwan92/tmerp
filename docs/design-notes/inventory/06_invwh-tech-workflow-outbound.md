# Technical Plan: Workflow, Booking, Picking, Transfer and Count

Alur kerja keluar dan internal: booking dari costing, alokasi pick list, lapisan peta, transfer, subkon, dan opname.

## Isi catatan

1. Tangga booking: pre-booking saat ProCost terbit, lalu quotation, customer order draft, dan kunci keras saat customer order dikonfirmasi.
2. Pre-booking tidak pernah mengurangi Available. Ia menampilkan kebutuhan dan kekurangan lebih awal supaya pembelian bisa bergerak. Satu rantai per inquiry, tidak dihitung dua kali.
3. Pick list: dari booking yang dikonfirmasi, mesin mengalokasikan pallet, batch, dan bin menurut aturan area (FIFO atau FEFO), serat, dan sertifikasi.
4. Warehouse Map tetap tidak berubah. Memilih tugas pick atau putaway hanya menambah lapisan alokasi opsional di atasnya.
5. Transfer dua langkah untuk gudang jauh: dikirim masuk In Transit, diterima masuk ke tempat baru; selisih menjadi discrepancy.
6. Opname: dijadwalkan per area, bin dikunci selama dihitung, selisih menunggu persetujuan dan diposting sebagai penyesuaian bernomor.

## Istilah dan rincian

- **Subcontract**: Send Material merujuk ke PO subkon; vendor adalah lokasi virtual milik perusahaan.
- **Odoo removal strategies**: FIFO, LIFO, FEFO, lokasi terdekat, dan paket paling sedikit menentukan stok mana yang diambil.
- **Dynamics 365 reservation**: Hierarki reservasi menentukan urutan dimensi; batch dengan kedaluwarsa paling awal dicadangkan lebih dulu.
- **Hot job**: Prioritas berbasis aturan; pengambilalihan butuh Ops Manager dan dicatat, dan order yang kehilangan stok diberi tahu.
- **Count rules**: Odoo membandingkan qty tercatat dengan qty hitung dan memposting selisih sebagai stock move; cycle count mengikuti kelas ABC.
- **Cancelled after issue**: Material tak terpakai kembali ke stok bebas; yang tercetak atau terpakai dihapus buku atau ditagih sesuai kontrak.
