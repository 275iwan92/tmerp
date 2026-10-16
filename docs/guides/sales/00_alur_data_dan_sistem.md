# Alur Data dan Alur Sistem Sales: ke mana informasi mengalir, dan mengapa

*Panduan untuk Jo. Ditulis dari membaca kode (16 Oktober 2026), bukan dari dokumen lama. Rumus dan layar rinci ada di artikel pendamping `01_inquiry_product.md`, `02_prodev.md`, `03_procost.md`, `04_quotation_order_cpdel.md`; di sini hanya jalan ceritanya. Hal yang saya ragukan ditandai "belum terverifikasi".*

Satu pesanan kardus melewati sebelas stasiun, dari permintaan customer sampai uang masuk bank. Di setiap stasiun ada informasi yang **lahir**, ada yang **disalin** (dibekukan, sehingga perubahan di hulu tidak mengubah dokumen yang sudah jalan), dan ada yang **ditautkan** (dokumen menunjuk ke data asalnya, sehingga mengikuti bila asalnya berubah). Memahami mana yang salinan dan mana yang tautan adalah kunci memahami sistem ini.

## 1. Gambaran satu halaman

Urutannya: **Inquiry → ProDev → Master Card → ProCost → Quotation → (WIN) Customer Order → CPDEL → Work Order → Delivery Order → Bukti Terima → Invoice → Pembayaran.**

| Dari | Ke | Yang mengalir | Salin / tautan | Alasan |
|---|---|---|---|---|
| Inquiry (`iqp_inquiry`, `iqp_part`) | ProDev (`prod_part`, `prod_layout_group`) | Spesifikasi kasar: dieline, ukuran, bahan, warna, finishing, isi per pack | Tautan dua arah (`iqp_part.prod_part_id`); ProDev boleh mengubah dieline lalu disimpan ke keduanya | Sales mengunci "apa yang diminta"; ProDev menerjemahkannya menjadi "cara membuatnya" |
| ProDev | Master Card | Layout, BOP, BOM, tinta | Diturunkan engine, bukan diketik ulang | Satu sumber kebenaran untuk produksi dan costing |
| ProDev | Salinan di Inquiry (`iqp_layout`, `iqp_part_process/material/ink`) | Hasil layout, BOP, BOM, tinta | Salinan | Inquiry tetap bisa dibaca utuh walau master berubah |
| ProDev (layout Confirmed) | ProCost (`iqp_costing`, `_part`, `_price_tier`) | Plano, jumlah up, ukuran cetak, daftar proses | Dibaca saat costing dibuka; ukuran terkunci | Harga harus dihitung dari cara produksi yang sama dengan yang nanti dikerjakan |
| ProCost (published) | Quotation (`quo_quotation_item`) | Harga jual per jenjang qty, biaya sekali bayar (pisau, plat, film) | Salinan harga | Penawaran adalah janji; harga tidak boleh bergeser diam-diam |
| Quotation (WIN) | Customer Order (`co_order`, `co_order_item`) | Baris, qty, harga, diskon, toleransi, PPN, payment term | Salinan (snapshot) | Pesanan customer menjadi dokumen sendiri yang boleh menyimpang dari penawaran |
| Quotation (WIN) | Master Product (`prod_product`, `prod_part`) | Part, dieline, artwork luar-dalam, finishing | Disalin ke master, baris CO menunjuk produknya | Produk menjadi milik customer dan bisa dipesan ulang tanpa mengulang Inquiry |
| CO (Confirmed) | CPDEL (`co_order_delivery`) | Satu baris kirim per baris produk: qty penuh di tanggal due date | Dibuat otomatis, lalu berdiri sendiri | Jadwal kirim bisa dipecah dan dinegosiasi tanpa mengubah CO |
| CPDEL (Post to Production) | Work Order (`prd_wo`, `_operation`, `_material`) | Qty, tanggal slot, urutan proses, kebutuhan bahan | Dihitung saat Post dari spesifikasi ProDev yang berlaku | Produksi baru mulai setelah janji kirim jelas |
| CPDEL (sudah Posted) | Delivery Order (`do_delivery`, `do_line`) | Qty tersedia, harga CO | Baris DO menyalin harga CO | Surat Jalan harus bisa berdiri sendiri sebagai bukti |
| DO | Bukti Terima (`do_delivery_pod`, `do_line.qty_received`) | Jam tiba, hasil, qty diterima, alasan selisih | Dicatat di DO | Yang ditagih adalah yang benar-benar diterima customer |
| DO (qty diterima) | Invoice (`fin_invoice`, `_line`) | Qty diterima, harga, deskripsi, nomor Surat Jalan | Salinan; hanya `do_line.qty_invoiced` yang diubah balik | Tidak ada tagihan tanpa Surat Jalan yang divalidasi customer |

