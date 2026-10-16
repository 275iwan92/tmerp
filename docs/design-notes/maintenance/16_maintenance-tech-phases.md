# Technical Plan: Phases 2 to 4 and Limits

Apa yang dikirim Fase 2, 3 dan 4, dalam urutan apa, dan batas yang berlaku untuk semua fase.

## Isi catatan

1. Fase 2 Preventive (dibangun): Preventive Plan, Preventive Maintenance Order, PM Calendar, Daily Check, Meter Readings, PM Compliance. Yang masih menyusul di Fase 2: slot mesin terblokir di Scheduling Board.
2. Fase 3 Breakdown: tombol Breakdown di worklist produksi membuat request prioritas P1, pengeluaran spare part ke stok, permintaan pembelian dengan status Waiting Part, dan laporan Machine Reliability.
3. Fase 4 Improvement: tabel dan service, detektor kerusakan berulang, layar Proposal dan Review, order perawatan jenis IMPROVEMENT, dan Apply to master dengan persetujuan manual.
4. Master Data lebih dulu karena fase lain membaca datanya. Tiap fase selesai bila test lulus dan datanya dicek di database.
5. Pekerjaan dilakukan di situs mockup dengan database ddt. Tidak ada yang ditulis ke database tm2 sebelum rilis.
6. Inquiry, ProDev dan ProCost tidak disentuh. Layar dibangun dari objek DevLib, tanpa HTML mentah.
7. Data mesin nyata tidak pernah dikarang. Watch point, interval plan dan part di database live diisi kepala maintenance lewat layar.

## Istilah dan rincian

- **Planned percentage**: Maintenance order terencana dibagi semua order perawatan. Tampil di laporan keandalan dan kepatuhan.
- **Scheduling Board**: Maintenance order preventive Confirmed memblokir mesinnya. Produksi hanya bisa mengusulkan menggeser jendela, kepala maintenance yang mengonfirmasi.
- **Inventory and Procurement**: Spare part dikeluarkan dari Inventory terhadap order perawatan. Part yang kurang membuat permintaan pembelian.
- **Backlog items still open**: Perapian spare part lama yang masih di kategori Others, dan aturan inspeksi wajib untuk forklift dan kompresor.
