# Technical Plan: Workflow, Receiving and Quality

Alur kerja masuk: receiving, QC sampling, jalur URGENT langsung ke produksi, putaway, dan kurang kirim yang baru ketahuan belakangan.

## Isi catatan

1. Receiving di dok: hitung, timbang, beri label pallet, dan ambil sampel sesuai rencana sampling.
2. Jalur normal: uji cepat di dok memutuskan lot (Released atau Rejected), lalu penerimaan diposting dan pallet disimpan.
3. Jalur URGENT: pihak berwenang menandainya dengan alasan dan work order. Langsung diposting sebagai Conditional dan dibawa ke mesin.
4. Sampling berjalan paralel untuk penerimaan URGENT. Penolakan menelusur maju ke work order dan barang jadi, menahannya, dan membuat klaim supplier.
5. Putaway menyarankan bin dari aturan area (mixing, pick order, kapasitas, stack); petugas mengonfirmasi dengan scan pallet lalu bin.
6. Kurang kirim: bila output ditambah waste standar masih lebih dari material yang dikeluarkan, qty sudah kurang sejak awal; kekurangannya diminta.

## Istilah dan rincian

- **Sampling plan**: Ukuran sampel menurut jumlah lot dengan intensitas ketat, normal, ringan, atau lewati. Semua nilai adalah pengaturan.
- **Odoo receipts**: Satu, dua, atau tiga langkah; pada tiga langkah barang melewati area mutu sebelum masuk stok.
- **SAP EWM**: Barang diterima ke interim bin, lalu task putaway memindahkannya ke bin tujuan.
- **Dynamics 365**: Receiving membuat work dengan baris Pick dari dok dan baris Put ke lokasi.
- **Shortfall**: (Output + waste standar) dikurangi qty yang dikeluarkan. Waste di atas standar adalah varians pemakaian, bukan klaim.
- **If the supplier refuses**: Setelah 7 hari baris PO ditutup di qty terima dan diminta debit note atau potongan tagihan.