## 2. Alur data, stasiun demi stasiun

### 2.1 Inquiry → ProDev → Master Card

**Apa yang mengalir.** Inquiry menyimpan keinginan Sales. Dari ukuran yang diketik, Dieline Engine menggambar pola pisau dan menghitung bentangan; hasilnya disimpan sebagai spesifikasi dieline di part Inquiry. Saat Sales menekan Confirm, Inquiry tidak "dikirim"; ia otomatis muncul di meja ProDev lewat tanda tahap (bagian 3).

**Dari mana ke mana.** `iqp_part` (milik Inquiry, di database penjualan) ke `prod_part` (master, di database master). Saat ProDev membuka sebuah Inquiry, sistem membuat Master Product berstatus draft dan part masternya bila belum ada (`InquiryStage::openInProdev`, `InquiryWriter::sendToProDev`), lalu menautkan keduanya.

**Salin atau tautan.** Tautan di awal, lalu ProDev menulis hasilnya ke dua tempat sekaligus: master (`prod_part_process`, `_material`, `_ink`, `prod_layout_group`) dan salinan Inquiry (`iqp_part_*`, `iqp_layout`). Spec Engine membaca aturan BOP dan master bahan, lalu menyarankan; petugas memutuskan.

**Siapa melihat.** Sales melihat spesifikasi; ProDev melihat spesifikasi dan hasil engine. Belum ada harga di tahap ini.

**Kenapa.** Karena yang diminta customer ("karton 20 x 10 x 5, laminasi doff") dan cara membuatnya (plano, susunan, urutan mesin) adalah dua hal berbeda yang dikerjakan dua orang berbeda. Engine menurunkan turunan dari satu sumber supaya tidak ada tabel turunan yang diisi tangan.

**Master Card.** Ada dua hal bernama Master Card di kode; jangan tertukar. (a) **Master Card hasil ProDev**: lembar A4 yang dirangkai dari layout, BOM, tinta, dan BOP; kodenya muncul saat layout pertama disimpan. (b) **Master Card Collection** (`prod_master_card`): kartu lama dari Excel atau foto yang diimpor, lalu diubah menjadi Master Product atau Inquiry (`MasterCardService::makeProduct/makeInquiry`), jadi arahnya terbalik dari hulu. Detail pola kode MC pada (a) belum terverifikasi sepenuhnya: model part masih menyebut fallback sementara (`ProdPart::displayMastercardCode`).

### 2.2 ProDev → ProCost

ProCost Mode terintegrasi membaca layout ProDev yang **Confirmed** (bila belum ada, layout draft terbaru dipakai, dan ini ditandai di layar). Ukuran plano, jumlah up, dan ukuran cetak terisi otomatis dan terkunci; petugas hanya mengisi yang memang bukan dari ProDev (pilihan kertas, ongkos proses, coating). Tautan ini dibaca saat costing dibuka, bukan salinan permanen. Ini sebabnya HPP dari jalur terintegrasi dan jalur manual harus sama untuk geometri yang sama: keduanya hanya dua pintu masuk ke satu Costing Engine. **Siapa melihat:** hanya Costing dan Direksi (bagian 4). **Kenapa:** harga yang jujur harus dihitung dari cara produksi yang akan benar-benar dipakai, bukan dari tebakan Sales.

### 2.3 ProCost → Quotation

Hanya Inquiry yang costing-nya **published** boleh ditawarkan (`QuotationBuilder::availableInquiries`). Untuk setiap jenjang qty, sistem menyalin **harga jual per pcs** (bukan HPP) ke baris Quotation, ditambah biaya sekali bayar yang dipecah per jenis. Setelah itu Quotation tidak lagi tersambung ke Costing: Sales boleh mengubah harga, qty, dan diskon di Draft, dan perubahan di ProCost sesudahnya tidak menyentuh Quotation yang sudah jalan. **Kenapa:** aturan A.1 menjaga agar tidak ada penawaran yang melompati costing; sifat salinan menjaga agar penawaran yang sudah terkirim tidak berubah sendiri.

