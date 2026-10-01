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
16. **[2026-10-01] Work Order hanya untuk product yang punya Inquiry dengan costing sudah di-publish**, juga untuk CO direct
    (INSTANT). Tidak ada jalan pintas di engine; dummy yang tidak memenuhi dihapus dan dibuat ulang dengan benar (B.12).
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

## B. Database & data

1. **[LOCKED] Database-per-modul** (`tm2mst`, `tm2sdi`, `tm2pur`, `tm2inv`, `tm2prd`, `tm2acc`, `tm2hrm`, `tm2hrr`,
   `tm2pyr`, `tm2sys`). FK fisik tidak boleh lintas-database; integritas lintas-DB dijaga aplikasi.
2. **[LOCKED 2026-09-30] Semua tabel memakai PK tunggal auto number BIGINT** `id_<entitas>`.
3. **[LOCKED 2026-10-01] Database & semua formula dalam ENGLISH.** Bahasa lain hanya untuk layar dan cetakan,
   diterjemahkan lewat kamus `sys_dictionary`. Bahasa cetak dipilih user.
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
    No CO kosong); harga estimasi tetap di kolom Harga / Subtotal (usulan tata letak, menunggu OK user).
    **Kop Telasih Makmur memakai alamat yang sama dengan CV. Langgeng Jaya Makmur** (Pondok Blimbing Indah Blok A5 No. 5,
    Malang 65126, Telp/Fax 62-341-471268) dan **tidak memakai NPWP**; NPWP hanya di kop dokumen PPN.
25. **Form ProDev berlatar putih — user 2026-10-01:** workbench Imposition (dulu gelap/navy) memakai **latar putih** seperti
    layar lain, warnanya lewat token tema DevLib (bukan hex). Tata letak dan isi tidak berubah; area gambar berlatar abu muda
    supaya lembar cetak (putih) tetap terlihat. Pratinjau & cetak Master Card tidak berubah.

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
| 2026-10-02 | A.15: semua menu terkait Inquiry -> Quotation tersedia di dalam menu `PREORDER` (ACC Digital, Pro-Cost Instant, pintasan master Proses / Mesin / Kategori Bahan / Satuan / Currency / Kurs / Constants, Sales Flow) — ddt; tm2 menunggu OK. |
| 2026-10-02 | C.21: bar alur order di atas layar ditiadakan (7 layar v2); alur ditulis di Help. |
| 2026-10-02 | C.22: kotak statistik di atas grid diganti satu baris filter teks dengan garis bawah berjalan & berwarna (semua grid DevLib). |
| 2026-10-01 | C.23: panel dieline — tombol Standard Sizes dihapus (dropdown saja), 3D di samping gambar & bisa diputar, Dimensions 2 baris berkelompok di bawah gambar. |
| 2026-10-01 | **Inquiry Product v2 = A (Approved) oleh user** — lencana menu hijau (`sys_app_menu.status_code = LOCKED`, baris `SALES_INQ`, ddtsys). Layar ini **tidak diubah lagi** kecuali user sendiri yang meminta. |
| 2026-10-02 | C.3: grid Inquiry Product = `x-dl::grid` biasa (komponen turunan InquiryWorklist dibuang); alamat buka per baris (`open-url="{open_url}"`) dan `new-modal` jadi fitur Grid DevLib. |
| 2026-10-01 | C.24: cetakan dokumen A5 landscape (1-5 baris) / A4 portrait (lebih dari 5 baris), satu generator, posisi sama, huruf mudah dibaca; mockup di `docs/mockups/print_doc/`. |
| 2026-10-01 | C.24: cetakan tanpa "Jumlah baris"; kaki dokumen = "Dicetak pada tanggal jam oleh nama orang" saja (tanpa nama aplikasi, login, nomor dokumen, ukuran kertas). Surat Jalan dan dokumen lain tanpa nilai uang: tanpa total. Surat Jalan + kolom Berat (kg) dan Ikat. Customer Order 2 format cetak: dengan harga / tanpa harga. |
| 2026-10-01 | B.14: master hanya diubah user; sistem hanya mengubah transaksi; default boleh diperbarui analisa sistem dan tetap bisa di-override. |
| 2026-10-01 | Material Planning (Booking + MRP + MPS) dibangun di ddt: keputusan B1-B9 di `docs/materialbooking_tier1.md` §6-§7. |
| 2026-10-01 | **A.17: ProDev berhak mengubah dieline & mockup part** — tab Part & Dieline di form ProDev v2 (dimensi, Flat Size, artwork luar/dalam), simpan dua arah `iqp_part` + `prod_part`, peringatan dampak (layout Confirmed / costing published / product aktif). |
| 2026-10-01 | C.25: workbench Imposition ProDev berlatar putih (token tema DevLib), tata letak tidak berubah. |
