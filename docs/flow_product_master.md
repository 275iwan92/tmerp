# Pedoman Alur: Inquiry → Quotation WIN → Master Product → Revisi / Rekalkulasi

> Diputuskan user 2026-09-29. Dokumen ini adalah **pedoman** untuk user dan developer (Claude): baca dulu
> sebelum mengerjakan apa pun yang menyentuh alur Inquiry, ProDev, ProCost, Quotation, Customer Order,
> Master Product, ACC Digital, atau Surat Jalan. Tampil di Menu v2 → Sales → Alur → **Pedoman: Master Product**.
> Status tiap aturan: ✅ sudah jalan di aplikasi · 🟡 sebagian · ⬜ belum dibangun.

## 1. Alur utama (tidak berubah)

```
Inquiry (Baru / Revisi / Rekalkulasi) → ProDev → ProCost → Quotation → WIN → Customer Order → CPDEL → Delivery Order / Surat Jalan
```

- Urutan Inquiry → ProDev → ProCost **tetap**, juga untuk Revisi dan Rekalkulasi. ✅
- **ProDev berhak mengubah dieline & mockup part** (user 2026-10-01, rulebook A.17): form ProDev punya tab **Part & Dieline**
  (dimensi, Flat Size, artwork luar/dalam). Simpan dua arah: inquiry (`iqp_part`) + master (`prod_part`); imposition langsung
  memakai blank baru. Tidak diblokir oleh status, tetapi memberi peringatan bila layout sudah Confirmed, costing sudah
  published, atau product sudah aktif (artwork baru = versi baru, ACC Digital diulang). ✅ v2 (kode sama untuk ddt & tm2,
  tanpa migration).
- **CPDEL → POST ke Produksi → DO / Surat Jalan** (user 2026-10-01). **Jalan pintas pekerjaan cepat:** setelah CPDEL
  di-POST ke Produksi, DO dan DN (Surat Jalan) boleh langsung dibuat tanpa menunggu produksi selesai, untuk pekerjaan
  yang pengerjaannya hanya sekitar 30 menit. 🟡 dibangun di v2 (mockup.test / ddt, 2026-10-01): tombol **Post to Production**
  di CPDEL → WO dibuat → DO hanya menarik baris Posted → laporan Production › Report › Shipped Without Production Output.
  Belum: migration ke tm2*.
- Quotation berisi **banyak inquiry**; **1 inquiry = 1 product**. ✅ (quotation multi-inquiry sudah ada)
- **1 Quotation ↔ 1 Customer Order** (No. CO). Satu CO punya **banyak CO Detail**. ✅
- **Inquiry → Quotation WAJIB integrated** (user 2026-10-01): baris quotation selalu berasal dari inquiry yang
  costing-nya sudah PUBLISHED — tidak ada quotation tanpa ProDev/ProCost, termasuk data dummy. ✅
- **Customer Order BOLEH direct tanpa Quotation** (CO INSTANT dari Master Product) (user 2026-10-01): menjaga
  kompatibilitas dengan sistem lama, dan sesuai realita — Direksi bisa minta percepatan yang langsung dikerjakan
  Produksi, jalur tercepatnya input langsung di CO. ✅
- **Setiap CO Detail menunjuk ke Master Product** — rantai lengkap bisa ditelusuri:
  Quotation → No. CO → CO Detail → Product (milik customer) → Parts. 🟡 (kolom `co_order_item.product_id` ada, tapi sekarang diisi dari
  `iqp_inquiry.parent_product_id` yang punya 2 arti — lihat §7)

## 2. Quotation WIN = lahir di Master Product ✅ (ProductMasterSync, dipanggil saat CO lahir dari Quotation)

- Saat quotation **dimenangkan (WIN)**, setiap inquiry di dalamnya disalin menjadi **data pokok Master Product**,
  tidak hanya tersimpan di `iqp_part`.
- Yang ikut menjadi data pokok product: **semua part** beserta bahan, BOM, BOP, warna/tinta, finishing,
  **dieline + ukuran bentangan**, **mockup/artwork sisi luar & dalam**, layout ProDev (plano, UP, pisau),
  dan semua ketentuan detail lainnya.
- **Setiap product WAJIB punya pemilik = customer** (`prod_product.customer_id`), ditampilkan & bisa ditelusuri
  (semua product milik 1 customer). Master Product **tidak** menampilkan No. PO (user 2026-09-29).
