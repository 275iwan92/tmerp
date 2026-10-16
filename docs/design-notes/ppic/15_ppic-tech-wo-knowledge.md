# Technical Plan: What the Work Order Must Know

Apa yang dibawa Work Order dari Product Development agar penjadwalan tahu setiap proses, setiap mesin yang bisa menjalankannya, serta target, waste dan spoilage tiap langkah, dan agar bahan diminta sekali dengan semua waste sudah termasuk.

## Isi catatan

1. Saat Work Order dibuat ia menyalin dari ProDev: referensi dieline, imposisi (ups per lembar, ukuran lembar, serat, yield), BOP, routing, BOM dan tooling. Salinan itu dibekukan di Work Order, seperti transaksi lain.
2. Tab Product and dieline menampilkan blank datar dengan garis potong dan lipat, imposisi pada lembar cetak, dan spesifikasi: model, ukuran, board, ups, yield, warna, finishing dan tooling.
3. Tab Routing and machines menampilkan proses berurutan dan, untuk tiap proses, mesin yang bisa menjalankannya, yang dipilih engine, dan yang tidak bisa beserta alasannya.
4. Sebuah mesin eligible hanya bila cocok dengan ukuran lembar, berat board, jumlah warna, kemampuan proses dan sertifikasi, pisau atau plat cocok, dan mesin tidak rusak. Batasnya berasal dari master proses-mesin.
5. Untuk tiap mesin eligible layar menampilkan target untuk produk ini, setup, beban dan mulai paling awal, sehingga officer tahu mesin mana yang bisa mengantre job. Proses tanpa mesin eligible menjadi pengecualian.
6. Satuan dibuat jelas: lembar sampai die-cutting, pcs sesudahnya, dengan ups per lembar sebagai konversi. Target disimpan dalam satuan proses dan juga ditampilkan sebagai pcs per jam.
7. Qty waste adalah kehilangan tetap saat persiapan. Qty spoilage adalah kehilangan saat jalan (persen dari output). Tiap proses meminta yang dibutuhkan: input = output dibagi (1 dikurangi persen spoilage) ditambah qty waste, dari proses terakhir mundur.
8. Input proses pertama adalah permintaan bahan. Semua waste tiap proses sudah ada di dalamnya sejak awal, jadi tidak ada permintaan susulan untuk waste di cetak, coating, die-cut atau lem. MRP dan booking memakai angka yang sama.

## Istilah dan rincian

- **tm2prd.prd_wo (add)**: Salinan beku dari ProDev: referensi dieline, ups per lembar, ukuran lembar, serat, yield, jumlah warna, model.
- **tm2prd.prd_wo_operation_machine (new)**: Satu baris per operasi dan mesin kandidat: eligible ya atau tidak, alasan, urutan, target untuk produk ini, menit setup, dihitung engine saat pembuatan dan saat master berubah.
- **tm2prd.prd_wo_operation (add)**: Satuan (lembar atau pcs), qty masuk, hasil baik, qty waste, qty spoilage dan persen, dari kaskade.