### 2.4 Quotation → Customer Order, dan kelahiran Master Product

CO lahir **saat Sales menekan WIN** (`Quotation::win` memanggil `CoOrderBuilder::findOrCreateFromQuotation`), bukan otomatis saat Quotation di-Publish. Semua baris disalin; toleransi cetak dari jenjang ProCost ikut dibekukan sebagai batas qty minimum dan maksimum. Flag PPN diambil dari Inquiry asal (atau profil pajak customer). Pada momen yang sama, `ProductMasterSync` menyalin setiap Inquiry menjadi data pokok Master Product (part, dieline, artwork, finishing) dan baris CO diberi `product_id`. Untuk Rekalkulasi, tidak ada part yang disalin ulang karena produknya sama.

Jalur kedua, **CO Instant**, memulai dari Master Product langsung; harga, satuan, dan toleransi disalin dari master saat baris dibuat. **Kenapa dua pintu:** Quotation wajib terintegrasi (A.1), tetapi CO boleh langsung (A.2) karena sistem lama bekerja begitu dan Direksi kadang meminta percepatan. Satu Quotation hanya punya satu CO (A.4).

### 2.5 CO → CPDEL → Work Order

Saat CO benar-benar Confirmed (setelah approval, bagian 3), `CoOrderBuilder::confirm` membuat satu baris `co_order_delivery` per baris produk: qty penuh pada due date. Baris yang sudah dijadwalkan manual tidak ditimpa. Tombol **Post to Production** pada baris kirim memanggil `WoGenerationService::generateFromDelivery`: satu part = satu Work Order, dengan proses dan bahan dari Spec Engine untuk qty tersebut, tanggal jatuh tempo mengikuti tanggal slot. Produk tanpa Inquiry atau part mendapat WO sederhana. **Salin atau tautan:** WO menyimpan penunjuk ke CO, baris kirim, dan part Inquiry (`source_co_id`, `source_delivery_id`, `source_iqp_part_id`), tetapi isi proses dan bahannya dihitung pada saat Post, bukan dibekukan saat CO Confirm (lihat celah di bagian 6).

### 2.6 CPDEL → Delivery Order → Bukti Terima → Invoice

DO hanya menarik baris kirim yang CO-nya Confirmed, sudah **Posted**, dan masih ada sisa qty. Baris DO **menyalin harga** dari CO; bila petugas mengubahnya, harga asli dicatat dan DO ditandai "ada perubahan harga". Setelah DO Confirmed lalu Shipped, baris kirim CPDEL otomatis berstatus Shipped bila qty terkirim mencukupi. Petugas pengiriman mencatat bukti terima (jam tiba, hasil, qty yang diterima, alasan selisih). Invoice dibuat **dari baris DO yang sudah punya qty diterima** (`CustomerInvoiceService::createInvoice`): qty ditagih = diterima dikurangi yang sudah ditagih; harga dan deskripsi disalin dari baris DO. Saat invoice di-Confirm: nomor terbit dari seri (Non PPN atau PPN), jurnal piutang otomatis, dan `do_line.qty_invoiced` bertambah sehingga baris yang sama tidak bisa ditagih dua kali. Penerimaan uang customer (`receivePayment`) menutup piutang per invoice. **Kenapa:** customer bisa menolak atau menerima sebagian, barang bisa rusak di jalan; yang tidak diterima tidak ditagih (A.21).

### 2.7 Titik snapshot beku, dan mengapa

| Titik | Yang dibekukan | Master berubah, dokumen lama... |
|---|---|---|
| Quotation dibuat dari Inquiry | Harga jual per jenjang, biaya sekali bayar | tetap |
| WIN → CO | Baris, harga, diskon, toleransi, PPN, payment term | tetap |
| WIN → Master Product | Spesifikasi Inquiry menjadi data pokok produk | produk berubah hanya lewat Revisi atau edit ProDev |
| CPDEL → DO | Harga baris | tetap |
| DO → Invoice | Qty diterima, harga, nomor Surat Jalan | tetap |

Prinsipnya (aturan A.13): transaksi membekukan nilai master pada momen yang penting. Kenapa: tagihan Januari tidak boleh berubah karena harga bahan Februari naik, dan penawaran yang sudah diterima customer adalah janji hukum.

## 3. Alur sistem: tanda, status, approval, engine

