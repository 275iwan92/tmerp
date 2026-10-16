# Open Decisions for Inventory, With Recommended Answers

Keputusan yang membentuk desain inventory, masing-masing dengan jawaban yang disarankan dan alasannya. Berlabel usulan sampai user mengonfirmasi.

## Isi catatan

1. Valuasi: rata-rata tertimbang, perpetual. Diterima standar Indonesia, didukung sistem besar, dan mudah dijelaskan. Tambah lapisan FIFO hanya bila auditor atau customer memintanya.
2. Stok minus: tidak diizinkan sebagai default. Pengeluaran sebelum penerimaan dicatat merusak rata-rata. Izinkan hanya per lokasi dengan aturan koreksi.
3. Aturan substitusi: ketiga lapisan, customer di atas produk di atas global, supaya satu customer bisa menolak yang diizinkan aturan global.
4. Persetujuan customer: catat di sistem dengan siapa, kapan dan lampiran, untuk downgrade dan perubahan proses.
5. Kepemilikan die: catat per die. Die milik customer ditagih sebagai tooling, die milik perusahaan sebagai aset yang dibagi ke box yang dibuat.
6. Offcut dan roll sisa: lacak di atas ukuran dan berat minimum, di rak khusus, dan scrap sisanya per berat.
7. Standar waste: satu nilai per mesin dan proses di master, dibandingkan dengan aktual dari laporan produksi, bukan satu persen per produk.
8. Sisa barang jadi customer: masa retensi sebagai pengaturan, lalu customer diminta memutuskan, dengan default tagih atau musnahkan sesuai kontrak.
9. FSC dan food grade: masukkan atribut batch sejak awal. Menambah kolom sertifikasi belakangan berarti menyentuh setiap catatan stok.

## Istilah dan rincian

- **Status of these answers**: Usulan berdasarkan praktik umum ERP besar dan aturan pabrik ini. Belum final sampai user mengonfirmasi tiap butirnya.
- **Accounting standard**: LIFO tidak diperbolehkan oleh PSAK 14 dan IAS 2. Rata-rata dan FIFO diperbolehkan.
- **Settings**: Setiap ambang yang dipilih di atas disimpan sebagai pengaturan yang bisa diubah user, bukan nilai tetap di kode.
