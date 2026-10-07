# PEDOMAN ATURAN USER — ERP CV. Telasih Makmur (v2)

> **RELEASE 1 Oktober 2026** · semua aturan & keputusan yang diberikan user (pemilik sistem), dikumpulkan
> menjadi satu. **WAJIB DIBACA dan DIJADIKAN PEDOMAN** oleh semua developer/agen (Claude, Jo, Iwan)
> sebelum mengerjakan apa pun. Bila bertentangan dengan dokumen lain, dokumen ini yang menang, kecuali
> user memberi keputusan yang lebih baru.
>
> Aturan baru sesudah release ini ditambahkan di bagian **Changelog** (paling bawah) dengan tanggalnya,
> lalu dimasukkan ke bagian yang sesuai. Aturan tidak dihapus diam-diam; yang dicabut ditandai ~~coret~~
> beserta tanggal dan alasannya.
>
> Tanda: **[LOCKED]** = tidak boleh diubah tanpa perintah eksplisit user.

---

## A. Alur bisnis (dokumen & proses)

1. **[LOCKED 2026-10-01] Inquiry → Quotation WAJIB integrated.** Setiap baris quotation berasal dari inquiry
   yang costing-nya sudah PUBLISHED (lewat ProDev + ProCost). Tidak ada quotation yang melompati costing,
   termasuk data dummy/seeder. Tidak boleh ada jalur "quotation direct".
2. **[LOCKED 2026-10-01] Customer Order BOLEH direct tanpa Quotation** (CO INSTANT, baris dari Master Product).
   Alasan: kompatibel dengan sistem lama (ScriptCase V1), dan sesuai realita di mana Direksi bisa minta
   percepatan yang langsung dikerjakan Produksi, sehingga jalur tercepatnya adalah input langsung di CO.
   Jalur INSTANT tidak boleh dihapus atau dipersulit.
3. **[LOCKED] Alur utama:** Inquiry (Baru / Revisi / Rekalkulasi) → ProDev → ProCost → Quotation → WIN →
   Customer Order → CPDEL → **POST ke Produksi** → Delivery Order / Surat Jalan. Pedoman rinci: `docs/flow_product_master.md`.
   **[2026-10-01] Jalan pintas pekerjaan cepat:** setelah CPDEL di-POST ke Produksi, DO dan DN (Surat Jalan) **boleh
   langsung dibuat** tanpa menunggu produksi selesai, untuk pekerjaan yang pengerjaannya hanya sekitar 30 menit.
   - Setiap baris CPDEL yang sudah **Confirmed** punya tombol **Post to Production**.
   - Sistem **tidak tahu** lama pengerjaan sampai hasil produksi di-input, jadi jalan pintas tidak diblokir oleh
     perkiraan sistem. Batas "30 menit" **bukan angka mati**: bisa berubah mengikuti data yang ada (disimpan sebagai
     nilai yang bisa diubah user, bukan hardcode).
   - **Hasil produksi TETAP WAJIB di-input** walaupun barang sudah dikirim lewat jalan pintas. Data hasil produksi
     menjadi **acuan KPI semua departemen**, jadi tidak boleh ada pekerjaan yang terkirim tanpa catatan hasil produksi.
4. **[LOCKED] 1 Quotation = 1 Customer Order.** Quotation berisi banyak inquiry; 1 inquiry = 1 product.
   Quotation WIN = Master Product lahir. Revisi = product turunan; Rekalkulasi = versi harga bertanggal.
5. **[LOCKED] Inquiry punya 3 jenis dokumen:** BARU, REVISI (spec berubah), REKALKULASI (spec sama, harga bahan berubah).
6. **[LOCKED] ProDev & ProCost punya 2 mode:** Mode #1 terintegrasi (dari Inquiry / layout ProDev CONFIRMED) dan
   Mode #2 manual/standalone. Mode #2 itu SAH (bukan gap). **HPP kedua mode HARUS SAMA** untuk geometri setara.
7. **Engine menyarankan, petugas memutuskan** (scheduling, imposition, routing, target kecepatan).
8. **Costing rahasia:** HPP/margin hanya untuk Costing + Direksi. Publish = harga boleh dilihat Sales.
   Rumus ProCost hanya terlihat untuk login `johiwan`.
9. **Harga jual hanya untuk** Sales, Marketing, Product Costing, Operational Manager ke atas. Produksi,
   Purchasing, dan lainnya melihat order TANPA harga; harga disaring di server, bukan cuma disembunyikan.
10. **Due date CO** = 14 hari kerja dari approval proof FISIK (bisa diubah). CO tidak bisa di-Confirm bila ada
    baris PRODUCT tanpa due date.
11. **CO Confirm = diajukan ke approval** (F&A SPV → Operational Manager). CPDEL baru dibuat setelah approval terakhir.
    PR = Operational Manager (1 level); PO = Product Costing → Operational Manager.
12. **Setiap modul wajib jelas siklus hidupnya:** kapan Confirmed, kapan Cancelled, kapan Closed. Tidak ada
    dokumen "mengambang".
13. **Snapshot:** transaksi membekukan nilai master saat momen penting; master berubah, transaksi lama tetap.
14. **ACC Digital artwork wajib** sebelum produksi.
15. **[2026-10-01] Customer memilih SATU qty per product di CO.** Quotation boleh menawarkan beberapa pilihan qty (tier) untuk
    product yang sama; saat menjadi CO, customer memilih salah satunya (tombol **Choose** di CO draft) dan pilihan qty lain
    dibuang dari CO. CO tidak bisa di-Confirm selama satu product masih punya lebih dari satu baris qty.
16. ~~**[2026-10-01] Work Order hanya untuk product yang punya Inquiry dengan costing sudah di-publish**, juga untuk CO direct
    (INSTANT). Tidak ada jalan pintas di engine.~~ **Dicabut user 2026-10-02:** software lama tidak punya Inquiry maupun Post to
    Production (koordinasi verbal), dan percepatan Direksi masuk lewat CO langsung.
    **[2026-10-02] SEMUA jenis CO boleh di-Post to Production, asal CO-nya sudah Confirmed dan approved.** Costing published
    tidak lagi disyaratkan. Product yang punya Inquiry + part: WO per part dengan routing dan bahan seperti biasa. Product
    tanpa Inquiry (CO direct): **WO sederhana**, satu per baris kirim, tanpa routing dan bahan — hasil produksi tetap bisa
    dicatat (A.3). Baris CO wajib punya Master Product.