- **Nama product diubah manual oleh Sales** supaya sama dengan PO customer. Tujuan: nama product di
  **salinan PO customer = CO Detail = Surat Jalan Detail** (sama persis).

## 3. Struktur product: 1 product = banyak part, bisa bertingkat ✅ (skema + dummy KS Power; UI input rakitan di Inquiry Baru ✅ kartu "Rakitan" di bawah form, 2026-09-30)

```
Product
 └── Part (Komponen) atau Rakitan
       ├── Dieline + ukuran bentangan, artwork luar/dalam (mockup)
       ├── Bahan (Jenis + Gramatur per lapis; mat_raw persis ditetapkan ProDev/Purchasing)
       ├── BOM (bahan + tinta + coating/film + lem/packing)
       ├── BOP (urutan proses + mesin)
       └── Finishing, warna
```

- Semua bahan, BOM, BOP **tertaut ke part**, part tertaut ke product.
- **Rakitan** = part yang dibuat dengan menggabungkan part lain. Boleh **bertingkat tanpa batas**
  (contoh: kursi dari sheet — rakitan di dalam rakitan). Tiap node punya BOM & BOP sendiri.
- Bahan tetap milik komponen; rakitan hanya menambah bahan penggabung (lem/tape) & packing.
- **Jenis sambungan (join) pada rakitan:**
  - **SINGLE JOIN** — direkatkan 1 sisi, **masih bisa dibentangkan**. Dieline tampil sebagai 1 bentangan gabungan.
  - **DOUBLE JOIN** — direkatkan 2 sisi, **tidak bisa dibentangkan lagi** (jadi tabung). Dieline tampil per komponen +
    3D rakitan + tanda "tidak bisa dibentangkan"; berdampak ke packing/volume kirim.
- Produksi: WO rakitan menunggu WO komponennya selesai (penggabungan WO antar-part belum dimodelkan — tahap tersendiri).
- ProCost: HPP rakitan = HPP komponen + biaya proses penggabungan.

**Contoh nyata (sampel dummy PERMANEN, tidak boleh hilang saat Reset):** KS Power N120 MF — PT. Selatan Jadi Jaya,
DS003637, 50,7 × 18,7 × 26,7 cm, Double Join. Master card: `docs/attachments/mastercard/`.

```
KS Power N120 MF
 └── Rakitan Body (DOUBLE JOIN) — BOP: Lem Samping → Hitung → Pack (10 pcs/pack)
      ├── Part A (1/2 UP) — BOP: Potong → Cetak (Lithrone G40, CMYK) → QC → Laminating Glossy → Laminasi → Plong Belakang → Kupas
      └── Part B (1/2 UP) — BOP: sama dengan Part A
      Bahan per part: Duplex 310 gsm + SH B/C Flute M125/M125X3/K125; pisau SJJ N120 MF 72,9 × 46,4 cm
```

## 3b. Alur antar bagian = tiga tanda di satu tabel (user 2026-10-02, pedoman A.20)

- `iqp_inquiry` menyimpan ketiga jenis dokumen (New, Revise, Recalc). Menu dipisah per jenis; tabelnya satu.
- `stat_confirm_sales = 1` → New / Revise muncul di **Pro-Dev**, Recalc muncul di **Pro-Cost** (tanpa Pro-Dev).
- `stat_confirm_prodev = 1` (semua part Confirmed oleh Pro-Dev) → muncul di **Pro-Cost**.
- `stat_confirm_procost = 1` (costing published) → kembali ke Sales, siap dipilih di Quotation.
- Tidak ada tombol "Kirim ke ProDev": Confirm Sales sudah cukup. Kode: `App\Services\Sales\InquiryStage`,
  grid `App\Support\V2\Grid\Sales\InquiryDeskGrid`.

## 4. Revisi & Rekalkulasi berangkat dari Master Product ✅ (layar v2 inquiry-revise / inquiry-recalc)

- Di layar Revisi/Rekalkulasi, user **memilih product (atau inquiry)** → **semua part tampil** lengkap dengan
  **dieline + mockup artwork** yang jelas, bahan, BOM, BOP, warna, finishing.
