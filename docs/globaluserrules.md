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
13. **Payroll wajib terenkripsi** at-rest (desain diputuskan terpisah).

## C. Tampilan (UI/UX)

1. **[LOCKED] Zona beku: Inquiry Product, ProDev, ProCost.** Esensi dan alur (data maupun dokumen) tidak boleh
   diubah tanpa izin. Sebelum mengubah apa pun di sana, tanya dulu dengan jelas (file, elemen, sebelum→sesudah).
   Yang sudah diizinkan: mengganti teks UI Indonesia ke English, dan memindahkan kulit ke komponen tanpa mengubah esensi.
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
