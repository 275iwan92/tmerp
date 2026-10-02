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
    Disetujui user, modul belum dibangun:
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
13. **Payroll wajib terenkripsi** at-rest (desain diputuskan terpisah).
14. **Master hanya diubah user, bukan sistem (2026-10-01).** Data master tidak berubah sampai ada override dari user; yang
    berubah oleh sistem hanya data transaksi. Nilai default boleh diperbarui oleh analisa terbaru sistem, dan tetap bisa
    di-override user. Contoh: Pick Item di Material Booking disimpan di booking, bukan di Master Product.

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
    - Objek DevLib: `x-dl::widget-board` (contoh di devlib.test → Opening Dashboard). Definisi tiap angka + cara mencoba:
      `docs/md_for_systems/openingboard_review.md`. **Dibangun di ddt; tm2 menunggu OK user.**

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

## E. Help & Asisten AI

1. **Setiap layar input dan laporan wajib punya Help (2026-10-02)**, tiga hal terpisah: **untuk apa** layar / laporan itu
   (Purpose), **cara pakainya** (langkah berurutan), dan **penjelasan tiap isian / kolom**. Tabel `sys_help_topic` +
   `sys_help_line`; tiap teks paling panjang 255 karakter, English di tabel, bahasa lain lewat kamus konteks `help` (B.3).
   - Dirawat di **Setup → Help Topics / Help Lines** (kolom English dan Indonesia berdampingan); dibaca semua user di
     **General Report → Help → Help Center**. Isi awal: `resources/data/help/*.json` → `php artisan help:import`
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
| 2026-10-02 | C.18: klik logo atau nama aplikasi di kepala Layer Menu = semua tab ditutup dan opening screen tampil; posisi menu tetap; isian belum di-Save -> satu pertanyaan dulu. Objek DevLib `x-dl::layer-menu` prop `home` + `Shell::goHome`. |
| 2026-10-02 | C.33 + E.3: **opening screen = papan widget** — top management dulu (yang lain masih template), katalog widget + susunan per user + target KPI sebagai data, widget dipasang / dilepas lewat Customize atau lewat asisten; dibangun di ddt, review `openingboard_review.md`. |