- **REVISI** = membuka **cabang baru**: setiap revisi menghasilkan **product turunan baru** di Master Product yang mencatat
  induknya ("turunan dari product X"). Product lama tetap utuh; CO lama tetap memakai snapshot-nya.
  **Titik berat Revisi = HARUS TAHU PERBEDAANNYA**: tiap field yang berubah dicatat sebelum → sesudah
  (`iqp_revise_change`: part, scope, field, nilai lama, nilai baru, diulang di ProDev/ProCost/Proof) dan informasi ini
  **mengalir & tampil di ProDev dan ProCost**. Hanya blok yang dipilih di scope yang boleh diubah; alur lanjut ProDev → ProCost → Quotation; bila WIN, product
  turunan resmi masuk master.
- **REKALKULASI** = **product yang sama**, spec 100% terkunci, yang diisi hanya Qty & Deadline. Menghasilkan
  **versi harga baru** di ProCost dengan **tanggal & jam terbit** yang jelas; riwayat harga per product bisa dilihat.
  Layar Rekalkulasi WAJIB menampilkan **riwayat harga detail untuk Sales**: tiap versi (terbaru di atas) berisi tanggal &
  jam terbit, dokumen sumber (Inquiry Baru/Rekalkulasi), penyetuju, harga per jenjang qty (+ total), selisih vs versi
  sebelumnya, dasar perubahan (mis. harga bahan naik), dan dipakai di Quotation/CO mana (WIN/LOST/terbuka).
  HPP & margin tetap rahasia Costing (tidak tampil).
  **Urutan prioritas layar Rekalkulasi (fokus Sales):** (1) **bahan baku yang digunakan** per part (Jenis/Gramatur;
  merek hanya acuan) — **harga beli bahan & ukuran/luasan plano TIDAK tampil di Sales, hanya di ProCost**;
  Sales hanya melihat harga JUAL product, (2) **riwayat harga jual**, (3) **proses khusus berbiaya besar** (mis. UV Spot, Emboss,
  Hot Print, Laminasi, Plong/pisau baru, Lem 2 sisi) — baru kemudian detail part + dieline (terkunci).

## 5. ACC Digital Artwork (wajib) 🟡 (cetak Proof Digital + catat ACC di ProDev ✅; blokir proses Cetak di WO ⬜)

> **Format dokumen = Proof Digital** yang selama ini dipakai (contoh asli: `docs/attachments/Proof Digital KAYS N200 C MF
> (N200L) DS003479_DC 310g 2x.pdf` — isinya proof KS Power N120 MF DS003637). Boleh berubah sedikit, jangan banyak:
> judul kode+ukuran (P x L x T cm) · tabel REVISION NOTE (NO, catatan, DESIGN NUMBER, DATE) · dieline+artwork tiap part
> (A, B) · blok bawah: logo+nama perusahaan + "Perhatian !!!", CUSTOMER, ITEM, Inner SIZE L/W/H mm, DATE, DESIGN NUMBER,
> PRINT COLOUR, DRAWING BY, APPROVED BY (QA, PRACETAK, CUSTOMER = INQUIRE & APPROVED BY), NOTE.

- Normalnya dibuat **setelah PO customer turun**, karena butuh biaya **CTP** (Computer To Plate — pembuatan plat cetak,
  proses pre-press). Produksi mencetak **sample dari plat CTP itu** → customer memberi ACC.
- Tetapi **ada customer yang ACC sebelum PO** — tetap boleh dicatat.
- **ACC Digital WAJIB ada sebagai pedoman produksi**: proses Cetak (WO) tidak boleh jalan tanpa ACC Digital.
- Batas waktunya belum pasti (tergantung customer) → cukup dicatat: tanggal/jam, penyetuju (mis. Pak Habibi),
  saluran (WA/e-mail — selama ini manual lewat WhatsApp), bukti (screenshot/file).
- Master card mencatat: *"ACC PROOF PRINT (DIGITAL + CETAK) HARUS DILAMPIRKAN BERSAMA MASTER CARD"*.

## 6. ACC Sample Bentuk ⬜

- Sample bentuk dibuat **sungguhan dari sheet saja** untuk mengecek presisi saat dibentuk.
- **Dicatat** (tanggal, penyetuju, saluran, bukti); tidak menahan proses.

## 7. Due date Customer Order 🟡

- Default = **tanggal CO dibuat + 14 hari kerja**, tetapi **MANUAL ADJUSTABLE** — harus fleksibel saat input.
  (Menggantikan aturan lama "14 hari kerja dari approval prove fisik".)