**Tiga tanda tahap di satu tabel.** `iqp_inquiry` menyimpan `stat_confirm_sales`, `stat_confirm_prodev`, `stat_confirm_procost` beserta siapa dan kapan (A.20). Tanda inilah yang menggerakkan dokumen: Sales Confirm membuat Inquiry New atau Revise muncul di meja ProDev dan Recalc langsung di meja ProCost; tanda ProDev menyala hanya setelah **semua part** Confirmed (`InquiryStage::syncProdev`); tanda ProCost menyala saat costing published (`syncProcost`). Grid tiap bagian hanyalah saringan jenis dokumen dan tanda. **Kenapa satu tabel:** tidak ada kopi dokumen antar bagian, jadi tidak ada versi yang berbeda.

**Status dokumen.**

| Dokumen | Alur status (menurut kode) |
|---|---|
| Costing | Draft → Calculated → Pending approval → Published |
| Quotation | Draft → Published (tombol Confirm) → Won / Lost / Cancelled |
| Customer Order | Draft → Submitted → Confirmed; Rejected; Cancelled |
| Baris CPDEL | Planned → Shipped, ditambah penanda Posted |
| Work Order | Draft saat lahir, lalu alur produksi |
| Delivery Order | Draft → Confirmed → Shipped; Cancelled |
| Invoice | Draft → Posted (terbit nomor); Cancelled |

**Approval, siapa memutuskan apa.**

- **Publish costing:** hanya admin atau grup Direksi (`costing.blade.php::canPublish`). Ini bukan hanya persetujuan; ini deklasifikasi harga ke Sales.
- **Customer Order:** Confirm berarti diajukan ke Flow Engine modul CO. Langkah F&A SPV lalu Operational Manager terbaca dari migrasi; nilai yang dikirim adalah piutang bersih CO. Approve, Reject (wajib alasan, final), atau Hold. Hanya setelah approval terakhir CO menjadi Confirmed dan CPDEL lahir.
- **Janji kirim:** Sales memegang slot yang dijanjikan ke customer; PPIC memegang kapan barang siap; bila bentrok dan customer menolak, Operational Manager memutuskan (lembur, prioritas, subkon, atau tawaran baru).
- **Post to Production:** dilakukan PPIC pada baris yang sudah Confirmed dan approved.

**Engine yang benar-benar ada di kode untuk rantai ini** (dari `EngineRegistry`):

| Engine | Titik kerja | Membaca → menulis |
|---|---|---|
| `dieline` | Form Inquiry, ProDev tab Part & Dieline | Resep dieline jenis produk (`prod_type.dieline_spec`) → gambar dan bentangan; tidak menulis sendiri, layar yang menyimpan |
| `imposition` | ProDev tab Imposition | Ukuran blank dan master plano → saran susunan; hasil dipilih dan disimpan layar ke `prod_layout_group` |
| `spec` | ProDev, pembuatan WO | Aturan BOP, proses, bahan, layout → BOP, BOM, tinta di `prod_part_*` |
| `routing`, `usage-rate` | ProDev | Template urutan proses; pembelajaran pemakaian bahan bulanan → usulan ke `prod_bop_rule` |
| `costing` | ProCost | Layout, part, konstanta Costing → `iqp_costing`, `_part`, `_price_tier` |
| `reverse-costing` | Tombol Reverse di Pro-Cost Instant | Costing instant → membuat Inquiry, produk, part, dan layout draft setelahnya |
| `flow` | Approval CO | Konfigurasi alur → log approval (`flow_log`) |
| `cpdel` | CO Confirmed | `co_order`, `co_order_item` → `co_order_delivery` |
| `cpdel-promise` | Layar CPDEL | Baris kirim, jam kerja, jam terima customer, hari libur → status janji dan riwayat negosiasi |
| `wo-generation` | Post to Production | Baris kirim, part Inquiry, master part → `prd_wo` dan turunannya |
| `tax` | Keuangan bulanan | Skema pajak dan buku besar → register pajak; ini hilir invoice, bukan penggerak dokumen Sales |

Engine **menyarankan, petugas memutuskan** (A.7): tidak ada engine di atas yang mengubah dokumen tanpa tindakan orang, kecuali `cpdel` dan `wo-generation` yang membuat dokumen turunan pada saat tombol ditekan atau approval selesai. Catatan: pembuatan invoice, DO, dan Quotation dikerjakan layanan biasa (`CustomerInvoiceService`, `DispatchService`, `QuotationBuilder`), bukan engine terdaftar.