17. **[2026-10-01] ProDev berhak mengubah dieline & mockup part.** Form ProDev (v2) punya tab **Part & Dieline** — panel
    yang sama dengan Inquiry Product (gambar dieline, kotak 3D, Dimensions, Flat Size, artwork luar/dalam) — dan ProDev boleh
    **mengubah dimensi, bentangan (Flat Size), dan artwork** lalu **Save**, walaupun inquiry-nya sudah Confirmed.
    - **Simpan dua arah** (pola Save ProDev): snapshot inquiry (`iqp_part`: `dieline_spec`, `flat_w/h/area`, artwork) **dan**
      master (`prod_part`: `dieline_spec`, `flat_*`, `cm_length/width/height`). Part manual (Mode #2, tanpa Inquiry) hanya arah master.
    - Imposition di tab sebelahnya langsung memakai ukuran blank yang baru.
    - **Tidak diblokir, tapi diberi peringatan sebelum Save** bila: layout part sudah Confirmed (susun ulang + Confirm lagi),
      costing sudah published (harga jual tidak ikut berubah, minta ProCost hitung ulang), atau product sudah aktif di Master
      Product (master ikut berubah; artwork baru = versi baru yang wajib **ACC Digital** lagi, A.14).
    - Yang **tetap milik Inquiry** (tampil saja di ProDev): nama part, Tipe Part, Qty per Set, isi per pack, dan **Tipe Produk
      (jenis dieline)**. Bahan, warna, finishing diatur ProDev lewat BOM / Ink / BOP seperti biasa.
18. **[2026-10-01] Batas canvas ProDev = pilihan officer ("Canvas limit"), bawaan Custom mengikuti dieline.** Di form ProDev v2,
    panel *3. Lembar Cetak / Canvas* punya pilihan:
    - **Custom (follow dieline)** — bawaan part yang belum punya layout: canvas = bentang dieline × jumlah up + gap + margin &
      gripper, **tanpa batas meja** dan tanpa minimum 25 × 20 cm; jumlah up bawaan **1** dan boleh dinaikkan; Length / Width tetap
      bisa diketik.
    - **Press bed** = batas meja mesin cetak terpilih; **Die-cut bed (Pond)** = batas meja mesin pond terpilih (selama ukuran
      mejanya kosong di Master Mesin: hanya peringatan, tanpa batas); **Press + Die-cut** = terkecil dari keduanya.
    - Aturan 16-Sep-2026 ("canvas wajib muat di meja cetak **dan** meja plong") **tidak lagi otomatis**: berlaku bila officer
      memilih *Press + Die-cut*. Layout lama (tanpa catatan pilihan) dibuka sebagai *Press + Die-cut*.
    - Bila dieline 1 up tidak muat di meja terpilih, layar menulis ukuran yang dibutuhkan dan **menyarankan** part dipecah
      kiri / kanan (Double Join). Hanya saran; struktur part tetap diubah di Inquiry / Master Product.
    - Pilihan tersimpan bersama layout (`layout_detail.canvas_limit`), tanpa kolom baru.
19. **Satuan ukuran bentangan:** `iqp_part.flat_w/flat_h` dan `prod_part.flat_w/flat_h` = **milimeter**; kolom teknis ProDev
    (`die_w_cm`, `printsize_*`, canvas, plano) = **cm**. Setiap pemakaian silang wajib dikonversi (kejadian 2026-10-01: flat
    dibaca sebagai cm sehingga blank jadi "250 × 161 cm" dan BOM / Ink / BOP tidak pernah terhitung).
15. **Scope modul (2026-10-02):** satu modul = satu menu besar dengan saklar `activate` sendiri (mati = menu + route tertutup,
    data tetap). Antar modul hanya lewat kontrak (snapshot + status akhir): A = Quotation published/WIN -> Customer Order,
    B = CO Confirmed + Post to Production -> Production. CO direct (INSTANT) selalu tersedia walau modul pre-order mati.
    Modul Inquiry & Quotation = menu `PREORDER` (izin masih memakai modul `SALES`).
    **Menu = data (cara ScriptCase):** layar tidak tahu ada di menu mana; menduplikasi menu = menambah satu baris
    `sys_app_menu` ber-`target` sama (mis. Master Customer di menu PREORDER, izin `MDATA`) — tanpa kode, tanpa salinan layar.
    Layar terbuka selama masih ada satu baris menu aktif (beserta induknya) yang menunjuk ke situ (`AppMenu::open`).
    Modul / grup / item baru, termasuk grup Flow, ditambah lewat baris data, bukan kode.
    **Satu modul = lengkap sendiri:** semua layar yang dipakai modul (transaksi, master penyerta, laporan, alur) wajib bisa
    dibuka dari menu modul itu sendiri — bila layarnya milik modul lain, tambahkan baris pintasan (izin tetap modul pemilik).

20. **[2026-10-02] Alur Inquiry → Pro-Dev → Pro-Cost lewat tiga tanda di SATU tabel (`iqp_inquiry`), menu dipisah per jenis.**
    - Jenis dokumen tetap tiga (New, Revise, Recalc) di satu tabel fisik; **menunya dipisah** supaya officer tidak memilah sendiri:
      Inquiry Product › All / New / Revise / Recalc, Pro-Dev › New / Revise / Manual, Pro-Cost › New / Revise / Recalc.
      Tombol **+ New Record di tiap submenu langsung membuka jenisnya sendiri** (tanpa pop-up pilih jenis).
    - Tiga tanda: `stat_confirm_sales` (dulu `stat_confirm`), `stat_confirm_prodev`, `stat_confirm_procost`, masing-masing
      dengan siapa (`confirm_*_by`) dan kapan (`confirm_*_at`). **Setiap grid = saringan jenis AND tanda** atas tabel yang sama:
      | Grid | Jenis | Tanda |
      |---|---|---|
      | Sales (draft) | sesuai menu | `sales = 0` |
      | Pro-Dev New / Revise | New / Revise | `sales = 1` AND `prodev = 0` (To Do), `prodev = 1` (Done) |
      | Pro-Cost New / Revise | New / Revise | `prodev = 1` AND `procost = 0` (To Do), `procost = 1` (Done) |
      | Pro-Cost Recalc | Recalc | `sales = 1` AND `procost = 0` (To Do), `procost = 1` (Done) |
      | Sales siap Quotation | semua | `procost = 1` |
      Semua grid kerja juga AND `stat_cancel = 0` (dan bukan LOST).
    - **New dan Revise:** Inquiry → Pro-Dev → Pro-Cost. **Recalc: Inquiry → Pro-Cost langsung** (spec sama). Recalc tetap dibuat
      dan di-Confirm Sales lebih dulu supaya **tercatat Sales siapa (dasar komisi)**.
    - **Confirm Sales = otomatis muncul di grid Pro-Dev.** Tombol "Kirim ke ProDev" di form Sales ditiadakan; part master
      dibuat saat officer Pro-Dev membuka inquiry itu dari gridnya.
    - **Pro-Dev boleh Confirm part demi part; `stat_confirm_prodev` baru menjadi 1 setelah SEMUA part Confirmed.**
      `stat_confirm_procost` = 1 saat costing published (kembali 0 bila publish dibatalkan).
    - Grid Pro-Dev dan Pro-Cost = objek grid yang sama (`InquiryDeskGrid`), hanya beda saringan. Part tanpa Inquiry
      (Mode #2) ada di **Pro-Dev › Manual**.
    - **Repeat order tanpa perubahan apa pun** tidak lewat Inquiry: langsung Customer Order direct (A.2).
    - **Belum diputuskan (butuh solusi, user 2026-10-02):** CO direct karena **percepatan Direksi** (langsung CO → Post to
      Production → kirim) menyisakan "back process" (Inquiry / Pro-Dev / Pro-Cost menyusul). Usulan: alasan CO direct wajib
      dipilih (Repeat / Percepatan), inquiry susulan otomatis bertanda Back Process, CO tidak bisa Closed sebelum selesai.
      Karena itu kode jenis order di laporan CO (RTP = Recalc) belum diubah.
21. **[2026-10-02] Customer Invoice + Piutang (AR) = satu modul; ketentuan keuangan dan akuntansinya di
    `docs/md_for_systems/customerinvoice_accounting.md`** (rencana layar dan tabel: `customerinvoice_plan.md`).
    Disetujui user; terpasang di mockup.test dan tmerp.test (2026-10-02, `customerinvoice_review.md`):
    - **Dua seri nomor invoice**, satu per identitas: Non PPN (Telasih Makmur) `275/TM/IX/2026`, PPN (CV. Langgeng Jaya
      Makmur) `118/LJM/IX/2026`. Format lama dan urut ulang bulanan tetap; **nomor terbit otomatis saat Confirm**, tidak
      diketik, tidak dipakai ulang; format dan kode = data di master seri nomor.
    - **Tagihan uang muka (DP) tidak menjurnal** dan bukan piutang. Jurnal lahir saat uang muka diterima (Bank pada
      Uang Muka Penjualan). Menggantikan butir LOCKED lama di `downpayment_tier1-4.md`.
    - **PPN uang muka mengikuti aturan pajak**: Faktur Pajak saat uang muka diterima, invoice akhir memuat PPN sisanya.
      Setelan cadangan `DP_VAT_ON_RECEIPT` untuk kembali ke cara lama. Dikonfirmasi konsultan pajak sebelum dipakai di
      tmerp.test.
    - **Nilai invoice selalu penuh**; uang muka tampil sebagai potongan dan "sisa yang harus dibayar" — tidak lagi
      diketik di kolom Diskon.
    - **Dasar tagih = qty yang diterima customer** (Surat Jalan kembali), dikurangi yang sudah ditagih.
    - **Qty Minim = harga borongan**: nilai baris = harga, bukan qty × harga (Customer Order, Delivery Order, invoice).
    - **Lima rekening bank sistem lama** boleh disalin ke master rekening perusahaan; bawaan PPN = Maspion CV. Langgeng
      Jaya Makmur, Non PPN = BCA Puspita Dewi Widodo.
    - **Setiap urusan keuangan dan akuntansi wajib terdokumentasi jelas** (jurnal, nomor, pajak, pengendalian) di
      dokumen di atas, dan diperbarui tiap ada keputusan baru.
    - **Setiap tagihan harus terkait Surat Jalan pengiriman DAN validasi customer atas yang terkirim** (user 2026-10-02:
      "karena bisa ada retur dan rusak di jalan"). Tidak ada invoice tanpa Surat Jalan; baris baru bisa ditagih sesudah
      Proof of Delivery; yang diterima kurang dari yang dikirim wajib beralasan (rusak di jalan, diretur, kurang kirim);
      yang tidak diterima tidak ditagih. Buktinya (penerima, jam tiba, foto Surat Jalan) tampil di invoice.
    - **Uang muka harus jelas dari Customer Order yang mana dan riwayatnya terlacak dan tertera jelas**: uang muka hanya
      dari satu Customer Order, hanya memotong invoice order itu, dan buku uang mukanya (ditagih → diterima → dipotong di
      invoice mana → sisa) tampil di uang muka, di Customer Order, di laporan, dan di cetakan invoice.
    - **Belum cukup wawancara pemakai untuk urusan invoicing** (user 2026-10-02: "sarankan yang terbaik dan jalankan
      dulu"): pilihan yang belum dipastikan dijalankan dengan bawaan terbaik, dicatat di `customerinvoice_review.md`
      bagian 10, dan harus mudah diubah. Retur sesudah invoice (Credit Note) menunggu wawancara itu.

21. **[2026-10-02] Sales = satu grup besar dengan tiga bagian: Inquiry & Quotation, Order, CRM.** Kata user: *Inquiry adalah
    tools dalam mencapai percepatan keluarnya harga jual; Order adalah pencatatnya; CRM adalah bangun hubungan dengan customer.*
    - **Menu:** Sales (modul) > grup kecil **Inquiry & Quotation** (baris `PREORDER`, kini grup di dalam Sales), **Order**,
      **Delivery**, **CRM**. Tiap grup kecil modular dan **bisa dilepas-pasang**: saklar `activate` di baris grupnya; mati = menu,
      layar, dan rute simpannya tertutup, data tetap. Ini menggantikan A.15 "Inquiry & Quotation = menu besar sendiri".
    - **Aktivitas Sales = satu tabel per customer** (`crm_activity`, perluasan `quo_activity`): customer wajib, quotation boleh
      kosong. Follow-up quotation dan kunjungan rutin tercatat di tempat yang sama; follow-up punya tanda selesai sendiri.
    - **Retur penjualan / komplain: informasinya ke Sales, mutunya ke QA.** Dokumen komplain (`crm_complaint`) milik Sales:
      Draft → Confirmed → Closed | Cancelled. Kategori mutu diteruskan ke QA; QA menjawab di tabelnya sendiri
      (`tm2prd.qc_complaint_review`, layar Production › Quality › Complaint Review) dan Sales baru bisa Close setelah QA menjawab.
    - **Customer 360 hanya membaca** (order baru + arsip sistem lama, quotation, kiriman, aktivitas, komplain); data customer
      tetap diubah di Master Customer. Nilai rupiah hanya untuk yang berhak melihat harga (A.9).
    - Peta lengkap + yang masih terbuka (tempat Delivery, izin per grup, retur masuk stok, target Sales): `docs/md_for_systems/sales_tier1.md`.
    - Status: dibangun di `ddt*` (mockup.test), **menunggu penilaian user**; menu `tm2sys` belum diubah.

22. **[2026-10-02] Inventory & Warehouse: gudang = gedung, area, bin; pindah stok lewat Stock Transfer; dead stock merah.**
    Kata user: *"ya saya tunggu warehouse mapping, lalu input moving item antar warehouse dan disana akan tertera capacity
    label dan barcode or QR code. Disana akan muncul warna merah untuk DEAD stock ... Facility for QR Code, Labelling, BUILD TOO."*
    - **Gudang = gedung nyata.** "Gedung itu pasti terpisah, A5, A6, A10 dan masih gedung yang lain." Semua gedung bisa
      didefinisikan user sendiri (Inventory > Warehouse > Setup > Buildings); ukuran awal = perkiraan (mis. 30 x 20 m),
      "nanti di-adjust oleh officer".
    - **Bentuk dan lantai:** software harus bisa dipakai perusahaan lain — gedung mudah ditambah dan bentuknya bisa selain
      kotak (contoh huruf L, dengan ukurannya). Gedung Telasih A5 / A6 / A10 berbentuk kotak dan punya 2 lantai; sistem
      harus bisa **2 sampai 3 lantai**.
    - **Area penyimpanan harus bisa ditambah** oleh user ("pastikan bisa ditambah area storage"), begitu juga bin.
    - **Pindah barang antar bin / antar gudang = dokumen Stock Transfer**; di sana tertera label kapasitas bin dan
      barcode / QR code-nya.
    - **Dead stock tampil MERAH** (peta, transfer, laporan, label). Arti dead stock = E.2 (stok tidak bergerak lebih dari
      batas bulan di Settings > Constants); **memindahkan stok tidak dihitung sebagai pergerakan**.
    - **Fasilitas QR code + label wajib ada**: label lokasi (bin) dan label item, QR + barcode, lacak lewat pindai.
    - **Denah sementara dibuat agen, tetap bisa diubah (user 2026-10-02):** "untuk ukuran dan foto untuk sementara denahnya
      kamu buat dulu tapi nanti bisa diubah oleh user atau officer warehouse". Ukuran gedung, letak area, dan bin awal =
      perkiraan agen, tidak menunggu sketsa atau foto; semuanya wajib tetap bisa diubah lewat layar (Buildings, Storage
      Areas, Bins, Atur Tata Letak di Warehouse Map).
    - Status: **terpasang di mockup.test dan tmerp.test** (user 2026-10-03: "apply to mockup.test and tmerp.test, make sure
      all connected to table and database"); data contoh hanya di mockup.test; menunggu penilaian user. Yang diputuskan
      agen sendiri dan menunggu koreksi: `docs/md_for_systems/warehouse_review.md` bagian 4 dan 9.4.
    - **Kapasitas = luas dan tinggi** (user 2026-10-02): tiap lantai (1, 2, 3) punya tinggi bersih; luas lantai x tinggi =
      volume. Bin: luas tapak x tinggi tumpuk. Kapasitas kg tetap dipakai; status bin memakai yang lebih penuh dari keduanya.
    - **Approval Stock Transfer**: untuk sementara di-skip, tetapi fasilitasnya ada dan bisa diaktifkan kapan saja
      (Setup > Setting Approval, modul TF). Saat aktif, stok baru pindah sesudah approval terakhir.
    - **Label palet wajib** dibuat setiap barang datang dan setiap isi satu palet habis dipakai (label ulang dengan qty
      sisa). **Label item dan label palet harus berbeda.** Setiap label: QR code + barcode + **tanggal tiba**; pita warna
      sebagai penanda; **kode palet dari supplier** ikut dicetak; **tag kedaluwarsa** untuk kertas dan tinta.
    - Ukuran label dijelaskan di layar supaya mudah dicetak. Label palet dan label item bisa dicetak langsung dari
      form Penerimaan Barang.

23. **[2026-10-02] Laporan stok dan gudang: set lengkap, mengacu ERP populer; selisih stok lewat stock opname.**
    Kata user: *"build all reports related with inventory movement, stock card and other reports"* — *"stockcard, summary
    all stocks, recapitulation of all items, inspection stocks, report items group by location warehouse, loss and gain all
    items every monthly"* — *"reference from all application about warehouse and stock, include refer to popular ERP like
    SAP, odoo, dynamics 365, zahir, accurate, Oracle and other apps"* — *"stock opname in indonesia, is most popular report
    to entry loss and gain"*.
    - Laporan stok wajib memuat: **Stock Card**, **ringkasan semua stok** (Stock Summary), **rekap semua item** (Stock
      Recapitulation), **stok inspeksi** (Stock in Inspection), **item per gudang / lokasi** (Stock by Location, terkelompok
      per gedung), dan **selisih lebih / kurang semua item per bulan** (Stock Loss & Gain).
    - **Selisih stok masuk lewat stock opname** (dokumen Stock Adjustment & Physical Count); laporan **Stock Opname**
      menampilkan qty sistem, qty fisik, dan selisihnya, dan rekap bulanannya = Stock Loss & Gain.
    - Daftar laporan gudang dan stok **mengacu ERP populer**; acuan + sumbernya di
      `docs/md_for_systems/inventoryreport_references.md`. Yang tidak bisa diverifikasi tidak dijadikan dasar.
    - Laporan semua kelas ada di Inventory > Warehouse > Report; laporan per kelas tetap di menu kelasnya.
    - Status: terpasang di mockup.test dan tmerp.test (user 2026-10-03: "apply to mockup.test and tmerp.test, make sure all
      connected to table and database ... don't forget data dummy on mockup.test"); data dummy laporan hanya di mockup.test
      (`UatStockReportSeeder`). **Menunggu penilaian user**; yang diputuskan agen sendiri dan menunggu koreksi:
      `docs/md_for_systems/inventoryreport_review.md` bagian 4.

24. **[2026-10-02] Kartu stok: dokumen bertanggal mundur dihitung ulang; periode tertutup ditolak; yang sudah Confirm dikoreksi lewat adjustment.**
    Kata user: *"pilih a"* (hitung ulang baris sesudahnya) — *"iya ditolak dan diinput adjustment (penyesuaian)"* — *"data
    dummy yang salah dihapus aja, dibangun ulang saja data dummy baru yang bener"* — *"jika salah tapi masih belum confirm
    masih boleh dibenerin, tapi kalau sudah confirm maka di-adjust"*.
    - **Urutan kartu stok = tanggal dokumen.** Dokumen stok (Stock In / Out / Return / Adjustment / Opname, penerimaan
      barang, pembalik penerimaan) yang tanggalnya lebih awal dari baris terakhir item itu di bin itu **disisipkan di
      tempatnya**, lalu **semua baris sesudahnya dihitung ulang**: saldo awal, saldo akhir, harga rata-rata, nilai. Bila
      harga rata-rata bin asal berubah, nilai yang sudah ikut Stock Transfer ke bin lain ikut dihitung ulang.
    - **Posting ditolak utuh** (tidak ada yang tertulis) bila membuat saldo minus — di baris itu atau di baris yang
      bertanggal sesudahnya (pengecualian lama tetap: Adjustment / Opname boleh).
    - **Periode inventory yang sudah ditutup:** dokumen bertanggal di dalamnya (atau sebelumnya) **ditolak**; koreksinya
      diinput sebagai **Stock Adjustment** bertanggal di periode yang masih terbuka.
    - **Belum Confirm boleh dibetulkan; sudah Confirm tidak diubah** — dikoreksi lewat adjustment (atau dokumen pembalik
      yang memang ada, mis. Cancel SPB).
    - **Dummy kartu stok yang salah dihapus dan dibangun ulang**, bukan diperbaiki di tempat.
    - Belum diputuskan (accounting): harga rata-rata baris Stock Out sesudahnya berubah, sedangkan jurnal pemakaiannya
      sudah diposting dengan harga lama. Sekarang selisih nilainya **ditampilkan sebagai peringatan**, jurnal tidak diubah.
    - Kode: `App\Services\Inventory\StockCardChain` (rantai saldo), `StockPostingService`, `InvPeriod::closedThrough()`;
      test `StockPostingOrderTest`. Rincian: `docs/md_for_systems/inventory_tier2.md` bagian "Urutan kartu stok".

## B. Database & data

1. **[LOCKED] Database-per-modul** (`tm2mst`, `tm2sdi`, `tm2pur`, `tm2inv`, `tm2prd`, `tm2acc`, `tm2hrm`, `tm2hrr`,
   `tm2pyr`, `tm2sys`). FK fisik tidak boleh lintas-database; integritas lintas-DB dijaga aplikasi.
2. **[LOCKED 2026-09-30] Semua tabel memakai PK tunggal auto number BIGINT** `id_<entitas>`.
3. **[LOCKED 2026-10-01] Database & semua formula dalam ENGLISH.** Bahasa lain hanya untuk layar dan cetakan,
   diterjemahkan lewat kamus `sys_dictionary`. Bahasa cetak dipilih user.
   **Kamus hanya berisi label, bukan data (2026-10-02):** nomor dokumen, nama customer / supplier / product, angka dan
   tanggal yang tampil di judul form, kartu, hint atau pesan tidak boleh tercatat di kamus. Layar tetap menulis label
   English; nilai datanya dibungkus `raw()` — `'Customer Order · '.raw($o->co_no)` — sehingga komponen menerjemahkan
   labelnya saja.
   **Sapuan multi-bahasa (2026-10-03, perintah user "make sure multi language sudah jalan dengan sempurna"):**
   - **Bahasa ikut AKUN** (`sec_user.lang_code`), bukan cookie komputer: memilih bendera menyimpan ke akun lalu halaman
     dimuat ulang; daftar bendera = `sys_languages` yang aktif. Tamu / halaman login memakai cookie.
   - **Pesan diterjemahkan di tempat pesan dibuat** (flash, error, abort, JSON, pesan service): `t('English.')` atau
     `tf('Quotation :no created.', ['no' => $x])`; layar menampilkannya sebagai teks jadi (`raw(session('v2_flash'))`).
   - **Label menu** (`sys_app_menu.label`) ditulis English dan diterjemahkan kamus saat tampil.
   - **Tombol, badge, dan status dokumen selalu English** (tidak masuk kamus).
   - Teks yang ditulis service KE DATABASE (uraian jurnal, catatan kartu stok, nama default) dan data master yang
     isinya masih Indonesia TIDAK diubah tanpa keputusan user (daftar: `docs/md_for_systems/multilanguage_status.md`).
   **Translation Dictionary dua panel ala ScriptCase (2026-10-03, user: "di panel kiri akan dibedakan button atau apapun
   object, di panel kanan semakin banyak bahasa kolomnya semakin banyak"):** pohon kelompok pesan di kiri (DevLib messages /
   Project messages per Buttons, Menu, Grid, Form, Messages, Print, ...; Lookup values per kolom), grid di kanan satu kolom
   per bahasa aktif. Kelompok = `sys_dictionary_group` (Setup > Message Groups), diisi otomatis oleh objek yang menampilkan
   teks. **Kamus proyek menimpa kata bawaan DevLib** (`Devlib\Lang::DICT` hanya cadangan). **Bahasa dinyalakan di Setup >
   Languages** (saklar Active; developer boleh mengubah detail bahasa); kolomnya muncul sendiri di kamus.
4. **Nama kolom:** `activate` (bukan `is_active`); status dokumen `stat_confirm` / `stat_cancel` / `stat_close` + `cancel_reason`.
5. **[LOCKED] Fase:** mockup.test memakai database dummy `ddt*`; tmerp.test memakai `tm2*`. **Jangan menulis ke
   `tm2*` saat kerja UAT/mockup.**
6. **SEMUA yang tampil dibaca dari database, dan SEMUA input disimpan ke database.** Tidak boleh ada data palsu,
   data JS saja, atau array hardcode. Bila menemukan jalur palsu, laporkan di baris PALING ATAS jawaban.
7. **Setiap data harus punya layar input**, termasuk konstanta. Bila belum ada, buatkan sebagai bagian pekerjaan.
8. **Data master REAL tidak boleh disentuh:** Master Mesin, Master Proses, Proses×Mesin, Master Pegawai.
   Jangan mengarang spesifikasi; riset ke situs pabrikan atau tanya user.
9. **Tanya dulu sebelum memasukkan DATA.** Data lama tidak 100% benar; analisa dulu dan ajukan usulan.
10. **Data yang sudah baku jangan dipertanyakan lagi.** Cek `docs/LOG_JOB_DONE.md` dulu sebelum menandai sesuatu janggal.
11. **Setiap mesin wajib punya minimal satu proses**, tanpa pengecualian.
12. **Dummy:** selalu sediakan data dummy untuk layar baru dan jangan dihapus. Tapi dummy lama yang sudah digantikan
    atau membingungkan harus DIHAPUS dan diganti yang baru. Setelah memperbaiki dummy, simpan ulang baseline `ddt`.
    **Aturan main tetap (2026-10-01):** dummy yang SALAH (saldo tidak konsisten, minus, relasi rusak, isi tidak
    masuk akal) selalu DIHAPUS dan diganti dummy baru yang benar — tidak perlu tanya dulu, cukup dilaporkan.
    **Baseline dan migration (2026-10-02):** setiap migration yang dijalankan di `ddt*` wajib diikuti simpan ulang
    baseline. Reset data dummy **menolak** selama struktur `ddt*` berbeda dari baseline (kolom / tabel baru atau hilang;
    daftarnya tampil di Developer → Data Dummy, atau `php artisan ddt:baseline check --env=mockup`), karena Reset
    memasang definisi tabel lama sementara migration tetap tercatat sudah jalan. Tabel yang dibuat setelah baseline
    tidak pernah disentuh Reset (barisnya tetap). Baseline lama dicadangkan dulu ke `tmerp_backup` sebelum ditimpa.
13. **Payroll wajib terenkripsi** at-rest (desain diputuskan terpisah).
14. **Master hanya diubah user, bukan sistem (2026-10-01).** Data master tidak berubah sampai ada override dari user; yang
    berubah oleh sistem hanya data transaksi. Nilai default boleh diperbarui oleh analisa terbaru sistem, dan tetap bisa
    di-override user. Contoh: Pick Item di Material Booking disimpan di booking, bukan di Master Product.
15. **Data dummy disusun per STAGE (2026-10-02).** Stage 1 = semua dokumen seolah belum di-Confirm, belum di-approve dan
    belum diproses; stage 2 = set yang sama sudah maju satu langkah dan menunggu langkah berikutnya; stage 3 dan
    seterusnya, sepanjang alurnya. Contoh user: stage 1 Inquiry New belum di-Confirm, stage 2 sudah di Pro-Dev belum
    di-Confirm, stage 3 di Pro-Cost belum di-Confirm. Tujuannya memastikan alur dan perilaku aplikasi benar: tiap langkah
    bisa diuji berulang dari titik awal yang pasti.
    - Tiap stage = satu set dump `.sql` (data saja) dengan tombol **Restore** sendiri di Developer → Data Dummy, untuk
      administrator. Perintahnya `php artisan ddt:stage list|save|restore|delete|build --env=mockup`
      (`App\Console\Commands\DdtStage`), file di `storage/app/ddt-stages/<set>/` (tidak masuk git).
    - Set dibangun `ddt:stage build` (`App\Support\Dummy\StageBuilder`) lewat ATURAN APLIKASI yang sama dengan tombol di
      layar (Confirm, Submit, Approve), bukan mengetik baris ke tabel. Aturan yang gagal saat memajukan stage = bug alur.
    - Stage 1 = titik nol: semua dokumen transaksi operasional (quotation, CO, CPDEL, WO, DO, invoice + voucher-nya, PO,
      penerimaan, dokumen & kartu stok, booking) tidak ada; yang ada hanya dokumen langkah pertama tiap alur. Master,
      data legacy, HR / payroll / rekrutmen, maintenance, aset dan voucher di luar penjualan / pembelian tetap.
    - Restore mengganti seluruh isi database dummy bisnis dengan set itu (master ikut kembali ke isi set); `ddtsys`
      (akun, hak akses, menu) tidak ikut. Kolom yang ditambah migration setelah set disimpan tetap ada. Set `mixed` =
      dummy campur semua status (untuk dashboard & laporan), disimpan terpisah.
    - **Urutan stage (user 2026-10-02):** 1 Inquiry New belum Confirm · 2 di Pro-Dev belum Confirm · 3 di Pro-Cost belum
      Confirm · 4 Quotation Draft · 5 Customer Order Draft · 6 Customer Order menunggu approval (level 1, F&A SPV) ·
      7 CPDEL Draft (CO Confirmed, baris Planned belum di-Post) · 8 Posted to Production, Work Order belum dimulai ·
      9 Delivery Order Draft · 10 Delivery Order Confirmed, menunggu bukti terima · 11 Customer Invoice Draft (bukti
      terima sudah dicatat) · 12 Customer Invoice Confirmed, menunggu pembayaran. Stage 1–7 dari user; 8–12 usulan
      agen yang belum dikonfirmasi user. Pembelian berjalan di samping: 1 PR Draft · 2 PR menunggu approval · 3 PR
      Approved · 4 PO Draft · 5 PO menunggu approval level 1 (Product Costing) · 6 level 2 (Operational Manager) ·
      7 level 3 (Presiden Direktur) · 8 dan seterusnya PO Approved (belum dikirim ke supplier, belum ada penerimaan).
      Stage berikutnya ditambah per alur di `StageBuilder::STAGES`.
    - Build sungguhan berjalan tanpa transaksi (aturan yang menulis lintas database harus melihat data tersimpan);
      `--dry-run` memakai transaksi yang dibatalkan dan berhenti di stage pertama yang menulis lintas database.
    - **Yang dibuat skrip di zona beku (hanya di dalam set stage):** Pro-Dev Confirm memakai susunan grid sederhana
      (blank tidak diputar, plano yang muat di meja cetak) lewat method yang sama dengan tombol Confirm, jadi BOM / tinta /
      proses tetap hasil SpecEngine; Pro-Cost memakai mode manual + CostingEngine (sama dengan dummy rantai order).
      Angkanya contoh, bukan hasil kerja petugas.
    - Draft yang tidak bisa menempuh alur (tanpa customer, part tanpa ukuran bentangan, tanpa pilihan qty) tidak ikut set
      stage; tetap ada di set `mixed`. Build memakan ±10 menit dan dijalankan lewat perintah, bukan tombol.
16. **[LOCKED 2026-10-03, sudah disampaikan user 7 kali — JANGAN ditanyakan lagi] Material Category = PEMBENTUK KODE
    ITEM.** Kata user: *"Material category ini pembentuk KODE ITEM. IMPORTANT !!!!"*. Acuan yang wajib dipahami:
    `docs/ddl/doc_rmprefix1.sql` (5 tabel `doc_rmprefix1..5`), sekarang satu tabel `mat_category` (level 1–5).
    - **Rantai 5 tingkat:** Category 1 → Category 2 → Category 3 → Category 4 → Category 5. Setiap tingkat punya nama
      **dan** kode prefix. **Kode item = prefix1 + prefix2 + prefix3 + prefix4 + prefix5** (prefix 1–3 satu karakter,
      prefix 4–5 sampai tiga karakter). Contoh nyata: Paper › Art carton › 210gr › 65 X 100 CM › GOLDEN =
      `P`+`C`+`C`+`02`+`002` = **`PCC02002`**; Ink › OFFSET › HN › AMBRI › AOK RED SAR = **`IOH31`**.
    - **Layar = dropdown yang saling terkait** (seperti yang lama): pilih Category 1, lalu Category 2 hanya berisi anak
      Category 1 itu, dan seterusnya sampai Category 5; kode item tersusun dan tampil selagi memilih. Daftar rata dengan
      kolom Level + Parent **salah**. Berlaku di master Material Category, form Raw Material, dan PR.
    - **Arti tiap tingkat berbeda per kategori** — jangan diberi label tetap "gramatur / ukuran / merek". Paper: jenis ›
      gramatur › ukuran lembar › merek. Sheet: flute (SHBF, SHEF, SFEF, BCF, DF) › substance (K150/M150/K200) › ukuran.
      Ink: kelompok (OFFSET) › seri (HN, DGM, RAPIDA, HAEMA) › nama / nomor › warna. Label layar = Category 1 … Category 5.
    - Satu baris kategori adalah sumber NAMA dan sumber KODE sekaligus — tidak boleh dipecah.
    - Kategori dan Type 2 tingkat di `tm2old` (`mst_item_material_cat` / `mst_item_material_type`) adalah klasifikasi
      sistem ScriptCase lama dan **bukan** pembentuk kode; jangan dipakai untuk menggantikan rantai 5 tingkat.
    - Rinci (struktur tabel, kejanggalan data, pola kode yang ada sekarang): `md_for_systems/matcategory_delta.md` §5.

## C. Tampilan (UI/UX)

1. **[LOCKED] Zona beku: Inquiry Product, ProDev, ProCost.** Esensi dan alur (data maupun dokumen) tidak boleh
   diubah tanpa izin. Sebelum mengubah apa pun di sana, tanya dulu dengan jelas (file, elemen, sebelum→sesudah).
   Yang sudah diizinkan: mengganti teks UI Indonesia ke English, dan memindahkan kulit ke komponen tanpa mengubah esensi.
   **Diminta user 2026-10-01 (form ProDev v2):** tab **Part & Dieline** (A.17) dan **latar putih** untuk workbench Imposition (C.25).
2. **DevLib dulu, mockup mewarisi.** Semua komponen layar harus dari objek DevLib (devlib.test). Layar v2 hanya
   membuat objek, tidak membuat ulang komponen. Format tanggal dan angka juga lewat objek DevLib.
3. **[LOCKED 2026-09-30] Grid mengikuti keluarga DevLib** (Grid Master / Pre-Entry Transactional / Report).
   Grid Pre-Entry jangan diubah sampai user memerintahkan. Semua input diawali dengan GRID.
4. **Form:** tombol aksi SELALU di atas (panel sticky), Save paling kanan di grup aksi, Back di ujung kanan.
   Selalu ada tab Riwayat (dibuat oleh/tanggal, terakhir diubah oleh/tanggal). Satu layar; bila lebih, pecah jadi tab.
5. **Tombol dokumen selalu English:** New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back.
   Status: Draft, Confirmed, Cancelled, Closed. Pakai kata **Confirm**, bukan Submit.
6. **Siklus tombol:** sebelum Confirm = Save + Delete (+ Confirm); sesudah Confirm = hanya Cancel + Print.
   Delete = hard delete sebelum confirm. Cancel = sesudah confirm, alasan wajib. Edit = form yang sama dengan New.
7. **Setiap form punya Print** (PDF, JPEG, HTML online + QR code) **dan Email**.
   **Dokumen Draft boleh dicetak** (user 2026-10-03, mulai dari Purchase Requisition): tombol Print tidak dikunci oleh status;
   lembar dokumen berstatus DRAFT memakai tanda air besar miring "DRAFT" (otomatis dari objek DevLib `x-dl::print-doc`,
   `Document::watermark()`), sehingga cetakan draft tidak bisa dikira dokumen sah.
8. **Back** kembali ke layar sebelumnya. Meninggalkan form yang berubah wajib muncul dialog Save / Don't Save / Cancel.
9. **Approval:** read-only; Approve/Reject = final; Hold = sementara; setiap keputusan dicatat (siapa, kapan, level, alasan).
10. **Input teks** memakai komponen dengan badge aa/Aa/AA. Nilai yang ada di master WAJIB dipilih lewat lookup,
    bukan diketik bebas. Warna lewat token tema, bukan hex.
11. **Format tanggal:** `dd-Mmm-yyyy` (10-Okt-2026 / 10-Oct-2026 sesuai bahasa). Angka mengikuti bahasa tampilan
    (id 1.234,56 / en 1,234.56).
12. **Ikuti mockup persis.** Penyimpangan harus ditunjukkan dulu sebagai JPEG bertanda, lalu tunggu persetujuan.
    Perubahan visual (ikon, warna, tata letak): tunjukkan pilihan JPEG dulu, baru dikerjakan.
13. **Setiap keputusan desain langsung dimasukkan ke mockup**; jangan pernah menampilkan mockup versi lama.
14. **Grup keamanan = level akses, bukan jabatan.** Jabatan hanya di struktur organisasi.
15. **Menu v2** (layer-shell dari `sys_app_menu`) = menu utama; laporan ada di grup Laporan per modul.
    Status `LOCKED` di menu hanya boleh diset oleh user.
16. **Nama file/folder tanpa emoji atau karakter non-ASCII.**
17. **F1 = Bantuan & dukungan** di setiap layar (v1 dan v2). Di v2, F1 / menu akun membuka halaman profil **Bantuan & dukungan**
    (Pedoman Aturan ERP, pedoman alur) dan **Tiket Bantuan** (kirim + lihat tiket ke tim support). Back selalu kembali ke Profile.
18. **Layer Menu (panel kiri) — disetujui user 2026-10-01:** baris 1 = logo + **nama aplikasi** (Settings → Identifikasi, mis. "ERP TM"),
    bukan nama perusahaan; baris 2 = ikon (tukar menu Operasional/Laporan di kiri, **cari** dan **kecilkan menu** di kanan).
    Tidak ada garis penanda layer dan **tidak ada breadcrumb di panel kiri** (breadcrumb hanya di layar kanan).
    Ikon kecilkan menu = panel jadi **satu lajur ikon** (diingat per browser); saat mini, klik cabang/Back/avatar membuat
    menu lengkap melayang di atas layar dan menutup sendiri setelah layar dipilih atau klik di luar.
    **Klik logo atau nama aplikasi (2026-10-02):** semua tab ditutup dan opening screen tampil; posisi menu di kiri tetap.
    Bila ada isian yang belum di-Save di salah satu tab, ditanya sekali dulu (Cancel / Close all tabs).
    **Logout (2026-10-03):** semua tab ditutup dan menu kembali ke lapis utama; login berikutnya mendarat di opening screen
    (tab lama tidak dipulihkan, berlaku juga bila sesi habis). Pilihan menu mini, tema dan bahasa tetap diingat.
    **Klik avatar / nama user di kaki panel (2026-10-03):** Profile terbuka; klik sekali lagi menutupnya. **Roda gigi dihapus** —
    Profile = satu pintu untuk semua hal akun. Susunan Profile: **Chat** (angka belum dibaca; titik merah di avatar bila Profile tertutup),
    **Edit profile** = laci LEBAR dari kanan berisi layar penuh `/v2/profile` (objek DevLib; data diri, keluarga, dokumen, cuti,
    izin keluar, tukar shift, jadwal, kartu pengenal, Security = akun + e-mail + password + pertanyaan pemulihan), **Setup**
    (anak: Theme, Language; akan bertambah), **Help & support** = laci LEBAR dari kanan berisi `/v2/help` (Help Center, pedoman,
    alur dokumen, Support Ticket) , lalu aksi akun (Notifications, AI Assistant, Developer Mode, Sign out). Halaman anak tidak tampil di daftar
    utama; Back dari halaman anak kembali ke induknya.
    **Klik item menu yang layarnya sudah terbuka (2026-10-03):** kembali ke **grid** menu itu; form yang sedang dikerjakan ditutup,
    bila ada isian belum di-Save muncul pertanyaan **Save / Don't Save / Cancel** (Save = simpan lalu ke grid, Cancel = tetap di form).
    **"General" = sifat akses, bukan menu (2026-10-04):** laporan yang terbuka untuk semua user (mis. Customer Order tanpa harga)
    tinggal di modul pemiliknya — Sales > Sales Order > **General Report**, nanti Production punya grupnya sendiri — bukan modul
    menu "General Report" tersendiri. Tandanya `required_object = GENERAL`; user tanpa hak modul itu tetap melihat modulnya,
    tapi hanya cabang GENERAL-nya.
    **Help & support dan Edit profile = Side Screen (2026-10-04, objek DevLib `x-dl::side-screen`):** dibuka LAYAR PENUH (menutupi
    menu dan area kerja); tombol Float menjadikannya jendela mengambang ala OS Windows: dipindah dengan menyeret kepala, ukuran
    ditarik dari tepi / sudut (snap ke tepi layar, posisi + ukuran diingat per browser); **tidak ada minimize** (ralat user).
    Kepala 52px = ikon, judul, sub-judul konteks, kotak cari (tombol `/`), Esc, Float / Full screen, Close. Isi =
    `x-dl::side-screen.layout`: rel bagian VERTIKAL di kiri (dikelompokkan, berangka), judul bagian lengket + satu kalimat
    petunjuk + breadcrumb, lebar penuh dimanfaatkan (kolom mengikuti lebar jendela), kaki aksi lengket `x-dl::side-screen.foot`
    untuk bagian berform; lebar < 900px = rel ikon saja. Mockup disetujui `docs/mockups/side_screen_drawer.jpg`.
    **Chat, AI Assistant, Notifications = laci sempit DUA MODE (2026-10-04):** tombol Maximize di kepala laci = laci selebar satu
    layar penuh (Chat menampilkan daftar orang dan percakapan berdampingan, isi AI / notifikasi dibatasi 960px di tengah),
    Restore = lebar semula; mode diingat per laci di browser. Objek DevLib `x-dl::drawer` prop `maximize`.
19. **Layar selebar panel kanan:** isi layar v2 tidak dibatasi lebar tengah; tepi kiri-kanan hanya 6px (±1,5 mm).
    Pengecualian: halaman Pedoman (teks bacaan).
20. **Setiap item menu wajib punya ikon sendiri** yang menggambarkan isinya dan berbeda dari item lain di daftar yang sama
    (menu mini hanya menampilkan ikon). Item menu baru tanpa ikon = belum selesai.
21. **Tidak ada bar alur (langkah 1-8) di atas layar** — membuat layar jelek. Penjelasan alur ditulis di **Help**, bukan di layar.
22. **Grid tanpa kotak statistik** (semua grid DevLib, 2026-10-02): status dan jenis jadi **satu baris filter teks + jumlah**;
    kiri = status (dari `stats`), garis pemisah, kanan = jenis (dari `tabs`). Garis bawah **bergeser** ke pilihan aktif dan
    **berganti warna** sesuai pilihan. "All" cukup satu (di kiri); klik lagi pilihan jenis yang aktif = lepas. Label singkat
    (mis. Pro-Dev, Pro-Cost).
23. **Panel dieline (Inquiry, ProDev, Master Product, Master Card) — user 2026-10-01:** tidak ada deretan tombol **Standard Sizes**
    di panel dieline; ukuran standar dipilih lewat **dropdown** saja. Gambar dieline memakai lebar penuh, **jendela 3D di
    samping gambar dengan tinggi sama** (lebih sempit) dan kotaknya **bisa diputar** (drag = putar, Shift+drag = miring,
    tombol auto-spin / zoom / reset). **Dimensions di bawah gambar, 2 baris berkelompok** (Product Size | Flat Size, lalu
    Flaps & Allowances) — tidak boleh ada satu field tersisa sendiri di baris bawah.
    **Hole (front panel)** di sebelah Flaps & Allowances: Hole Type None / Round / Rectangle + Width / Ø + Height, digambar di
    tengah panel depan (garis biru), terhitung di kotak **Perforation / hole**, tampil di 3D, tersimpan di
    `dieline_spec.dims` (`hole_type`, `hole_w`, `hole_h`) hanya bila dipakai. Belum masuk hitungan pisau ProDev / harga ProCost.
24. **Cetakan dokumen — user 2026-10-01:** kertas **A5 landscape** bila isi **1-5 baris**, **A4 portrait** bila **lebih dari
    5 baris**; berlaku untuk semua dokumen (Surat Jalan, Customer Order, Inquiry, PR, PO, Jadwal Kirim, dst). Dibuat sebagai
    **satu generator berorientasi objek** (kelas induk + turunan per dokumen): semua yang bisa diletakkan di posisi yang sama
    harus di posisi yang sama (kop, judul, nomor, tanggal, status, kolom Jumlah/Satuan/Harga/Subtotal, total, tanda tangan);
    variasi hanya pada **field yang dicetak**. Huruf **mudah dibaca, tidak kurus dan tidak tebal**. Format kertas lama di
    `docs/attachments` adalah acuan isi, **bukan untuk disalin**: tata letaknya diimprovisasi. Mockup dan generator contoh:
    `docs/mockups/print_doc/` (masih dikoreksi user; belum dipasang ke DevLib).
    Koreksi user 2026-10-01: kotak total **tanpa "Jumlah baris"**; kaki dokumen cukup **"Dicetak pada 01-Okt-2026 16:12 oleh
    Yuna"** (nama orang, bukan login) — tanpa nama aplikasi, nomor dokumen, dan tulisan ukuran kertas. **Dokumen tanpa
    nilai uang (Surat Jalan, dst) tidak mencetak total**; kotak total hanya untuk dokumen berharga (CO, PO, PR).
    **Surat Jalan punya kolom Berat (kg) dan Ikat**; Ikat hanya terisi bila barang dikemas per ikat (sumber data belum ada
    di database — perlu input sebelum dipasang ke aplikasi). **Customer Order punya 2 format cetak: dengan harga dan
    tanpa harga** (tanpa harga = kolom Harga/Subtotal, total, dan Terbilang tidak dicetak). **Nama Pembuat di tanda tangan
    dan nama "oleh" di kaki dokumen harus sama** (satu sumber: nama orang pembuat dokumen). **Purchase Requisition ada
    dua jenis: Produksi** (kolom No CO + nama product di baris kedua nama barang) **dan Non Produksi** (ATK, komputer;
    No CO kosong); harga estimasi tetap di kolom Harga / Subtotal (disetujui user 2026-10-01).
    **Kop Telasih Makmur memakai alamat yang sama dengan CV. Langgeng Jaya Makmur** (Pondok Blimbing Indah Blok A5 No. 5,
    Malang 65126, Telp/Fax 62-341-471268) dan **tidak memakai NPWP**; NPWP hanya di kop dokumen PPN.
    **Urutan tanda tangan semua cetakan — KETENTUAN TETAP user 2026-10-01, jangan dibalik lagi:** dari paling kiri ke
    kanan = **pihak luar** (supplier / customer / penerima / pengemudi), lalu **Menyetujui** (orang terakhir, paling kiri
    di antara orang dalam), **Mengetahui**, dan **Pembuat** di paling kanan. Contoh PO: Supplier, Hendra, Wiwik, Eka.
    **Pembuat = selalu user yang login** (nama orangnya). **Penanda tangan lain (Menyetujui, Mengetahui) = setting
    konstanta per dokumen yang mudah diubah** — **disetujui user 2026-10-01 ("taruh di constant")**: `sys_constant`
    kategori Print-out, grup Signer, kunci `SIGN_<DOKUMEN>_APPROVER` / `SIGN_<DOKUMEN>_ACKNOWLEDGER`, diubah di Settings →
    Constants; kosong = garis untuk tanda tangan tangan. Belum dibangun (dibangun saat cetakan dipasang ke aplikasi).
    **Format cetak Surat Jalan, Customer Order (dengan harga + tanpa harga), Purchase Requisition (Produksi + Non
    Produksi), Purchase Order, dan Inquiry = A (Approved) oleh user 2026-10-01** — tidak diubah lagi kecuali user sendiri
    yang meminta. **CPDEL = A juga (user 2026-10-01).** **A juga (user 2026-10-02, setelah melihat hasil cetak dari
    aplikasi): Quotation, Delivery Order, Bukti Terima (POD), Penerimaan Barang (SPB), Material Booking, Maintenance
    Request, Maintenance Work Order.** Belum A: Jadwal Kirim, Purchase Quotation, Bukti Kas Kecil.
    **Dipasang ke aplikasi 2026-10-02 (perintah user: pola di DevLib, diturunkan secara object oriented):** kelas induk
    `Devlib\PrintDoc\Document` + komponen `x-dl::print-doc`; tiap dokumen = satu kelas turunan di `App\Support\V2\Print`
    yang hanya mendeklarasikan field dan membaca datanya dari database. **Tombol Print di setiap entry transaksional membuka
    cetakan ini** (halaman `v2.print`; Print / PDF / JPEG / online + QR / Email lewat `x-dl::doc-share`). Dokumen baru wajib
    dibuat dengan cara yang sama, bukan tata letak cetak sendiri.
    **Cetakan CPDEL (Customer Plan Delivery) wajib ada, tanpa harga, dan split harus jelas** (user 2026-10-01): satu
    baris per kiriman; baris hasil split diberi garis tebal di kiri, kolom Kiriman "SPLIT 1/3", dan ringkasan Split di
    bawah tabel. **Penanda tangan CPDEL: Operations Manager, PPIC, dan Sales (pembuat) saling setuju, terakhir
    Customer** — dicetak dari kiri: Customer, Operations Manager, PPIC, Sales.
    **Jadwal Kirim wajib memuat Ambil / Kirim, jam, kota, dan alamat** (user 2026-10-01): satu baris per
    perhentian, **tiga baris teks**: nama tujuan / tempat ambil + kota, alamat, lalu barang yang dikirim / diambil (+ No
    CO). Ruang kosong blok atas dipakai untuk tinggi baris, dan **kolom tulis tangan IN / OUT dibuat besar**. Tiga
    baris itu **dibedakan gaya dan jenis hurufnya**: tujuan + kota tebal, alamat miring abu-abu, barang huruf berkait.
    **Model waktu Jadwal Kirim (koreksi user 2026-10-01):** tiap perhentian punya **ETA (rencana tiba) dan ETD (rencana
    berangkat dari tempat itu)**. Baris pertama = pangkalan, **hanya ETD** (mulai muat sampai berangkat); baris terakhir
    = kembali ke pangkalan, hanya ETA. Sistem menghitung **lama di jalan** (ETA - ETD perhentian sebelumnya) dan **lama
    muat / bongkar** (ETD - ETA). Jenis + ETA + ETD ditumpuk di satu kolom; IN / OUT = jam sebenarnya, tulis tangan.
25. **Form ProDev berlatar putih — user 2026-10-01:** workbench Imposition (dulu gelap/navy) memakai **latar putih** seperti
    layar lain, warnanya lewat token tema DevLib (bukan hex). Tata letak dan isi tidak berubah; area gambar berlatar abu muda
    supaya lembar cetak (putih) tetap terlihat. Pratinjau & cetak Master Card tidak berubah.

26. **Ink Configuration ProDev: 4 slot warna khusus — user 2026-10-01.** Di bawah tinta proses (CMYK) selalu tersedia **4 slot
    warna khusus** (Special 1-4 = Unit 5-8, pass pertama) yang **diisi officer langsung** di tampilan biasa: nama tinta
    (mis. PANTONE 485 C), warna, dan tutupan %. Slot kosong tidak disimpan; slot terisi tersimpan saat **Save** (master +
    Inquiry) dan menjadi baris tinta di Bill of Material selama BOM masih mengikuti engine. Nama dikosongkan = slot dihapus.

27. **Panel atas form ProDev = panel form standar — user 2026-10-01.** Judul + kode Master Card + badge status
    (Draft / Confirmed), lalu **Email ▾, Print ▾, Confirm, Save**, dan **Back** di ujung kanan (urutan C.4). Karena form ProDev punya
    **dua dokumen**, Print dan Email masing-masing **satu tombol berpilihan**: **Master Card** dan **ACC Digital**. Tidak ada lagi bar
    Print / Email terpisah di atas layar. Form lain yang punya lebih dari satu dokumen cetak memakai pola yang sama
    (objek DevLib `x-dl::menu-button`).
    Belum ada di panel ProDev karena alurnya belum tersedia: Undo, Delete, Cancel, Back to Draft, New, tab History.
    **Menu "ACC Digital / Proof Digital" ditiadakan** (user 2026-10-01): dokumen itu cukup dibuka dari Print / Email di form ProDev.

28. **Grid Transactional: persegi dokumen + tabel anak — user 2026-10-02.** Berlaku untuk SEMUA grid transaksi.
    - **Kolom pertama = persegi dokumen** (kolom `type => doc`): persegi panjang lebar 160, **nomor dokumen di atas (tebal), nama
      pihak di bawah** (customer / supplier / departemen / gudang), huruf putih di atas **gradasi dua rona**. Warna diambil dari
      **8 gradasi pilihan (Palet 1)** menurut nama pihak — pihak yang sama selalu berwarna sama; tanpa kuning / hijau zaitun.
      Tidak ada lagi kotak inisial di grid transaksi.
    - **Baris induk 5 sampai 8 kolom tampil.** Kolom lain boleh ada tetapi tersembunyi (dinyalakan lewat tombol Kolom).
      **Tidak ada kolom jumlah item / jumlah baris.**
    - **Expand (›) = tabel anak, paling banyak 7 kolom, dan SELALU memuat nama item / product.** Judul tabel tanpa jumlah baris.
      Tanpa harga (aman untuk semua pemakai). Dokumen satu product: nama product tetap muncul di tabel anak.
    - Objek: `x-dl::grid` (`type doc` + `party`, prop `detail`), `DbGrid::detail()`. Pratinjau yang disetujui:
      `docs/mockups/grid_tx_kolom_pertama_persegi.jpg`, `docs/mockups/grid_tx_expand_item.jpg`.
    - Sudah dipasang: contoh DevLib, Quotation, Customer Order, CPDEL, DO, Surat Jalan, POD, PR, PQ, PO, Penerimaan, Kas Kecil,
      Work Order, dokumen stok RM / FG / SFG, Material Booking, Inquiry (All / New / Revise / Recalc), Pro-Dev (New / Revise /
      Manual), Pro-Cost (New / Revise / Recalc), HR / Payroll (kode pegawai + nama), Recruitment, Rule Library, Setting Approval,
      Maintenance (WO: Expand suku cadang; Plan: Expand tugas). Grid tanpa item hanya memakai persegi, tanpa Expand.
      Daftar master (Master Product, Master Card Collection, ACC Digital) dan Material Planning tidak diubah.
29. **Kerapatan grid cukup per grid — user 2026-10-02.** Kerapatan baris (Compact / Normal / Comfortable) dan baris per sel
    diatur lewat tombol **Density** di toolbar tiap grid dan tersimpan di browser untuk grid itu. **Tidak ada** setelan kerapatan
    per user di database, **tidak ada** pintu kedua di menu Profile / drawer Configuration, dan Grid Pre-Entry tetap tanpa tombol
    Density. Menggantikan rencana 2026-09-22 (satu setelan per user + pintu Configuration → Grid).
30. **Logo aplikasi ≠ logo perusahaan — user 2026-10-02.**
    - **Logo aplikasi (lencana gradasi) + nama aplikasi "ERP TM" + tagline tidak diubah** dan tidak diganti logo perusahaan.
      Dipasang di menu tmerp.test **dan** mockup.test (kepala Layer Menu v2: lencana, nama, tagline).
    - **Logo perusahaan (TM "Percetakan TELASIH MAKMUR") dipakai di cetakan.** Cetakan versi **hitam-putih** memakai logo
      **hitam-putih**; cetakan versi **warna** memakai logo **warna berlatar putih** (hemat tinta). Versi tulisan putih hanya
      untuk latar gelap. Kop PPN (CV. Langgeng Jaya Makmur) tetap nama teks + NPWP, tanpa logo Telasih Makmur.
    - Tempatnya: `sys_constant` `COMPANY_LOGO_PATH` / `COMPANY_LOGO_BW_PATH` / `COMPANY_LOGO_INVERSE_PATH` (file di disk public
      `branding/`), dibaca `App\Support\Branding\CompanyLogo`, diganti di **Settings > Identifikasi > Identitas Perusahaan**
      (versi hitam-putih dibuat otomatis dari versi warna). Logo aplikasi tetap `APP_LOGO_PATH` (kosong = lencana gradasi).

31. **Chat antar user ala WhatsApp — user 2026-10-02.** Menu akun (roda gigi) → **Chat**; objek DevLib `x-dl::chat`.
    - **Yang bisa di-chat = user yang sudah terdaftar** (`sec_user` aktif + disetujui), satu lawan satu. Belum ada grup.
    - **Online = sedang login.** Orang online tampil **berwarna cerah** (avatar warna penuh + cincin dan titik hijau) di daftar
      "Sedang login"; yang tidak login **abu-abu** dengan "terakhir dilihat ...", dicari lewat tombol **+**. Sign out = langsung
      tidak online; browser ditutup tanpa sign out = tidak online setelah `CHAT_ONLINE_TIMEOUT_SECONDS` (90 detik).
    - **Lampiran file apa saja, paling besar `CHAT_ATTACHMENT_MAX_MB` (20 MB dulu).** Gambar tampil langsung, file lain jadi
      kartu unduh. File disimpan di disk privat dan hanya bisa dibuka dua orang di percakapannya.
    - **Chat berbunyi** saat pesan masuk (dua nada pendek) + pemberitahuan bila panel chat sedang tertutup. **Belum ada voice.**
    - Centang: satu = terkirim, dua abu = diterima layar lawan, dua biru = dibaca. Pesan baru dicek tiap `CHAT_POLL_SECONDS`
      (5 detik). Ketiga angka itu = `sys_constant` kategori **Chat**, diubah di Settings → Constants.
    - **Seketika (user 2026-10-02: "jalankan dengan reverb"):** pesan, centang dibaca, status online, dan tulisan **"sedang
      mengetik…"** didorong lewat WebSocket (Laravel Reverb) — server kecil terpisah di folder `realtime/`
      (`php artisan reverb:start` / `start.bat`, port 8080), supaya pustaka tmerp tidak berubah. Selama tersambung layar hanya
      bertanya tiap `CHAT_REALTIME_POLL_SECONDS` (30 detik). **Server Reverb mati = chat tetap jalan** dengan cara 5 detik dan
      menyambung lagi sendiri.
    - **Modular (user 2026-10-02):** chat = paket berdiri sendiri `packages/chat` (server) + objek DevLib `x-dl::chat` (layar),
      supaya bisa diambil untuk aplikasi lain. Paket tidak boleh bergantung pada tabel / kelas tmerp; yang khas aplikasi masuk
      lewat `config/chat.php`, `Chat\Contracts\Directory`, dan kait `Chat\Chat`. Petunjuk pasang: `packages/chat/README.md`.

32. **Baris grid berwarna menurut jatuh tempo, bukan kotak statistik — user 2026-10-02 (mulai dari CPDEL).**
    User: filter jauh lebih penting daripada statistik; baris berwarna lebih baik daripada tujuh kotak.
    - **Warna lembut + garis tegas di bawah baris.** Lewat tanggal = merah muda (garis merah), hari ini = oranye,
      H-1 .. H-n = kuning, masih jauh = putih, sudah dikirim = huruf abu-abu. Selalu ada **kolom teks "Due"** (Late 3 days,
      Today, D-1, 5 days left) supaya arti tidak bergantung pada warna saja.
    - **Hitungan hari = hari kerja:** Sabtu dianggap kerja, **Minggu saja yang libur**, ditambah hari besar di Holiday
      Calendar (`App\Support\WorkCalendar`, membaca Office Hours + Holiday Calendar).
    - **Batas kuning bisa diubah user sendiri:** konstanta `CPDEL_DUE_SOON_DAYS` (awal 2) di Settings → Constants.
    - Filter grid CPDEL: kiri = waktu (All, Overdue, Today, Due soon, Next 7 days), kanan = janji (Waiting for PPIC,
      Need negotiation, Escalated, Aligned). Baris yang belum dikirim tampil lebih dulu, slot paling awal di atas.
    - Objek DevLib: `x-dl::grid row-tone="<kolom>"` + kolom badge `toneKey` — grid lain yang punya jatuh tempo memakai
      cara yang sama. **Filter otomatis per peran** (tiap orang langsung melihat baris yang menunggu tindakannya) **belum
      diputuskan** — user belum bisa menilainya karena selama ini masih manual; jangan dibangun dulu.

33. **Opening screen = papan widget yang diatur pemiliknya — user 2026-10-02.**
    User: "bisakah disediakan many widget including graphics dan lain-lainnya, lalu dengan bantuan asisten AI seseorang
    bisa meminta untuk dipasangkan widget-nya — jadi opening screen menjadi lebih fleksibel"; "top management dulu yang
    diprioritaskan, yang lainnya masih ikut template yang dibuat".
    - **Top management dulu:** yang boleh membuka Laporan Manajemen (Presiden Direktur, Direktur, General Manager,
      Operational Manager) mendarat di papan widgetnya sendiri sesudah login. **Jabatan lain masih mengikuti template**
      (`x-dl::dashboard` per jabatan) — belum dipasang di mockup.test.
    - **Widget = satu kelas program + satu baris katalog** (`sys_widget`, Setup → Opening Board Widgets): judul, kelompok,
      siapa yang boleh memasang (modul + level security yang sudah ada, harga jual ikut A.9), frasa asisten, aktif.
    - **Susunan milik user** (`sys_user_widget`): tombol **Customize** → pindah, lebar, lepas, **Add Widget**, **Reset to
      Template**; langsung tersimpan. Papan pertama = salinan template profilnya (Setup → Opening Board Templates).
    - **Target** yang dibandingkan widget diisi di Setup → KPI Targets (perusahaan, atau per sales person untuk nilai
      order); kata harian di Setup → Daily Quotes; lokasi cuaca di Settings → Constants. Tanpa target = "No target".
    - **Periode "dari daily sampai yearly"** (user 2026-10-02): Harian, Mingguan, Bulanan, Kuartalan, Semesteran, Tahunan di
      kepala papan = periode kalender yang sedang berjalan, dibanding periode sebelumnya; pilihan tersimpan per user. Target
      diisi per bulan: kuartal ke atas menjumlah bulan-bulannya, harian dan mingguan tanpa target.
    - **Order value: "dijelaskan saja draft sekian dan confirmed sekian"** (user 2026-10-02): angka besar = Customer Order
      Confirmed / Closed (yang dibandingkan target); baris di bawahnya merinci Draft dan yang menunggu approval. Berlaku untuk
      setiap widget berisi nilai / jumlah order.
    - Data dummy / legacy di papan mengikuti setelan Setup → Dummy & Legacy Data, sama dengan layar lain.
    - **Dashboard Overall + dashboard per bidang** (user 2026-10-02): "kerjakan widget sebanyak mungkin ... direksi tinggal
      pilih tickmark widget mana saja yang mau terlihat di dashboard secara overall"; "dashboard untuk sales, purchasing, AR AP,
      cashflow, payroll, human resource sudah disediakan templatenya"; "kumpulan widget yang ada di purchasing, sales dll inilah
      yang bisa dipilih untuk masuk ke dashboard overall".
      - **Overall** = satu-satunya dashboard yang disusun pemiliknya: Choose Widgets = daftar centang, dikelompokkan menurut
        dashboard bidang asal widget. **Dashboard bidang** = template (Setup → Opening Board Templates), hanya dibaca.
      - Daftar dashboard = data (Setup → Opening Dashboards). Widget gaji hanya untuk yang berhak melihat nilai payroll.
    - **Seperti layar Android** (user 2026-10-02: "user can add some widgets and easily to add widget"): ubin **+ Add widget**
      di ujung papan, tekan dan tahan widget = mode atur, seret untuk memindah, kotak cari di daftar widget.
    - **Referensi wajib dicari dulu** (user 2026-10-02: "FIND and BUILD IT references from another ERP or another software
      for offset printing or ERP popular"): daftar widget disusun dari dashboard Business Central, SAP, NetSuite, Odoo, ERPNext,
      Zoho, PrintVis, Accurate / Jurnal — `docs/md_for_systems/openingboard_references.md` (termasuk yang tidak terkonfirmasi).
    - Objek DevLib: `x-dl::widget-board` (contoh di devlib.test → Opening Dashboard). Definisi tiap angka + cara mencoba:
      `docs/md_for_systems/openingboard_review.md`. **Terpasang di ddt dan tm2** (user 2026-10-02: "pasang saja di tmerp");
      asisten di tm2 belum dipasang, jadi perintah widget lewat asisten baru jalan di mockup.test.

## D. Cara kerja developer / agen

1. **Peran:** system analyst, developer, dan database designer. Aktif menilai desain; audit integritas data
   secara proaktif tanpa menunggu diminta.
2. **Konfirmasi perjalanan user** ("buka X → lihat Y → klik Z → dapat W") sebelum membangun. Kata yang bisa
   dibaca dua arti: sebutkan kedua arti dan pilihan yang diambil.
3. **Jangan menambah lingkup yang tidak diminta.** Kebutuhan belum jelas = proposal tertulis dulu.
4. **Bertanya lewat narasi**, bukan widget pilihan, saat diskusi. Saat user minta berjalan sendiri (tanpa
   prompting), jangan bertanya; catat keputusan untuk ditinjau user.
5. **Jangan membuat user mengizinkan prompt** untuk situs .test; jangan pakai browser pane untuk situs .test.
6. **Uji:** saat ini user menguji sendiri secara manual, kecuali user minta Claude menguji. Test yang menulis ke
   database wajib membersihkan sendiri, dan hasil bersih-bersihnya diverifikasi ke DB.
7. **Selesai kerja:** `npm run build` (bila class Tailwind berubah) → commit per potongan kerja → push ke GitHub.
   `git add` hanya path yang jelas (ada agen lain di checkout yang sama). Satu branch `main`, tanpa branch/worktree baru.
8. **Backlog:** item yang selesai dipindah ke `docs/LOG_JOB_DONE.md`, bukan ditandai SELESAI di backlog.
9. **Yang tidak dipakai lagi dipindah ke** `C:\Users\iwanp\tmerp_unused\` (+ MANIFEST), bukan dibiarkan atau diedit.
10. **Serah terima:** tulis hand-off di memori, lalu arsipkan sesi setelah selesai.
11. **Jangan pernah mengetik password** ke browser; jangan memakai data `employee_id=1` untuk uji.
12. **MRP/MPS sengaja ditinggalkan** (arsip `arsip/mrp-mps-2026-08-30`); akan dirancang ulang meniru ProDev/Inquiry.
13. **Folder acuan di `docs/` yang TIDAK boleh diarsipkan (user 2026-10-02):** `docs/md_prompt_rel*` = ketetapan user yang
    masih mau didiskusikan; `docs/legacy_scriptcase/` = sumber sistem lama untuk dipelajari supaya menjadi **pagar** — saat
    membangun / memeriksa modul v2, bandingkan dengan yang lama dan ingatkan user apa yang masih kurang; `docs/ddl/` dan
    `docs/database/` = kilas balik untuk menemukan apa yang masih tertinggal.
14. **Perbaikan di zona beku: preview dulu (user 2026-10-02).** Walaupun sudah diizinkan, tunjukkan dulu **sebelum → sesudah**
    ke user; dikerjakan setelah user bilang OK.
15. **Semua layar = turunan objek DevLib (user 2026-10-02).** Layar v2 (mockup.test dan tmerp.test, kode yang sama) ditulis
    dengan `x-dl::*` (dan `x-mp::*`, bagian khusus yang juga turunan `DevlibComponent`), bukan HTML mentah atau komponen v1.
    Form kecil (tombol aksi, isian pop-up, saringan) = `x-dl::action-form`; form dokumen / master = `x-dl::form`.
    Dijaga `DevlibOnlyRatchetTest` (angka HTML mentah tidak boleh naik). Layar v1 (menu lama) belum turunan DevLib: diganti
    layar v2 satu per satu, tidak dipoles.
16. **Setiap ENGINE wajib terdaftar dan dijelaskan (user 2026-10-02).** `App\Support\Engines\EngineRegistry` memuat kegunaan,
    parameter input, output, cara kerja, tabel yang dibaca / ditulis, setelan, layar pemakai dan test tiap engine; tanda tangan
    method dibaca dari kodenya. Tampil di **devlib.test → Engines → Engine Catalog** dan di menu **Developer → Engines**
    (objek DevLib `x-dl::engine-catalog`). Engine baru / tanda tangan berubah: daftarkan, lalu `php artisan engine:catalog`
    (dijaga `EngineCatalogTest`). Rumus harga ProCost rahasia: yang ditulis hanya alurnya, tanpa rumus dan angka.
17. **Dasar pemrograman = Object Oriented + komponen modular + library (user 2026-10-07).** Kode utama (layar, route,
    komponen Livewire, controller) dibuat **sederhana, mudah di-debug, dan dapat dilacak (traceable)**: hanya memanggil
    objek / service / library; logika, rumus, dan tampilan berada di kelas yang bernama jelas dan berdiri sendiri.
    Aturan praktis: satu kelas / file = satu tanggung jawab; main code berupa daftar panggilan yang dibaca seperti
    alur bisnis; tanpa salin-tempel (perilaku bersama naik ke induk / trait / library); tiap objek bisa ditelusuri ke
    sumbernya (`tag()`, `source()`, `lineage()`, `EngineRegistry`, log lewat satu jalur); kegagalan dilempar sebagai
    exception bernama dan dicatat dengan konteks (id dokumen, langkah), bukan ditelan; nama method memuat maksud.
18. **Arah warisan: DevLib membuat, tmerp mewarisi (user 2026-10-07).** Objek, helper, dan perilaku bersama dibuat DEVLIB DULU
    (`packages/devlib`, demo di devlib.test); tmerp / mockup.test hanya memakai atau menurunkannya. Paket `packages/devlib`
    berdiri sendiri: tidak boleh memakai kode `App\` milik tmerp (`use`, `new`, panggilan statis), dan helper global yang dipakai
    komponen / demo `x-dl::*` (mis. `raw()`) didefinisikan di paket (`packages/devlib/src/helpers.php`), bukan di aplikasi.
    Hal yang butuh data / kamus tmerp (`t()`, `tf()` -> `Dictionary`) tetap di tmerp dan masuk ke DevLib lewat kontrak, bukan
    dipanggil langsung. Ukuran: demo di devlib.test harus tampil tanpa kode tmerp. Dijaga `DevlibInheritanceTest`. Panduan kerja = skill `devlib-inheritance`.

19. **QC cepat per layar, tiga lapis persetujuan (user 2026-10-07).** Tiap halaman devlib.test (komponen) dan tiap layar v2 mockup.test punya bilah QC:
    **Approved** (satu klik, langsung tersimpan + waktu) atau **Need improvement** (pop-up, catatan wajib). Tiga lapis berurutan: **QC (user) -> UAT officer
    (officer terkait) -> UAT supervisor (atasannya)**; ketiganya Approved = **Ready to release** (siap naik ke live). Sekarang hanya lapis QC yang aktif.
    Objek `x-dl::qc-bar` + `Devlib\Qc` di paket; tabel `devlib.qc_review` (devlib.test) dan `sys_qc_review` di ddtsys (mockup.test, hanya bila `UAT_QC=true`
    di `.env.mockup`, mati di live). **Awal sesi agen: `php artisan qc:reviews` (di folder `devlib/`) dan `php artisan qc:reviews --env=mockup`** — kerjakan dulu semua
    Need improvement, tandai selesai dengan `--resolve=<id> --by=<agen>`. Kode tetap SATU folder; saat masa migrasi kode mockup disalin ke folder live terpisah,
    dan live dijalankan sesuai layar yang sudah Approved.

## E. Help & Asisten AI

1. **Setiap layar input dan laporan wajib punya Help (2026-10-02)**, tiga hal terpisah: **untuk apa** layar / laporan itu
   (Purpose), **cara pakainya** (langkah berurutan), dan **penjelasan tiap isian / kolom**. Tabel `sys_help_topic` +
   `sys_help_line`; tiap teks paling panjang 255 karakter, English di tabel, bahasa lain lewat kamus konteks `help` (B.3).
   - Dirawat di **Setup → Help Topics / Help Lines** (kolom English dan Indonesia berdampingan); dibaca semua user di
     **Profile → Help & support → Help Center** (dan F1; tidak ada lagi grup Help di menu utama, user 2026-10-04). Isi awal: `resources/data/help/*.json` → `php artisan help:import`
     (melaporkan layar di menu yang belum punya Help). **Layar baru tanpa Help = belum selesai.**
   - Help ditulis dari apa yang benar-benar ada di kode layar, bukan dari rencana.
2. **Asisten AI (2026-10-02, `docs/md_for_systems/aiassistant_tier1.md`; tahap 1–2 dibangun di ddt):**
   - **Bekerja tanpa internet** (model lokal lewat Ollama; deploy Ubuntu 24 ke atas). Lambat tidak masalah; diukur setelah jalan.
   - Tugas: mencari menu / layar, bantuan cara pakai (dari Help), dan menjawab angka untuk manajemen.
   - **AI tidak menghitung dan tidak menulis SQL.** Angka dihitung kelas program yang teruji (satu pertanyaan terdaftar = satu
     kelas); cara pakai dikutip dari Help; AI hanya memahami kalimat dan merangkai jawabannya. Tidak tahu = bilang tidak tahu.
   - **Siapa saja boleh bertanya, jawaban sesuai levelnya — acuannya level di user security yang sudah ada** (grup,
     `mgmt_level`, hak modul; harga dan HPP ikut A.8–A.9). Yang boleh membuka Laporan Manajemen boleh bertanya angka semua bagian.
   - **Pertanyaan gaji hanya untuk General Manager dan Direksi.** Gaji sendiri dilihat user di profilnya.
   - Bentuk: **chat pribadi asisten dengan user yang bertanya, di drawer sendiri.**
   - **Modular**: paket terpisah yang tidak tahu tabel ERP; tiap aplikasi cukup menulis adaptor (penanya, menu, bantuan, angka).
   - **Sales today** = nilai rupiah Customer Order yang masuk hari ini, dijawab terinci: **Draft** sekian, **Confirmed** sekian,
     **Total** sekian. **Deadstock** = stok tidak bergerak lebih dari 6 bulan, dinilai harga beli rata-rata.
   - **Riwayat percakapan:** 30 hari untuk top management, 14 hari untuk middle management, 7 hari untuk level terbawah
     (user lebih dari 100; menghemat storage). Angkanya konstanta, bukan hardcode.
3. **Asisten boleh menjalankan PERINTAH papan widget (2026-10-02, C.33):** "pasang widget ...", "lepas widget ...",
   "widget apa saja", "reset widget". Asisten hanya mencocokkan nama widget dari katalog lalu memanggil aturan papan yang
   sama dengan tombol Customize — hak pasang tetap dari security user, angka tetap dihitung kelas widget. Kata **widget**
   selalu berarti perintah papan, walau kalimatnya memuat nama pertanyaan angka ("lepas widget sales today").

---

## Changelog

| Tanggal | Aturan |
|---|---|
| 2026-10-01 | **RELEASE 1 Oktober 2026**: seluruh aturan user dikumpulkan dalam dokumen ini. |
| 2026-10-01 | A.1–A.2: Quotation wajib integrated; CO boleh direct (kompatibel V1 + percepatan Direksi). |
| 2026-10-01 | B.3: database & formula English, kamus untuk tampilan/cetak. |
| 2026-10-01 | A.3: CPDEL → POST ke Produksi; setelah POST, jalan pintas DO + DN (Surat Jalan) boleh dibuat untuk pekerjaan ±30 menit. |
| 2026-10-01 | A.3: tombol Post to Production pada CPDEL Confirmed; sistem tidak tahu lama pengerjaan sampai produksi di-input; batas 30 menit berubah sesuai data. |
| 2026-10-01 | A.3: hasil produksi tetap wajib di-input walau sudah dikirim; data hasil produksi = acuan KPI semua departemen. |
| 2026-10-01 | Pedoman ini dibuka dari menu akun v2 → **Bantuan & dukungan**. |
| 2026-10-01 | A.3 dibangun di v2 (ddt): Post to Production, DO hanya dari baris Posted, laporan Shipped Without Production Output. |
| 2026-10-01 | C.17: F1 = Bantuan & dukungan di v2; Tiket Bantuan di profil dengan ikon jelas; Back kembali ke Profile. |
| 2026-10-01 | A.15: customer memilih satu qty per product di CO (tombol Choose); A.16: WO hanya untuk product dengan costing published, dummy salah dihapus & dibuat ulang (sampel Master Card/KS Power kini lewat ProCost). |
| 2026-10-01 | B.12: dummy yang salah selalu dihapus dan diganti baru (aturan main tetap, tanpa tanya). |
| 2026-10-01 | C.3: grid Inquiry Product v2 ikut Grid Transactional (+ New Record di toolbar membuka wizard; Draft / Ready to Quote = kotak statistik; tanpa kartu tombol di atas grid; Duplicate = tombol di form, bukan ikon di baris grid). |
| 2026-10-01 | C.18–C.20: Layer Menu baru (nama aplikasi, baris ikon, mini, tanpa garis/breadcrumb); layar selebar panel kanan tepi 6px; setiap item menu wajib berikon. |
| 2026-10-02 | A.15: scope modul — Inquiry & Quotation jadi menu besar `PREORDER` (saklar sendiri), pintasan master, kontrak A/B (ddt; tm2 menunggu OK). |
| 2026-10-02 | A.15: menu = data (cara ScriptCase) — pintasan = baris ber-target sama, hidup-mati layar dihitung dari pohon menu, grup Flow jadi baris menu. |
| 2026-10-02 | A.15: semua menu terkait Inquiry -> Quotation tersedia di dalam menu `PREORDER` (ACC Digital, Pro-Cost Instant, pintasan master Proses / Mesin / Kategori Bahan / Satuan / Currency / Kurs / Constants, Sales Flow) — ddt + tm2 (menu `PREORDER` dimigrasi ke `tm2sys` atas OK user). |
| 2026-10-02 | A.15: WIN / LOST cukup di layar Quotation (filter status + kolom Closed, Customer Order, Reason); laporan terpisah "Hasil Quotation (WIN/LOST)" dihapus. Jangan buat layar baru untuk hal yang sudah bisa dilihat di layar dokumennya. |
| 2026-10-02 | B.5: perubahan menu / kode yang disetujui user diterapkan di mockup.test DAN tmerp.test sekaligus (kodenya sama) — menu `PREORDER` di `tm2sys` kini sama dengan `ddtsys`. |
| 2026-10-02 | A.15: pintasan master di sebuah modul hanya master yang berhubungan langsung (Inquiry: Customer, Product, Bahan Baku, Kategori Bahan Baku, Proses, Mesin) — bukan lookup umum seperti Satuan / Currency / Kurs / Constants. |
| 2026-10-02 | C.21: alur (Sales Flow, Costing Flow) sementara di Bantuan & dukungan (F1), bukan grup menu Flow — user akan memikirkan ulang tempatnya. |
| 2026-10-02 | C.21: bar alur order di atas layar ditiadakan (7 layar v2); alur ditulis di Help. |
| 2026-10-02 | C.22: kotak statistik di atas grid diganti satu baris filter teks dengan garis bawah berjalan & berwarna (semua grid DevLib). |
| 2026-10-01 | C.23: panel dieline — tombol Standard Sizes dihapus (dropdown saja), 3D di samping gambar & bisa diputar, Dimensions 2 baris berkelompok di bawah gambar. |
| 2026-10-01 | **Inquiry Product v2 = A (Approved) oleh user** — lencana menu hijau (`sys_app_menu.status_code = LOCKED`, baris `SALES_INQ`, ddtsys). Layar ini **tidak diubah lagi** kecuali user sendiri yang meminta. |
| 2026-10-02 | C.3: grid Inquiry Product = `x-dl::grid` biasa (komponen turunan InquiryWorklist dibuang); alamat buka per baris (`open-url="{open_url}"`) dan `new-modal` jadi fitur Grid DevLib. |
| 2026-10-02 | C.30: logo & nama aplikasi tidak diubah (dipasang juga di menu mockup.test); logo perusahaan TM di kop cetakan — hitam-putih untuk cetakan hitam-putih, warna berlatar putih untuk cetakan warna. |
| 2026-10-01 | C.24: cetakan dokumen A5 landscape (1-5 baris) / A4 portrait (lebih dari 5 baris), satu generator, posisi sama, huruf mudah dibaca; mockup di `docs/mockups/print_doc/`. |
| 2026-10-01 | C.24: cetakan tanpa "Jumlah baris"; kaki dokumen = "Dicetak pada tanggal jam oleh nama orang" saja (tanpa nama aplikasi, login, nomor dokumen, ukuran kertas). Surat Jalan dan dokumen lain tanpa nilai uang: tanpa total. Surat Jalan + kolom Berat (kg) dan Ikat. Customer Order 2 format cetak: dengan harga / tanpa harga. |
| 2026-10-01 | C.24: urutan tanda tangan semua cetakan = pihak luar, Menyetujui, Mengetahui, Pembuat (kiri ke kanan, ketentuan tetap); Pembuat = user yang login; penanda tangan lain = konstanta per dokumen (skema menunggu OK). Kop Telasih Makmur = alamat Langgeng Jaya Makmur, tanpa NPWP. PR Produksi (No CO + nama product) / Non Produksi. |
| 2026-10-01 | **C.24: format cetak Surat Jalan dan Customer Order (dengan harga + tanpa harga) = A (Approved) oleh user** — tidak diubah lagi kecuali user meminta. |
| 2026-10-01 | **C.24: cetakan CPDEL (tanpa harga, split jelas, penanda tangan Customer / Operations Manager / PPIC / Sales) = A (Approved) oleh user.** Jadwal Kirim dirancang ulang (Ambil / Kirim, jam, kota, alamat, tiga baris berbeda huruf, IN / OUT besar), belum A. |
| 2026-10-02 | **C.24: format cetak Quotation, Delivery Order, Bukti Terima, SPB, Material Booking, Maintenance Request, Maintenance Work Order = A (Approved) oleh user** — tidak diubah lagi kecuali user meminta. Belum A: Jadwal Kirim, Purchase Quotation, Bukti Kas Kecil. |
| 2026-10-02 | C.24: cetakan A5 / A4 dipasang ke aplikasi sebagai objek DevLib (`Devlib\PrintDoc\Document`, `x-dl::print-doc`); 15 dokumen transaksional punya tombol Print ke format ini; penanda tangan dari konstanta `SIGN_*` (ddt; tm2 menunggu OK). |
| 2026-10-01 | C.24: user minta **semua entry punya cetakan A5 landscape**. Mockup tahap 2 dibuat (belum A): Quotation, Delivery Order, Bukti Terima (POD), Purchase Quotation, Penerimaan Barang (SPB), Material Booking, Maintenance Request, Maintenance Work Order, Bukti Kas Kecil. Belum dipasang ke aplikasi. |
| 2026-10-01 | **C.24: format cetak Purchase Order dan Inquiry = A (Approved) oleh user** — tidak diubah lagi kecuali user meminta. Belum A: Jadwal Kirim. |
| 2026-10-01 | **C.24: format cetak Purchase Requisition (Produksi + Non Produksi) = A (Approved) oleh user** — tidak diubah lagi kecuali user meminta. |
| 2026-10-01 | B.14: master hanya diubah user; sistem hanya mengubah transaksi; default boleh diperbarui analisa sistem dan tetap bisa di-override. |
| 2026-10-01 | Material Planning (Booking + MRP + MPS) dibangun di ddt: keputusan B1-B9 di `docs/materialbooking_tier1.md` §6-§7. |
| 2026-10-01 | **A.17: ProDev berhak mengubah dieline & mockup part** — tab Part & Dieline di form ProDev v2 (dimensi, Flat Size, artwork luar/dalam), simpan dua arah `iqp_part` + `prod_part`, peringatan dampak (layout Confirmed / costing published / product aktif). |
| 2026-10-01 | C.25: workbench Imposition ProDev berlatar putih (token tema DevLib), tata letak tidak berubah. |
| 2026-10-01 | A.19: perbaikan satuan di ProDev (zona beku, diminta user "perbaiki" BOM kosong) — bentangan Inquiry (mm) dibaca sebagai cm di `jobs()`, `saveLayout()` dan `SpecEngine::blankArea()`; BOM / Ink / BOP kini tetap dihitung walau belum ada plano yang muat. |
| 2026-10-01 | **A.18: pilihan Canvas limit** di ProDev v2 (Custom ikut dieline = bawaan, Press bed, Die-cut bed, Press + Die-cut); aturan 16-Sep jadi salah satu pilihan, bukan otomatis. Mockup: `docs/mockups/prodev_canvas_limit.jpg`. |
| 2026-10-01 | C.26: Ink Configuration ProDev v2 — 4 slot warna khusus (Unit 5-8) diisi officer langsung, tersimpan saat Save All. |
| 2026-10-01 | C.27: panel atas ProDev v2 disamakan dengan panel form standar; Print ▾ dan Email ▾ masing-masing berisi Master Card + ACC Digital (objek baru `x-dl::menu-button`). |
| 2026-10-01 | C.27: menu ACC Digital / Proof Digital dihapus (cukup dari Print / Email di form ProDev); layarnya tetap ada. ddt sudah, tm2 menunggu OK. |
| 2026-10-02 | C.28: Grid Transactional — kolom pertama persegi dokumen (nomor + nama pihak, 8 gradasi Palet 1), baris induk 5-8 kolom tanpa jumlah item, Expand = tabel anak maks 7 kolom yang selalu memuat nama item / product. Inquiry / ProDev / ProCost menunggu diskusi. |
| 2026-10-02 | **A.20: alur Inquiry → Pro-Dev → Pro-Cost lewat tiga tanda** (`stat_confirm_sales` / `_prodev` / `_procost`) di satu tabel; menu dipisah New / Revise / Recalc; Confirm Sales otomatis muncul di Pro-Dev (tombol Kirim ke ProDev dihapus); Recalc langsung ke Pro-Cost; `stat_confirm` inquiry diganti nama. ddt + tm2. |
| 2026-10-02 | A.20: CO direct ada dua macam (repeat tanpa perubahan / percepatan Direksi); "back process" percepatan belum diputuskan. |
| 2026-10-02 | C.24: cetakan baru dipasang dengan objek yang sama, **belum A** (menunggu koreksi user): Sales Invoice / Down Payment Invoice, bukti kas / bank (Payment Voucher), Surat Jalan Subkon. Contoh hasil aplikasi di `docs/mockups/print_doc/app/`. |
| 2026-10-02 | C.24: mockup tahap 3 (**belum A, belum dipasang ke aplikasi**) untuk entry yang belum punya cetakan: Bukti Barang Masuk, Bukti Barang Keluar, Retur dari Produksi, Penyesuaian Stok / Stock Opname, Retur Pembelian, Work Order, Jurnal, Tagihan Supplier, Permohonan Cuti / Izin, Permohonan Tenaga Kerja; plus Tagihan Uang Muka (varian dengan kolom Persen; di aplikasi saat ini memakai format Sales Invoice). File `docs/mockups/print_doc/<nama>_a5.jpg`. Belum dirancang: Offering Letter (bentuk surat), slip gaji. |
| 2026-10-02 | C.24 (koreksi user atas mockup tahap 3): yang dicetak **bukan "Tagihan Supplier" melainkan Tanda Terima Tagihan Supplier** (satu baris = satu invoice supplier yang diserahkan; bukan bukti bayar); dokumen barang masuk bernama **SPB, Surat Penerimaan Barang**; ditambah **Tanda Terima Uang Muka** (bukti untuk customer). Semua masih mockup, belum A. |
| 2026-10-02 | C.24 (user): **barang masuk cukup satu dokumen = SPB** (yang diterima = yang masuk gudang; mockup "Bukti Barang Masuk" dibuang); yang diperlukan **QC Inspection** barang masuk, dicetak dari SPB yang sama. **Tanda Terima Tagihan Supplier** = entry baru v2 (Finance → Transaction Flows; `tm2acc.fin_invoice_receipt` + `_line`, nomor `TTyy#####`, bukan bukti bayar, tanpa jurnal); **Tanda Terima Uang Muka** tidak punya tabel sendiri: mencetak pembayaran uang muka yang sudah tercatat (nomor = nomor voucher). Tanda terima dicetak tanpa kolom Menyetujui. Ketiganya terpasang (ddt + tm2), **belum A**. |
| 2026-10-02 | C.24 (user: "ya ganti jadi SPB"): judul cetakan SPB = **SURAT PENERIMAAN BARANG** (dulu "Penerimaan Barang"), sub-judul tetap "SPB · Goods Receipt"; hanya judul yang berubah, format tetap A. |
| 2026-10-02 | **C.24 / Procurement (user): tiga jenis penerimaan = tiga judul dan tiga seri nomor.** Dari supplier = **Surat Penerimaan Barang**, nomor `SPByy#####` (field "Jenis" tidak dicetak); bahan dari customer = **Penerimaan Bahan Baku Customer**, nomor `PBCyy#####`; kembali dari subkon = **Penerimaan Barang dari Subkon**, nomor `PBSyy#####`. Alasan: SPB dipakai accounting untuk memeriksa tagihan supplier, jadi nomor SPB harus urut tanpa diselingi jenis lain. |
| 2026-10-02 | **Procurement (user setuju): nomor penerimaan (SPB / PBC / PBS) lahir saat dokumen diposting**, bukan saat Draft dibuat. Draft tampil sebagai "Draft #id" / "(on Post)" dan boleh dihapus tanpa membuat nomor loncat; dokumen yang sudah bernomor hanya bisa di-Cancel (nomornya tetap terlihat). Urutan nomor SPB = urutan posting. |
| 2026-10-02 | **A.16 diganti:** semua jenis CO boleh di-Post to Production asal Confirmed + approved; costing published tidak disyaratkan; product tanpa Inquiry mendapat WO sederhana (tanpa routing / bahan). |
| 2026-10-02 | C.29: kerapatan grid cukup per grid lewat tombol Density (tersimpan di browser); rencana setelan per user + pintu Configuration → Grid dibatalkan, bagian "Grid (segera)" di drawer Configuration menu lama dihapus. |
| 2026-10-02 | **E.1: Help wajib untuk setiap layar input dan laporan** (untuk apa, cara pakai, penjelasan tiap isian) — 159 topik ditulis dari kode, layar Help Center + Setup → Help Topics / Help Lines (ddt + tm2, OK user 2026-10-02), pintu Help Center di halaman F1. **E.2: keputusan Asisten AI** (offline, modular, AI tidak menghitung, jawaban sesuai level, drawer chat pribadi); rancangan menunggu persetujuan. Sales today dirinci Draft / Confirmed / Total; riwayat chat 30 / 14 / 7 hari menurut level. |
| 2026-10-02 | E.2: Asisten AI dibangun di ddt (paket `packages/assistant`, drawer chat, cari layar, bantuan dari Help, Sales today, Deadstock, penjaga gaji, log); level = user security yang sudah ada; pertanyaan gaji hanya GM + Direksi. tm2 menunggu user selesai menguji di mockup.test. |
| 2026-10-02 | C.31: chat antar user ala WhatsApp di menu akun v2 — user terdaftar, online = sedang login (warna cerah), lampiran file sampai 20 MB, bunyi saat pesan masuk, belum ada voice. Menggantikan data contoh; ddt + tm2. |
| 2026-10-02 | C.31: chat harus modular — bagian server dipindah ke paket `packages/chat`, layar tetap objek DevLib `x-dl::chat`; tmerp hanya menyambungkan user, setelan, dan kamus. |
| 2026-10-02 | C.31: chat seketika lewat Laravel Reverb (server terpisah `realtime/`) + tulisan "sedang mengetik…"; cara 5 detik tetap jadi cadangan bila server mati. |
| 2026-10-02 | C.32: CPDEL tanpa tujuh kotak KPI; baris berwarna menurut jatuh tempo slot (warna lembut + garis tegas di bawah), kolom Due, hitungan hari kerja (Sabtu kerja, Minggu + hari besar libur), batas kuning = konstanta `CPDEL_DUE_SOON_DAYS`. Filter otomatis per peran belum diputuskan. |
| 2026-10-02 | D.13: `docs/md_prompt_rel*`, `docs/legacy_scriptcase/`, `docs/ddl/`, `docs/database/` tidak diarsipkan — bahan diskusi user dan pagar pembanding sistem lama. D.14: perbaikan di zona beku ditunjukkan sebelum → sesudah dulu, dikerjakan setelah OK. |
| 2026-10-02 | B.6 / B.7 (hasil audit `docs/audit_input_report_lookup_2026_10_02.md`, urutan disetujui user): tarif PPN + PPh 23 Customer Order dan PPN Quotation dibaca dari Settings → Constants, Non PPN = tanpa PPN; layar isi baru Sales Person, Payment Terms, Department; Edit profil, Change password, Notifikasi di menu akun v2 tersambung database. ddt + tm2. |
| 2026-10-02 | **A.21: Customer Invoice + AR satu modul** — dua seri nomor (Non PPN `…/TM/…`, PPN `…/LJM/…`), nomor terbit saat Confirm; tagihan uang muka tanpa jurnal sampai uangnya diterima (menggantikan butir LOCKED `downpayment_tier1-4.md`); PPN uang muka mengikuti aturan pajak dengan setelan cadangan; nilai invoice penuh + potongan uang muka; dasar tagih = qty diterima; lima rekening bank lama boleh disalin. Ketentuan keuangan dan akuntansi: `docs/md_for_systems/customerinvoice_accounting.md`. Modul belum dibangun. |
| 2026-10-02 | A.21: **Qty Minim = harga borongan** — nilai baris Customer Order dan Delivery Order = harga, bukan qty × harga (sama dengan sistem lama). Kode: `CoOrderItem::recalcSubtotal`, `DoLine::recalcSubtotal`; test `QtyMinimLumpSumTest`. Berlaku untuk mockup.test dan tmerp.test (kode yang sama; belum ada baris Qty Minim di kedua database). |
| 2026-10-02 | **A.21: Sales = satu grup besar** dengan grup kecil Inquiry & Quotation, Order, Delivery, CRM yang bisa dilepas-pasang (saklar per grup). **CRM dibangun di ddt**: Customer 360 (hanya membaca, termasuk arsip order lama), Activities & Follow-up (`quo_activity` diperluas menjadi `crm_activity` per customer), Complaint & Return (informasi ke Sales) + Complaint Review QA (mutu ke QA). Tabel sudah ada di tm2; menu, Help, dan data contoh baru di ddt, menunggu penilaian user. |
| 2026-10-02 | B.3: kamus hanya berisi label, bukan data — nomor dokumen, nama customer / supplier / product, angka dan tanggal di judul form, kartu, hint dan pesan dibungkus `raw()` supaya tidak tercatat di Translation Dictionary; baris data yang sudah tercatat dihapus (migrasi `clean_dictionary_data_values`). |
| 2026-10-02 | A.21: **Customer Invoice Tahap 1 dibangun di mockup.test** (user: "jalankan dulu lalu buatkan dokumentasinya buat saya review") — invoice dari Surat Jalan yang sudah diterima, tagihan uang muka + Record Receipt, potongan uang muka otomatis, nomor dari seri saat Confirm, jurnal otomatis, master Company Bank Accounts dan Document Number Series, qty diterima di Proof of Delivery. Menu Down Payment lama dimatikan di mockup.test. tmerp.test belum. Dokumen review: `docs/md_for_systems/customerinvoice_review.md`. |
| 2026-10-02 | A.21: **layar lama dihentikan** (user: "yang lama dihapus saja, gantikan dengan yang baru. yang lama disudahi saja") — Order to Cash dan Down Payment lama diarsipkan, menunya dihapus; Customer Invoice satu-satunya pintu untuk invoice, uang muka, dan penerimaan uang customer (Record Payment: satu penerimaan untuk satu atau beberapa invoice). Terpasang di mockup.test dan tmerp.test. Belum ada gantinya: AR Aging. |
| 2026-10-02 | A.21: **tagihan terkait Surat Jalan + validasi customer; uang muka terlacak per CO** (user: "setiap tagihan harus related dengan surat jalan pengiriman dan validasi dari customer nilai yang terkirim, karena bisa ada retur dan rusak di jalan; uang muka jelas dari CO yang mana dan historical-nya terlacak dan tertera dengan jelas"; "sarankan yang terbaik dan jalankan dulu"). Dibangun di ddt + tm2: alasan selisih per baris di Proof of Delivery, tab Delivery di invoice, buku uang muka per order, tab Billing di Customer Order, To Invoice, AR Aging, Down Payment Ledger, satu akun bank per rekening (111210–111250). Retur sesudah invoice menunggu wawancara pemakai. |
| 2026-10-02 | C.18: klik logo atau nama aplikasi di kepala Layer Menu = semua tab ditutup dan opening screen tampil; posisi menu tetap; isian belum di-Save -> satu pertanyaan dulu. Objek DevLib `x-dl::layer-menu` prop `home` + `Shell::goHome`. |
| 2026-10-02 | C.33 + E.3: **opening screen = papan widget** — top management dulu (yang lain masih template), katalog widget + susunan per user + target KPI sebagai data, widget dipasang / dilepas lewat Customize atau lewat asisten; dibangun di ddt, review `openingboard_review.md`. |
| 2026-10-02 | C.33: papan widget **dipasang di tm2**; pilihan periode Harian – Tahunan (tersimpan per user, target bulanan dijumlah); order value merinci Draft di samping Confirmed; angka papan ikut setelan Dummy & Legacy Data. |
| 2026-10-02 | D.15: semua layar v2 = turunan objek DevLib; objek baru `x-dl::action-form` untuk form kecil (48 form dikonversi), penjaga `DevlibOnlyRatchetTest`. D.16: Engine Catalog — 20 engine terdaftar di `EngineRegistry` dengan input, output dan cara kerja; tampil di devlib.test > Engines dan menu Developer > Engines; `php artisan engine:catalog`. |
| 2026-10-02 | C.33 + E.3: user "pasang saja nanti juga bisa disempurnakan" -> **asisten AI dipasang di tm2**. User "perlu ada production, quality control and quality assurance, inventory and warehousing, payroll, human resource, Work Instruction, job description, SOP; on production, target vs result, capacity planning" -> dashboard **Production** (target vs hasil, beban kapasitas mesin, downtime), **Quality Control & Assurance**, **Inventory & Warehousing**, **Work Instruction / JD / SOP**; 67 widget, 11 dashboard. **Work Instruction** = jenis dokumen (`doc_type` SOP / WI) di register SOP. Jam tersedia mesin = jam per shift x shift per hari x hari kerja (Master Mesin) — tidak pernah dikarang; mesin tanpa jam tampil "No capacity set". ddt + tm2. |
| 2026-10-02 | C.33: 49 widget, dashboard **Overall** (daftar centang) + enam dashboard bidang (template): Sales, Purchasing, AR & AP, Cashflow, Payroll, Human Resource; ubin tambah, tekan-tahan, seret; daftar widget disusun dari referensi ERP lain (`openingboard_references.md`). ddt + tm2. |
| 2026-10-02 | B.12: **Reset data dummy menolak bila struktur `ddt*` berubah sejak baseline** (penjaga drift; daftar tampil di Developer → Data Dummy, `ddt:baseline check`, paksa `--allow-drift`); setiap migration di `ddt*` diikuti simpan ulang baseline; tabel yang dibuat setelah baseline tidak disentuh Reset; baseline lama dicadangkan sebelum ditimpa. Baseline baru disimpan 19:33:33. |
| 2026-10-02 | B.15: **data dummy per stage** — stage 1 = belum ada yang di-Confirm / di-approve / diproses, stage berikutnya maju satu langkah; tiap stage = set dump `.sql` dengan tombol Restore sendiri di Developer → Data Dummy (`ddt:stage`), dibangun lewat aturan aplikasi (`StageBuilder`). Terbangun: stage 1 (Inquiry New Draft, PR Draft), stage 2 (Inquiry Confirmed menunggu Pro-Dev, PR menunggu approval); set `mixed` = dummy campur. |
| 2026-10-02 | B.15: urutan stage dari user — 4 Quotation Draft, 5 Customer Order Draft, 6 Customer Order menunggu approval, 7 CPDEL Draft; set stage 1–7 dibangun (31 inquiry menempuh seluruh alur; Pro-Dev = susunan grid contoh lewat tombol Confirm yang sama, Pro-Cost = mode manual + CostingEngine). Draft tanpa customer / ukuran bentangan / pilihan qty tidak ikut set stage. |
| 2026-10-02 | **A.22: Inventory & Warehouse** — gudang = gedung (A5, A6, A10 + gedung lain; ukuran perkiraan, disesuaikan officer; bentuk bebas mis. huruf L; 1-3 lantai), area dan bin bisa ditambah user, Stock Transfer dengan label kapasitas + QR / barcode, dead stock merah (transfer bukan pergerakan), fasilitas label + lacak QR. Dibangun di ddt: Warehouse Map, Stock Transfer, Buildings / Storage Areas / Bins, Stock by Location, Print Location / Item Label, Track via QR/Barcode. Review: `md_for_systems/warehouse_review.md`. |
| 2026-10-02 | A.22: denah gudang sementara dibuat agen (ukuran dan letak perkiraan, tanpa menunggu sketsa / foto) dan harus tetap bisa diubah user atau officer warehouse lewat layar. |
| 2026-10-02 | A.22: **kapasitas gudang = luas dan tinggi** (tiap lantai 1-3: luas x tinggi bersih = volume; bin: tapak x tinggi tumpuk), kg tetap dipakai; yang lebih penuh dari keduanya menentukan status bin. |
| 2026-10-02 | A.22: **approval Stock Transfer di-skip dulu, fasilitasnya ada** — modul `TF` di Setting Approval, bawaan tidak aktif, bisa diaktifkan kapan saja; saat aktif stok baru pindah sesudah approval terakhir. |
| 2026-10-02 | A.22: **label wajib** — label palet dibuat setiap barang datang dan setiap isi satu palet habis dipakai (label ulang dengan qty sisa); label palet dan label item sengaja berbeda; setiap label punya QR code + barcode + tanggal tiba, pita warna sebagai penanda, kode palet dari supplier, dan tag kedaluwarsa (kertas, tinta); ukuran label dijelaskan di layar (60 x 40, 100 x 50, 100 x 150 mm; gulungan label atau A4); label palet dan label item bisa dicetak langsung dari Penerimaan Barang. Dibangun di ddt; tabel di ddt + tm2. |
| 2026-10-03 | A.22: **dipasang di mockup.test dan tmerp.test** (user: "apply to mockup.test and tmerp.test, make sure all connected to table and database; don't forget data dummy on mockup.test") — menu Inventory > Warehouse / Label & QR Code / Scan Center, Help, dan gedung A10 / A5 / A6 (denah perkiraan, tanpa stok) di tm2; data contoh mockup.test disimpan sebagai baseline Reset baru dan set Mixed. Approval Stock Transfer di tmerp.test menunggu Setting Approval terpasang di sana. |
| 2026-10-03 | A.22: **lokasi barang = posisi X, Y dan stack (tumpukan)**, terutama untuk barang yang tersisa 1 atau 2 rim (user: "IMPORTANT"). Rak sudah bertingkat; barang di lantai dan sisa rim belum. Analisa dan usulan: `warehouselayout_tier1.md` bagian 15 (menunggu jawaban user, belum dibangun). |
| 2026-10-03 | A.22: **tumpukan di gudang tidak jelas, jadi harus dipantau** (layar pemantau tumpukan, bukan batas kaku); **sisa tidak diberi palet sendiri, ditumpuk begitu saja dalam satu palet**; yang paling bermasalah = **kertas, ambri, sheet**; nomor tumpukan 1 = paling bawah; sisa seharusnya dipakai lebih dulu. Rancangan bagian 15.6, mockup `mockups/warehouse_stack_remnant_v2.jpg` (menunggu persetujuan). |
| 2026-10-03 | A.22: **tinta khusus hasil campuran tidak boleh hilang dari stok** — tinta sering dicampur untuk mendapat warna khusus dan hasilnya selama ini tidak terpantau. Konsep dokumen Ink Mixing: `warehouselayout_tier1.md` bagian 16 (menunggu jawaban user, belum dibangun). |
| 2026-10-03 | A.22: **lokasi barang = X, Y dan stack DIBANGUN** (user: "selesaikan main jobs, mapping warehouse with X, Y, stack. FIX IT NOW"; "soal ink dibahas nanti") — nomor tumpukan tiap palet / pak (1 = paling bawah) dijaga sistem, sisa = pak (otomatis untuk lembaran di bawah `WH_REMNANT_MAX_SHEETS`), tanda Remnant / Old remnant / Buried, layar Stack Monitor, tukar urutan di Warehouse Map, sisa pindah ke tumpukan lain lewat Stock Transfer. Aturan: `warehouselayout_tier1.md` 15.7. Terpasang di mockup.test dan tmerp.test. |
| 2026-10-02 | B.15: set stage 8–12 dibangun (Posted to Production + Work Order belum dimulai, Delivery Order Draft, Delivery Order Confirmed, Customer Invoice Draft, Customer Invoice Confirmed menunggu pembayaran) — usulan agen, belum dikonfirmasi user; rantai PO di samping: stage 4 PO Draft, 5–7 menunggu approval level 1–3, 8+ Approved. Build sungguhan tanpa transaksi. |
| 2026-10-02 | **A.23: laporan stok dan gudang** — set lengkap mengacu ERP populer (SAP, Odoo, Dynamics 365, Zahir, Accurate, Oracle NetSuite, ERPNext): Stock Card, Stock Recapitulation, Stock Summary, Stock Opname, Stock Loss & Gain (per bulan), Stock in Inspection, Stock by Location terkelompok per gedung; selisih stok masuk lewat stock opname. Grid Report mendapat periode (Period + Show) dan cetakan `x-dl::report`. Dibangun di ddt; review: `md_for_systems/inventoryreport_review.md`. |
| 2026-10-03 | **A.24: kartu stok** — dokumen bertanggal mundur disisipkan dan baris sesudahnya dihitung ulang (pilihan (a) user); dokumen bertanggal di periode inventory tertutup ditolak, koreksi lewat Stock Adjustment; belum Confirm boleh dibetulkan, sudah Confirm dikoreksi lewat adjustment; dummy kartu stok yang salah dihapus dan dibangun ulang. Kode sama untuk mockup.test dan tmerp.test. Belum diputuskan: jurnal koreksi untuk baris Stock Out yang harganya berubah (sekarang peringatan). |
| 2026-10-03 | A.23: laporan stok semua kelas dipasang juga di tmerp.test (menu, Help, kamus); tiap laporan diperiksa tersambung ke tabel di kedua database; sidebar menu lama menampilkan grup di dalam grup sebagai grup sendiri ("Warehouse › Report"). Data dummy laporan (`UatStockReportSeeder`) hanya di mockup.test, tanpa dokumen di periode akuntansi tertutup. |
| 2026-10-03 | **B.16: Material Category = pembentuk KODE ITEM** (user: "Material category ini pembentuk KODE ITEM. IMPORTANT !!!!", disampaikan untuk ke-7 kalinya; acuan `docs/ddl/doc_rmprefix1.sql`). Rantai 5 tingkat, kode item = gabungan prefix tiap tingkat (`PCC02002`), layar = dropdown yang saling terkait seperti yang lama; daftar rata Level + Parent di v2 salah. Arti tiap tingkat berbeda per kategori, label layar Category 1 … 5. Rinci: `md_for_systems/matcategory_delta.md` §5. |
| 2026-10-03 | B.3: sapuan multi-bahasa — sekitar 3.000 teks layar v2, pesan, objek DevLib, mockup-parts dan service kini English di kode + pasangan Indonesia di kamus (ddt + tm2); bahasa ikut akun user; pesan validasi Indonesia (`lang/id`); label menu English + kamus; penjaga `MultiLanguageRatchetTest` memakai kosakata 2.100 kata; status dan sisa keputusan di `docs/md_for_systems/multilanguage_status.md`. |
| 2026-10-03 | C.7: **PR Draft boleh dicetak dengan tanda air DRAFT** besar miring di lembarnya (user: "PR ini walau draft bisa di cetak, hanya cetakan print out ada tulisan draft miring dan besar"). Tanda air milik objek DevLib `x-dl::print-doc` (status DRAFT), berlaku untuk semua dokumen; tombol Print di PR dibuka untuk Draft / Submitted. Contoh di devlib.test → Print Document → Draft document. |
| 2026-10-03 | C.18: **Logout = semua tab ditutup, menu ke lapis utama, opening screen tampil saat login lagi** (user: "jika logout itu all tabs closed dan balik ke menu utama dan opening screen muncul"); **klik item menu yang sudah terbuka = kembali ke grid-nya** dengan pertanyaan Save / Don't Save / Cancel bila form berubah (user: "sudah saya sampaikan, belum dikerjakan" — sebelumnya hanya bekerja untuk form beralamat `?id=`, form v2 beralamat path). Objek DevLib Shell, berlaku di devlib.test, mockup.test dan tmerp.test. |
| 2026-10-03 | C.18: **klik nama / avatar user di kaki Layer Menu = buka Profile, klik lagi = tutup** (user: "bisakah jika klik nama juga muncul user profile ... di click sekali lagi maka user profile akan menutup"); roda gigi tetap membuka menu akun. Objek DevLib Layer Menu, berlaku di devlib.test, mockup.test dan tmerp.test. |
| 2026-10-03 | C.18: **roda gigi dihapus, Profile satu pintu** (user: "harusnya sama dan harusnya dengan begitu roda gigi bisa dihapus"); Chat masuk Profile; Support Ticket di dalam Help & support; Theme + Language di dalam **Setup** ("di setup nanti bisa berkembang lebih banyak"); E-mail, Change password, keamanan (pertanyaan pemulihan), cuti dan data diri lainnya di dalam **Edit profile** ("INI IMPORTANT untuk user profile, tidak sesederhana ini") — layar penuh self-service dibuka sebagai tab kerja. Objek DevLib Layer Menu / Layer Shell, berlaku di devlib.test, mockup.test dan tmerp.test. |
| 2026-10-03 | B.3: **Translation Dictionary dua panel ala ScriptCase** (pohon kelompok pesan kiri, satu kolom per bahasa kanan; `sys_dictionary_group`, `group_code` / `origin` / `first_seen`; kamus proyek menimpa kata bawaan DevLib; Setup > Languages = saklar bahasa; Setup > Message Groups). Mockup disetujui `docs/mockups/translation_dictionary_v2.jpg`. |
| 2026-10-03 | C.18: **Edit profile layar penuh = objek DevLib** (user: "gunakan inherit object from devlib untuk edit profile") — portal self-service v1 (11 tab: data diri, deskripsi kerja, SOP, keluarga, dokumen & pendidikan, cuti / sakit / izin, izin keluar, tukar shift, jadwal, kartu pengenal, security) ditulis ulang sebagai `v2/screens/profile.blade.php` + `App\Support\V2\Data\SelfService` (aturan SOP cuti, koreksi lewat HR, auto-Alpha, kunci dokumen HR = sama dengan v1; nilai `field_name` koreksi tetap teks v1 agar layar review HR tetap cocok). Tab Security = akun, password, pertanyaan pemulihan. Layar v1 `self-service` tidak dihapus (masih dipakai menu v1). |
| 2026-10-03 | C.18: **Edit profile dan Help & support = laci lebar dari kanan** (user: "banyak aspek sehingga windownya harus besar"; "help & support juga drawer besar dari kanan"); E-mail dan Change password tidak lagi item panel Profile — keduanya di dalam Edit profile tab Security (user: "harusnya email dan change password masuk ke edit profile"). Panel Profile = Chat, Edit profile, Setup, Help & support, aksi akun. Mekanisme umum: prop `drawers` objek DevLib Shell (laci lebar + iframe, dimuat saat pertama dibuka). |
| 2026-10-03 | C.18: **Chat dan AI Assistant = laci dari kanan dengan lebar secukupnya** (user: "chat dan assistent AI drawer dari kanan juga dengan lebar secukupnya") — Chat tidak lagi menempel / menukar panel kiri; angka belum dibaca tetap di avatar dan baris Chat di Profile. |
| 2026-10-03 | C.18: **urutan panel Profile** (user): Notifications, Chat, AI Assistant, garis, Edit profile, Help & support, Change password, Developer Mode (saklar), Sign out. Setup (Theme + Language, tidak disebut user) disisipkan sesudah Help & support. Urutan = `LayerShell::actions()`, baris pemisah = `sep`. |
| 2026-10-04 | C.18: **menu Setup = delapan grup tetap** (user): 1 Security, 2 Support & Ticket, 3 Help and Support, 4 Language and Localization, 5 Dummy Data & Migration, 6 Constants, 7 Dashboard and Widgets, 8 Others. Isi lama grup Settings (23) + Security (5) dibagi ke grup itu (migration `2026_10_04_000010`, ddt + tm2): Security = Account, Group, Group per Submodule, Setting Approval, Acting Officer; Support & Ticket = Support Tickets; Help and Support = Help Topics, Help Lines, Assistant Questions, Assistant Log; Language and Localization = Languages, Language, Localization, Translation Dictionary, Message Groups; Dummy Data & Migration = Dummy & Legacy Data, Migration; Constants = Constants, Constant Categories, Document Number Series; Dashboard and Widgets = Opening Dashboards, Widgets, Board Templates, KPI Targets, Daily Quotes; Others = Identification, Menu Setup, DevLib UI UX (+ Theme v1, mati). Baris grup Settings dipakai ulang sebagai Others. Item Setup baru masuk ke salah satu grup ini, bukan grup baru. **Revisi hari yang sama (user):** grup 8 = **Menu** (Menu Setup), 9 = **DevLib** (DevLib UI UX), 10 = **Identification**, 11 = **Others** (sementara hanya Theme v1 yang mati, jadi tidak tampil) — migration `2026_10_04_000020`. **Revisi berikutnya (user):** grup DevLib + layar DevLib UI UX **dihapus benar-benar** ("sudah ada di devlib.test"; file diarsipkan ke `tmerp_unused`, migration `2026_10_04_000030`), jadi sepuluh grup. Isi **Security** (user): Accounts, Groups, Modules (baru: grid `sec_module`), Groups on Modules, Approval Levels (dulu Setting Approval), Authorities (belum didefinisikan user — belum dibuat). Acting Officer (PLT) -> **HRM > Organization > Temporary Acting Officer** (user: "pejabat sementara harus di module HR bukan di setting ... tulis yang jelas pejabat pengganti sementara"; migration `2026_10_04_000040`, kode menu `HRM_ACTING_OFFICER`). |
| 2026-10-04 | E.1: **semua bantuan hanya lewat Profile > Help & support** (user: "koq ada help di general center? kenapa bukannya help semua ada di Help & support di halaman user profile?") — grup General > Help > Help Center dihapus dari menu utama (migration `2026_10_04_000050`, ddt + tm2); Help Center tetap dibuka dari laci Help & support dan F1. |
| 2026-10-04 | E.1: **Help & support = empat bagian** (user): tab **Help Topics** (Help Center: semua topik per layar), **User's Guide** (tuntunan pemakaian = topik `sys_help_topic` tipe GUIDE dirender berurutan: langkah bernomor + penjelasan bagian layar, dirawat di Setup > Help and Support > Help Topics; plus Pedoman Aturan + Pedoman Master Product), **Application's Flow** (alur Sales dan Costing dari `App\Support\V2\Flows`), **Tickets** (kirim tiket **dengan lampiran**: maks 5 berkas @ 5 MB, jpg/png/pdf/xlsx/docx -> `storage/app/public/support/<no tiket>/` + `sys_support_ticket_attachment`; daftar tiket saya + lampiran + balasan; tim developer melihat jumlah berkas dan tautannya di Setup > Support & Ticket). Judul di kepala laci lebar dihilangkan (user: "sudah tahu dari breadcrumb cukup"), hanya tombol tutup; breadcrumb jadi baris teratas. |
| 2026-10-04 | C.18: **"General" = sifat akses, bukan menu** (user: "GENERAL nanti menu itu ada di sales module, bisa nanti di PRODUCTION module. Sifatnya general bukan berarti dibuatkan menu general report") — modul menu "General Report" dihapus; tiga laporan CO tanpa harga pindah ke Sales > Sales Order > grup **General Report** (`required_object = GENERAL`, migration `2026_10_04_000060`); user tanpa hak Sales tetap melihat modul Sales hanya dengan cabang GENERAL-nya (`AppMenu::modules`). Di tm2 laporan ini belum pernah dipasang (hanya ddt). |
| 2026-10-04 | C.18: **Side Screen** — Help & support dan Edit profile menjadi objek DevLib baru `x-dl::side-screen` (user: "dibuatkan object baru turunan dari object devlib yang ada ... luas layar diperlebar hingga full screen ... bisa floating ... seperti windows di OS windows, bisa di-adjust size-nya dan bisa di-move"; ralat: bukan minimize). Turunan Drawer + Tabs + Breadcrumb: jendela layar penuh / mengambang (seret, ubah ukuran, snap, diingat per browser), rel bagian vertikal, judul bagian lengket, kotak cari, kaki aksi lengket. Katalog devlib.test Part 2 > Containers & Navigation > Side Screen; mockup `docs/mockups/side_screen_drawer.jpg`; test `SideScreenTest`. |
| 2026-10-04 | C.18: **Chat, AI Assistant, Notifications = dua mode** (user: "sebaiknya dibuat bisa maximize besar seluas full screen. Jadi ada 2 mode") — tombol Maximize / Restore di kepala laci (Shell) dan prop `maximize` objek `x-dl::drawer`; `DrawerMax` (devlib.js), mode diingat per laci; Chat di mode penuh = daftar orang + percakapan berdampingan. Test `SideScreenTest`. |
| 2026-10-07 | D.17: **Dasar pemrograman = Object Oriented + komponen modular + library** (user: "Make your main code simple, ease to debug and traceable") — main code berupa daftar panggilan ke objek / service / library, satu kelas satu tanggung jawab, tanpa salin-tempel, error = exception bernama berkonteks, objek traceable (`tag/source/lineage`, `EngineRegistry`). Ditulis juga di skill `devlib-oo` (`.ai/skills`). |
| 2026-10-07 | D.18: **Arah warisan DevLib -> tmerp** (user: "di devlib harusnya membuat object untuk bisa diwariskan ke tmerp") — objek / helper dibuat di DevLib dulu; paket tidak boleh memakai kode `App\`; `raw()` pindah ke `packages/devlib/src/helpers.php` (dulu hanya di tmerp, membuat demo devlib.test error 500); test `DevlibInheritanceTest`. Ditulis juga di skill `devlib-oo`. |
| 2026-10-07 | D.19: **QC cepat Approved / Need improvement per layar, tiga lapis QC > UAT officer > UAT supervisor; ketiganya lolos = Ready to release** (user: "fasilitas tiap layar saya sebagai QC ada approve, nanti 2 lapis UAT oleh officer terkait dan atasannya"; "jika ketiganya lolos tercatat siap release ke live"). Kode tetap satu folder sampai masa migrasi, lalu disalin ke folder live. |