- `due_date` tetap wajib terisi di semua baris PRODUCT sebelum CO bisa Confirm (sudah ada ✅).

## 8. Catatan teknis untuk developer

- Kolom `iqp_inquiry.parent_product_id` saat ini punya **2 arti**: id inquiry induk (Revisi/Rekalkulasi) **dan**
  id `prod_product` (diisi "Kirim ke ProDev"). Harus dipisah: tautan ke Master Product pakai kolom tersendiri.
- Tabel master yang sudah ada: `prod_product`, `prod_part`, `prod_part_process` (BOP), `prod_part_finishing`,
  `prod_part_attachment` (artwork), `prod_layout_group` (layout ProDev). **Belum ada**: dieline di `prod_part`,
  bahan per part, BOM, induk rakitan + jenis join, versi/cabang product.
- Izin user 2026-09-29: perubahan alur di zona beku Inquiry/ProDev/ProCost untuk keperluan pedoman ini **diizinkan**,
  asalkan urutan Inquiry → ProDev → ProCost tetap.

## 9. Aturan layar (user 2026-09-29)

- **Setiap layar input dimulai dari GRID** (DevLib; pilih keluarga yang cocok: Grid for Master / Pre-Data Entry / Report),
  lalu form: **+ New Record → isi → Simpan (DRAFT, bisa diedit & dihapus) → Confirm** (terkunci) → sesudahnya hanya
  **Cancel** (+ Print). Pakai istilah **Confirm** (bukan Submit) supaya mindset user tidak berubah.
- **Tidak ada bar alur di atas layar** (user 2026-10-02: membuat layar jelek). Alur order — Inquiry → Product Development →
  Product Costing → Priced Inquiry → Quotation (WIN → Master Product) → Customer Order → Shipping Schedule → Post to
  Production — ditulis di **Help**. Teks penjelasan per layar yang dulu tampil di bar (bahan Help):
  - `quotation`: Quotation = official offer to the customer. Confirm locks it; WIN creates the Customer Order and the Master Product.
  - `product-acc`: ACC Digital comes from the CTP sample print (usually after the customer PO) and is REQUIRED before printing.
  - `master-product`: Master Product is created when a Quotation is WON; Revision and Recalculation start again from Inquiry.
  - `inquiry-revise`: Revision = a new derived product. The changes below flow to Product Development and Product Costing so they know what must be redone.
  - `inquiry-recalc`: Recalculation = same product, new price. After Sales confirms it goes straight to Product Costing (no Product Development), then the new price returns here.
  - `customer-order`: Customer Order = the order is confirmed. Every product line needs a due date; Confirm creates the default shipping schedule.
  - `cpdel`: Every delivery line holds two promises that must match exactly: the slot agreed with the customer and the time production commits to be ready.
- **ACC Digital / Proof Digital = dokumen CETAK.** Pencatatan ACC customer (tanggal & jam, penyetuju, saluran, bukti)
  ada di **form ProDev**, dengan **tombol khusus "Print ACC / Proof Digital"** di form itu. Tidak ada menu terpisah.
- **Print & Email di setiap form (✅ dibangun 2026-09-29)** = objek DevLib **`x-dl::doc-share`** (`Devlib\Components\DocShare`
  + JS `DocShare`): tombol **Print ▾** → PDF, JPEG, HTML online (link tanpa login `/d/{token}`), Printer; semua hasil
  membawa **QR** ke salinan online (snapshot saat dicetak, `tm2sys.sys_doc_share`). Tombol **Email** → To/CC/BCC/Subjek/
  Pesan + pilih: link online, dokumen (gambar) di isi e-mail, lampiran PDF; tercatat di `sys_doc_email`. Layar cukup
  menaruh 1 tag dan menunjuk area dokumennya (`target`). Masa berlaku link = konstanta `DOC_SHARE_DAYS` (0 = tanpa batas).
  Sudah terpasang: Master Product, Inquiry Rekalkulasi, Inquiry Revisi, ACC / Proof Digital.
- Halaman login tmerp.test & mockup.test **tanpa daftar akun UAT** (dimatikan 2026-09-29 di `.env.mockup`).
- (Nanti) Pendaftaran akun: tanyakan **nama lengkap** dulu untuk dicek keberadaan user-nya.
- Backup & disaster recovery: `docs/dr_backup_guide.md`.