## 4. Informasi rahasia dan penyaringannya

**Sisi A dan Sisi B.** Sisi A = rincian biaya, HPP, margin, rumus. Hanya Costing dan Direksi, dan rumus hanya untuk login yang tercantum di konstanta `PROCOST_FORMULA_LOGINS`. Sisi B = harga jual per jenjang qty; baru terbuka ke Sales setelah costing **published**. Sebelum itu, `TierPricingResolver` mengembalikan kolom harga kosong, jadi Nota Inquiry dan Quotation tidak pernah menerima angkanya. Penyaringan dilakukan di server, bukan hanya disembunyikan di tampilan.

**Harga jual di dokumen sesudahnya.** Hak melihat harga ditentukan `SecUser::canViewSalesPrice`: admin, grup Sales (staff, SPV, manajer), Marketing, Costing, Operational Manager, Direksi, atau level manajemen 4 ke atas. Produksi dan Purchasing melihat order tanpa harga: grid laporan CO membuang kolom harga (`PRICE_KEYS`) dari data sebelum dikirim ke layar bila pemakai tidak berhak. Surat Jalan dan cetakan CPDEL dicetak tanpa harga.

**Kenapa.** HPP dan margin adalah posisi tawar perusahaan terhadap customer dan terhadap bagian lain; harga jual penting bagi Sales tetapi tidak perlu diketahui operator. Menyaring di server menjaga agar rahasia tidak bocor lewat jaringan, ekspor, atau cetakan.

Yang belum saya telusuri: apakah **semua** layar Produksi dan Purchasing sudah melewati penyaringan yang sama; saya hanya memverifikasi kelompok grid laporan CO dan `Crm::canSeePrice` (belum terverifikasi untuk layar lain).

## 5. Bila hulu berubah

| Kejadian | Yang ikut berubah | Yang tidak berubah |
|---|---|---|
| **Revisi** (spesifikasi berubah) | Inquiry baru jenis Revise dengan catatan sebelum → sesudah (`iqp_revise_change`) yang tampil di ProDev dan ProCost; saat WIN lahir **produk turunan** yang mencatat induknya | Produk lama, Quotation lama, dan CO lama tetap dengan snapshot-nya |
| **Rekalkulasi** (spesifikasi sama, harga bahan berubah) | Costing baru (harga versi baru) lewat Inquiry Recalc; ProDev dilewati | Produk dan part tidak disalin ulang; Quotation dan CO lama tidak berubah otomatis |
| **Edit ProDev sesudah costing published** | Dieline atau ukuran tersimpan ke inquiry dan master; layar memberi peringatan | Harga jual tidak berubah sendiri; minta ProCost menghitung ulang |
| **Quotation Cancel** (hanya yang masih Published) | Inquiry-nya dilepas sehingga boleh ditawarkan lagi | Costing published tetap |
| **Quotation Lost** (alasan wajib) | Inquiry dalam quotation itu ditandai Lost | Costing dan produk draft tetap tercatat |
| **CO Rejected / Cancelled** | CO Cancelled juga membatalkan baris kirim; hanya bila belum ada WO dan DO hidup | Quotation tetap Won, Inquiry tetap Converted, Master Product tetap; pesan ulang lewat CO Instant (Duplicate) karena satu Quotation hanya satu CO |
| **Jadwal dipecah atau digabung** | Baris CPDEL; janji awal ke customer tetap terekam, perubahan masuk riwayat negosiasi | CO |
| **DO dibatalkan** | Qty kembali tersedia untuk ditarik lagi | CPDEL dan CO |
| **Invoice dibatalkan** | Jurnal dibalik (`CustomerInvoiceService::cancel`) | Surat Jalan dan bukti terima |

## 6. Di mana alur ini putus atau belum tersambung (menurut kode)

1. **Due date CO 14 hari kerja dari approval proof fisik: tidak ada di kode.** `CoOrderBuilder` tidak menghitungnya dan tidak ada kolom tanggal approval proof di alur CO; due date diisi tangan. Dokumen juga bertentangan: A.10 menyebut approval proof fisik, `flow_product_master.md` §7 menyebut tanggal CO + 14 hari kerja. Sampai Jo memilih, kode tidak mengikuti keduanya.
2. **WO tidak membekukan spesifikasi saat CO Confirm.** `WoGenerationService::generateFromDelivery` membaca part Inquiry dan Spec Engine pada saat Post. Bila ProDev mengubah BOP atau BOM antara CO Confirm dan Post, WO mengikuti yang baru. Untuk CO Instant, sumbernya "Inquiry terbaru yang tidak dibatalkan untuk produk itu" (`orderByDesc id_inquiry`), yang belum tentu versi yang dimaksud customer.
3. **Batas 30 menit jalan pintas tidak ditegakkan.** `QUICK_JOB_MINUTES` (`Cpdel.php`) hanya muncul sebagai keterangan; yang menjadi gerbang adalah status Posted. Hasil produksi tetap wajib dicatat, dan ada laporan Shipped Without Production Output sebagai pengawas.
4. **Tidak menemukan pengurangan stok barang jadi saat DO Shipped.** `DispatchService` tidak menyebut stok, dan penelusuran saya di layanan inventory tidak menemukan pemicu dari DO (belum terverifikasi: bisa ada jalur di layar yang tidak saya cari). Bila memang tidak ada, stok barang jadi dan Surat Jalan tidak tersambung.
5. **Costing published tidak punya jalur batal yang saya temukan.** `InquiryStage::syncProcost` mampu mengembalikan tanda ke 0 bila publish dicabut, tetapi layar costing (`costing.blade.php`) hanya punya aksi publish satu arah (belum terverifikasi untuk layar lain). Artinya koreksi harga pasca-publish praktis lewat Rekalkulasi.
6. **Quotation tidak tahu bila costing asalnya berubah.** Harga tersalin; tidak ada peringatan "harga di ProCost sekarang berbeda". Itu disengaja oleh prinsip snapshot, tetapi tidak ada layar yang membandingkan.
7. **Baris biaya sekali bayar (pisau, plat, film) menjadi baris Product di CO** bila berasal dari Inquiry (hanya baris bebas yang menjadi Service). Dampaknya (butuh due date, ikut dijadwalkan kirim, ikut pemeriksaan "pilih satu qty") terbaca dari kode, belum saya uji.
8. **Retur sesudah invoice (credit note) belum ada**; rulebook A.21 menyebut menunggu wawancara pemakai. Alasan selisih di bukti terima hanya mencegah penagihan sejak awal.
9. **`claude_vital_flows.md` masih menulis CO lahir otomatis begitu Quotation Published.** Kode: CO lahir saat WIN. Dokumen itu perlu diperbarui.
10. **Penautan `parent_product_id` pada Inquiry** disebut punya dua arti di `flow_product_master.md` §8; di `IqpInquiry` sekarang terpakai `parent_inquiry_id` dan `product_id`, jadi catatan itu mungkin sudah usang (belum terverifikasi lewat skema).
11. **Back process untuk CO percepatan Direksi belum diputuskan** (A.20): CO langsung bisa lewat Post to Production tanpa Inquiry, ProDev, atau ProCost menyusul; tidak ada penanda atau penahan penutupan CO.

## 7. Catatan untuk Jo: yang tidak bisa saya pastikan

- Saya **tidak menjalankan** sistem atau tes; semua dari membaca kode.
- Isi tabel konfigurasi approval (`flow_config`) di mockup.test maupun tmerp.test tidak saya buka; urutan F&A SPV lalu Operational Manager terbaca dari migrasi.
- Nilai konstanta (`QUICK_JOB_MINUTES`, `CPDEL_DUE_SOON_DAYS`, `PROCOST_FORMULA_LOGINS`, tarif pajak) saya baca sebagai bawaan; nilai live bisa sudah berbeda.
- Pola kode Master Card dari ProDev: artikel `02_prodev.md` menyebut `MC-<nomor layout>`, model part memakai fallback `MC-<id part>`, dan ada `mastercard_code` di `prod_layout_group`. Saya tidak memastikan mana yang tampil di layar akhir.
- Saya tidak menelusuri jalur Subkon (SUBCON), Purchasing, atau pembayaran uang muka dalam detail; hanya garis besar rantai Sales sampai penagihan.
- Keputusan yang butuh Jo: (a) aturan due date (poin 1), (b) apakah WO perlu dibekukan saat CO Confirm (poin 2), (c) apakah batas 30 menit perlu ditegakkan (poin 3), (d) apakah DO Shipped harus menggerakkan stok barang jadi (poin 4).
