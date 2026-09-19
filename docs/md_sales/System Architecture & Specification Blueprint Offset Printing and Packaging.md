# README

\# Packaging & Offset Printing ERP — System Architecture & Specification Blueprint

Repositori dokumentasi ini memuat spesifikasi teknis, arsitektur data, formulasi matematis, dan pustaka prompt AI untuk pembangunan sistem ERP industri manufaktur \*\*Offset Printing & Folding Carton Packaging\*\*.

Arsitektur sistem dirancang menggunakan pendekatan \*\*Modular Clean Architecture\*\*, memisahkan batasan domain antara Commercial Sales, Structural Packaging Engineering (CAD/Imposition), Product Costing (HPP Calculation Engine), serta Shop-Floor Execution (Master Card Pabrik).

\---

\#\# 1\. Alur Bisnis Inti (Core Business Flow)

Sistem mengikat seluruh siklus transaksi dan operasional dalam alur terintegrasi:

inqprod \-\> prodev \-\> procost \-\> CO \-\> CPDEL

1\. \*\*inqprod (Inquiry Product):\*\* Gerbang komersial pencatatan kebutuhan customer oleh tim Sales (NEW, RECALC, REVISE).  
2\. \*\*prodev (Product Development):\*\* Evaluasi teknis CAD, kalkulasi bentangan parametrik, tata letak imposisi lembar cetak, validasi arah serat kertas (grain direction), dan penentuan pisau pond (die-cut tooling).  
3\. \*\*procost (Product Costing):\*\* Engine kalkulasi HPP/COGS per komponen part (Material Kertas, Corrugated Sheet, Ongkos Mesin, Finishing, dan Tooling) serta pembentukan harga jual komersial.  
4\. \*\*CO (Customer Order):\*\* Konfirmasi pemesanan resmi (Sales Order) yang mengunci spesifikasi teknis menjadi Surat Perintah Kerja (SPK).  
5\. \*\*CPDEL (Customer Plan Delivery):\*\* Pengaturan jadwal kirim bertahap (blanket order / call-off), alokasi gudang barang jadi, dan logistik pengiriman.

\---

\#\# 2\. Peta Dokumen Spesifikasi (Documentation Map)

Seluruh berkas spesifikasi teknis dipecah ke dalam modul-modul independen agar mempermudah pemrosesan pada LLM (Google AI Studio) tanpa mengalami penurunan konteks:

\- README.md : Master Index & Peta Navigasi Sistem  
\- 00\_SYSTEM\_FOUNDATION.md : Standar Sistem, tm3\* Multi-Schema, DDL, Audit Footprint & Form UI Standard  
\- 01\_SALES\_INQPROD.md : Modul Part 1: Sales Inquiry, Onboarding Cards & Visual Catalog  
\- 02\_PRODEV\_ENGINE.md : Modul Part 1: CAD Dieline, Nesting & Imposition Engine  
\- 03\_PRODEV\_MASTER\_CARD.md : Output Produksi: Kartu Induk Pabrik & Blueprint Mesin Plong  
\- 04\_PROCOST\_TAXONOMY\_DDL.md : Modul Part 2: Skema DDL Database, Tipe Part & Kamus Field Scriptcase  
\- 05\_PROCOST\_CALC\_ENGINE.md : Modul Part 2: Formulasi Matematis 10 Sesi & Event Ajax calc\_cogs()  
\- 06\_PROMPT\_PIPELINE\_AI.md : Kumpulan Master Prompt Bahasa Inggris untuk Google AI Studio

\---

\#\# 3\. Matriks Klasifikasi Tipe Part (part\_type)

Sistem menerapkan aturan kalkulasi biaya berdasarkan klasifikasi bahan baku komponen part:

| Tipe Part | Label Sistem | Substrat & Bahan Baku Utama | Default Pisau Pond | Rute Biaya Aktif di Procost |  
| :---: | :--- | :--- | :---: | :--- |  
| \*\*DC\*\* | DC — Duplex Carton | Karton padat (Duplex, Ivory, Art Carton). | Rp 900.000 | Plano Kertas \+ Cetak Offset \+ Coating \+ Pisau \+ Plong \+ Lem. |  
| \*\*DC+SF\*\* | DC \+ Single Face | Duplex dilaminasi ke lembaran gelombang 2 lapis (Flute E/F). | Rp 900.000 | Kertas Duplex \+ Cetak \+ Single Face EF (J37) \+ Lam SF (J38) \+ Pisau \+ Plong \+ Lem. |  
| \*\*DC+SHEET\*\* | DC \+ Laminasi Sheet | Duplex dilaminasi ke lembaran karton boks jadi (corrugated sheet utuh). | Rp 900.000 | Kertas Duplex \+ Cetak \+ Laminasi Sheet Flat (J36) \+ Pisau \+ Plong \+ Lem. |  
| \*\*KRAFT\*\* | KRAFT — Kraft Box | Kertas kraft cokelat tebal (perhitungan basis Rp/kg). | Rp 1.500.000 | Kertas Kraft (kg) \+ Cetak \+ Pisau Kraft \+ Plong \+ Lem. |  
| \*\*SHEET\*\* | SHEET — Outer Box / OB | Lembaran karton boks gelombang polos/flexo. | Rp 0 | Sheet Corrugated (m2) \+ Cetak Flexo \+ Slitter \+ Slotter \+ Join Lem/Kawat. |  
| \*\*SHEET\_DIGITAL\*\* | SHEET DIGITAL — OB Digital | Lembaran gelombang khusus cetak digital. | Rp 0 | Mengikuti basis formula SHEET untuk lini cetak digital. |

\---

\#\# 4\. Standar Penomoran Dokumen (Document Numbering)

Format terpadu: \*\*\[PREFIX\]\[YY\]\[NNNNN\]\*\*

\- \*\*PREFIX\*\*: Kode dokumen 2 huruf (IQ, QU, CO, DS, MC).  
\- \*\*YY\*\*: 2 digit tahun kalender (contoh: 26 untuk tahun 2026).  
\- \*\*NNNNN\*\*: 5 digit running counter (00001–99999), reset otomatis setiap 1 Januari pukul 00:00:00 UTC+7.  
\- Suffix revisi: IQ2600001-R01 (khusus tipe inkuiri REVISE, tidak memakan counter baru).  
\- Suffix multi-part: IQ2600001-P01 (Body), IQ2600001-P02 (Cap/Lid).

# 00\_SYSTEM\_FOUNDATION

\# 00\_SYSTEM\_FOUNDATION.md  
\#\# GLOBAL SYSTEM ARCHITECTURE, NUMBERING CONVENTIONS, STRUCTURAL GOVERNANCE, AUDIT FOOTPRINT, & UI COMPONENT STANDARDS  
\#\#\# Module: Foundation & Common Engine Specifications

\---

\#\# 1\. GLOBAL SYSTEM ARCHITECTURE & DOMAIN WORKFLOW

\#\#\# 1.1. End-to-End Pipeline  
Sistem ERP Offset Printing & Packaging mengintegrasikan seluruh tahapan manufaktur secara terstruktur dan berurutan:

inqprod \-\> prodev \-\> procost \-\> CO \-\> CPDEL

1\. \*\*inqprod (Inquiry Product):\*\* Gerbang komersial intake permintaan pelanggan atau prospek baru oleh tim Sales (NEW, RECALC, REVISE).  
2\. \*\*prodev (Product Development):\*\* Evaluasi rekayasa kemasan, kalkulasi bentangan parametrik (CAD/Dieline), imposisi lembar cetak (plano-to-pieces), penentuan pisau pond (die-cut tooling), dan penerbitan dokumen kerja fisik (Master Card / Kartu Induk).  
3\. \*\*procost (Product Costing):\*\* Engine kalkulasi HPP/COGS berbasis Bill of Materials (BOM) kertas/substrat, Bill of Operations (BOP) ongkos kerja mesin, amortisasi tooling (CTP plate, pisau pond, film), dan struktur margin komersial.  
4\. \*\*CO (Customer Order):\*\* Pengesahan pesanan penjualan resmi (Sales Order) dari hasil penawaran harga (Quotation), verifikasi limit kredit, dan penerbitan Surat Perintah Kerja (SPK).  
5\. \*\*CPDEL (Customer Plan Delivery):\*\* Pengelolaan jadwal pengiriman bertahap (blanket order call-off), alokasi stok gudang barang jadi (Finished Goods), dan sinkronisasi logistik.

\---

\#\#\# 1.2. Operational Dual-Modes  
\- \*\*Mode \#1 (Integrated & Automated):\*\* Pemrosesan langsung (Straight-Through Processing / STP). Perubahan parameter dimensi memicu kalkulasi background engine secara otomatis untuk imposisi, penataan nesting, dan perolehan HPP.  
\- \*\*Mode \#2 (Manual with Formula):\*\* Antarmuka kalkulasi semi-otomatis dengan input terbuka (spreadsheet-style). Memberikan keleluasaan bagi penaksir biaya (Cost Estimator) untuk menentukan variabel mesin, rasio potong plano, margin trim, dan biaya proses secara manual.

\---

\#\# 2\. STANDAR PENOMORAN DOKUMEN (AUTO-SEQUENCE ENGINE)

\#\#\# 2.1. Sintaks Utama Penomoran Transaksional  
\[PREFIX\]\[YY\]\[NNNNN\]

\- \*\*PREFIX (2 Karakter):\*\* Kode klasifikasi modul dokumen.  
\- \*\*YY (2 Digit):\*\* Dua digit tahun kalender aktif (contoh: 26 untuk tahun 2026).  
\- \*\*NNNNN (5 Digit):\*\* Counter berurutan dengan penambahan nol di depan (00001–99999) yang wajib di-reset otomatis menjadi 00001 setiap tanggal 1 Januari pukul 00:00:00 UTC+7.

\#\#\# 2.2. Sintaks Suffix Ekstensi  
\- \*\*Suffix Revisi Desain / Inkuiri:\*\* \[BASE\_NO\]-R\[XX\] (contoh: IQ2600058-R01, IQ2600058-R02).  
\- \*\*Suffix Multi-Part Assembly:\*\* \[BASE\_NO\]-P\[XX\] (contoh: IQ2600058-P01 untuk Badan/Body, IQ2600058-P02 untuk Tutup/Cap, IQ2600058-P03 untuk Partisi).

\#\#\# 2.3. Matriks Penomoran Dokumen ERP

| Domain Modul | Tipe Dokumen | Prefix Standar | Contoh Format | Perilaku Counter & Reset | Keterangan Operasional |  
| :--- | :--- | :---: | :--- | :--- | :--- |  
| \*\*Sales\*\* | Inquiry Product | IQ | IQ2600001 | Reset tahunan | Dokumen awal intake Sales (NEW, RECALC, REVISE). |  
| \*\*Sales\*\* | Inquiry Revision | IQ | IQ2600001-R01 | Mengikuti ID Induk | Khusus inkuiri bertipe REVISE. |  
| \*\*Commercial\*\* | Sales Quotation | QU (alt: QUO) | QU2600001 | Reset tahunan | Surat penawaran harga resmi (kompatibilitas format QUO). |  
| \*\*Sales\*\* | Customer Order | CO | CO2600001 | Reset tahunan | Pesanan penjualan resmi dari PO pelanggan. |  
| \*\*Engineering\*\* | Dieline / CAD Master | DS | DS2600001 | Running sequence | Nomor gambar bentangan dan pisau CAD master. |  
| \*\*Shop-Floor\*\* | Master Card / Induk | MC | MC2600001 | Reset tahunan | Surat perintah kerja fisik yang dibawa operator mesin. |  
| \*\*Tooling\*\* | Master Pisau Pond | PS | PS2600001 | Running sequence | Nomor registrasi fisik pisau plong di rak tooling. |  
| \*\*Logistics\*\* | Plan Delivery | DL | DL2600001 | Reset tahunan | Dokumen jadwal rencana pengiriman barang. |

\---

\#\# 3\. ATURAN KESELAMATAN STRUKTURAL (STRUCTURAL SAFETY & GRAIN GOVERNANCE)

\#\#\# 3.1. Arah Serat Kertas (Paper Grain Direction Rule)  
\- \*\*Aturan Utama:\*\* Garis rel atau lipatan utama kemasan karton (primary folding scorelines) wajib sejajar (||) dengan arah serat kertas.  
\- \*\*Dampak Kegagalan Serat:\*\* Jika garis rel melintang tegak lurus terhadap serat kertas, karton berisiko retak (cracking) saat dilipat di mesin folder-gluer, lapisan laminasi mudah lepas, serta kekuatan tekan kotak menurun.  
\- \*\*Klasifikasi Bahan:\*\*  
  \- LG (Long Grain): Arah serat sejajar dengan dimensi sisi yang lebih panjang (misal plano 79 x 109 cm LG, serat sejajar sisi 109 cm).  
  \- SG (Short Grain): Arah serat sejajar dengan dimensi sisi yang lebih pendek.  
\- \*\*Sistem Peringatan Otomatis:\*\*    
  Jika posisi bentangan diputar (90° atau 270°) sehingga garis rel berlawanan arah serat plano, sistem memunculkan peringatan:  
  \> PERINGATAN ARAH SERAT: Garis lipatan utama berlawanan dengan arah serat kertas\! Berisiko tinggi karton pecah/retak saat proses lipat lem.

\---

\#\#\# 3.2. Penanganan Bentangan Jumbo (Single Joint vs. Double Joint)

1\. \*\*Single Joint (Karton 1 Keping / Normal Tube):\*\*  
   \- Standar kemasan karton di mana seluruh 4 panel utama (L1, W1, L2, W2) dan 1 lidah lem utama (glue flap) berada dalam satu bentangan utuh.  
   \- Diproduksi menggunakan satu lembar cetak dan hanya membutuhkan 1 kali pengeleman sambungan.  
2\. \*\*Double Joint (Karton 2 Keping / Two-Piece Split Box):\*\*  
   \- \*\*Pemicu Otomatis:\*\* Aktif jika bentangan total melebihi batas cetak mesin offset atau ukuran plano maksimum yang tersedia:  
     Flat Width \> Max Machine Print Width ATAU Flat Height \> Max Sheet Height  
   \- \*\*Pemecahan Komponen:\*\* Bentangan dipisah menjadi 2 keping:  
     \- Keping A: Panel L1 \+ Panel W1 \+ Lidah Lem A.  
     \- Keping B: Panel L2 \+ Panel W2 \+ Lidah Lem B.  
   \- \*\*Dampak Produksi & Costing:\*\*  
     \- Membutuhkan 2 set plat CTP atau layout cetak kombinasi.  
     \- Memerlukan pembuatan 2 pisau pond atau pisau pond ganda.  
     \- Memerlukan 2 kali pengeleman sambungan pada mesin two-piece folder-gluer atau pengeleman manual.  
   \- \*\*Anotasi Master Card:\*\* Wajib mencantumkan label:  
     \> PERINGATAN BENTANGAN: DOUBLE JOINT (2 KEPING SAMBUNGAN) — Memerlukan 2 titik pengeleman pada sambungan flap A dan flap B.

\---

\#\# 4\. CODED AUDIT FOOTPRINT ENGINE (PRINTED FOOTER SECURITY STAMP)

\#\#\# 4.1. Format Standar Struktur Kode (5 Segmen)  
\[MOD5\]-\[DATETIME14\]-\[USER4\]-\[HOST3\]-\[IP\]

Contoh tampilan nyata pada footer cetakan:  
00182-20260904093712-3012-017-1921681254

\#\#\#\# Rincian Anatomi Digit:

| Segmen | Panjang | Contoh | Makna & Dekonstruksi Digit |  
| :--- | :---: | :---: | :--- |  
| \*\*1. MOD5\*\* | 5 digit | 00182 | • \*\*Digit 1 (Status Validitas Dokumen):\*\*\<br\>0 \= DRAFT / OPEN (Tidak sah untuk produksi)\<br\>1 \= CONFIRMED (Terkonfirmasi & sah)\<br\>2 \= CANCELLED / REJECTED (Batal)\<br\>3 \= CLOSED (Terkunci menjadi Customer Order)\<br\>• \*\*Digit 2–3 (Kode Modul ERP):\*\*\<br\>01 \= Sales Inquiry (inqprod)\<br\>02 \= Product Development & CAD (prodev)\<br\>03 \= Costing Engine (procost / HPP)\<br\>04 \= Customer Order & Quotation (CO)\<br\>05 \= Production Execution (SPK)\<br\>06 \= Delivery Plan (CPDEL)\<br\>• \*\*Digit 4–5 (Kode Tipe Dokumen):\*\*\<br\>Pada Modul 02: 01 \= CAD Dieline, 02 \= Imposisi Plano, 82 \= Master Card / Blueprint Pond.\<br\>Pada Modul 03: 01 \= Rincian HPP Internal, 02 \= Ringkasan Costing.\<br\>Pada Modul 04: 01 \= Penawaran Resmi (Quotation), 02 \= Surat Konfirmasi Pesanan (CO). |  
| \*\*2. DATETIME14\*\* | 14 digit | 20260904093712 | Format YYYYMMDDHHmmss (Waktu presisi detik saat dokumen dicetak). |  
| \*\*3. USER4\*\* | 4 digit | 3012 | • \*\*Digit 1 (Level/Otoritas Pengguna):\*\*\<br\>1 \= Super Administrator / Direksi\<br\>2 \= Plant Manager / Kepala Pabrik / QA Lead\<br\>3 \= Technical Staff (Cost Estimator / Packaging Engineer)\<br\>4 \= Commercial Staff (Sales / CS)\<br\>5 \= Floor Staff (Operator Cetak / Operator Pond / Gudang)\<br\>• \*\*Digit 2–4 (Nomor Urut Pengguna Terdaftar):\*\*\<br\>Contoh 012 \= User Wiwik (ID: 12). |  
| \*\*4. HOST3\*\* | 3 digit | 017 | Nomor registrasi unit komputer terminal di pabrik (contoh: 017 \= PC Meja Estimator 2). |  
| \*\*5. IP\*\* | 10–12 digit | 1921681254 | Alamat IP terminal tanpa tanda titik (contoh dari 192.168.1.254). |

\---

\#\#\# 4.2. Implementasi Engine (PHP Service)

\`\`\`php  
\<?php  
namespace App\\Services;

class AuditFootprintEngine  
{  
    public static function generateStamp(  
        int $docStatus,  
        int $moduleNo,  
        int $docType,         \\DateTime$timestamp,  
        int $userLevel,  
        int $userSeq,  
        int $hostNo,  
        string $ipAddress  
    ): string {  
        $mod5    \= sprintf("%d%02d%02d", $docStatus,$moduleNo, $docType);$dt14    \= $timestamp-\>format('YmdHis');$user4   \= sprintf("%d%03d", $userLevel,$userSeq);  
        $host3   \= sprintf("\\%03d", $hostNo);  
        $cleanIp \= str\_replace('.', '',$ipAddress);

        return "{$mod5}-{$dt14}-{$user4}-{$host3}-{$cleanIp}";  
    }

    public static function parseStamp(string $stamp): array  
    {  
        $parts \= explode('-',$stamp);  
        if (count($parts) \< 5\) {  
            throw new \\InvalidArgumentException("Format stamp footprint tidak valid.");  
        }

        return \[  
            'doc\_status\_code' \=\> (int) substr($parts\[0\], 0, 1),  
            'module\_no'       \=\> (int) substr($parts\[0\], 1, 2),  
            'doc\_type'        \=\> (int) substr($parts\[0\], 3, 2),  
            'printed\_at'      \=\> \\DateTime::createFromFormat('YmdHis', $parts\[1\])-\>format('Y-m-d H:i:s'),  
            'user\_level'      \=\> (int) substr($parts\[2\], 0, 1),  
            'user\_seq\_id'     \=\> (int) substr($parts\[2\], 1, 3),  
            'workstation\_id'  \=\> (int) $parts\[3\],  
            'ip\_raw'          \=\> $parts\[4\],  
        \];  
    }  
}

# 01\_SALES\_INQPROD

\# 01\_SALES\_INQPROD.md  
\#\# SALES MODULE: GUIDED INQUIRY (inqprod), PARAMETRIC PACKAGING CATALOG, & LIVE DIELINE  
\#\#\# Module Specification: Part 1 (Sales & Pro-Dev Intake)

\---

\#\# 1\. OVERVIEW & BUSINESS WORKFLOW

Modul Inquiry Product (inqprod) adalah pintu gerbang awal pencatatan permintaan pelanggan atau calon pelanggan oleh tim Sales\[cite: 1\]. Alur kerja transaksi mengikuti rangkaian standar ERP Versi 3:

inqprod (Sales) \-\> prodev (CAD/Engineering) \-\> procost (HPP) \-\> CO \-\> CPDEL

1\. Ownership & Trigger: Dibuat oleh Sales Officer saat menerima brief spesifikasi kemasan dari pelanggan.  
2\. Validation & Routing Gate: Dokumen berstatus DRAFT belum dikirim ke divisi lain\[cite: 1\]. Hanya inkuiri dengan status CONFIRMED yang secara otomatis diteruskan ke pipeline prodev untuk evaluasi CAD dan uji layout cetaknya\[cite: 1\].

\---

\#\# 2\. UI/UX ENTRY POINT: GUIDED SALES GRID DASHBOARD

Antarmuka dirancang khusus dengan pendekatan guided wizard agar staf sales pemula dapat mengeksekusi tugas secara mandiri tanpa pelatihan teknis mendalam.

\#\#\# 2.1. Three-Column Action Cards (Panduan Mandiri)  
Di bagian atas layar, tersedia 3 kartu aktivasi dengan aksen warna khas\[cite: 1\]:  
1\. Kartu 1: Produk Baru (NEW)\[cite: 1\]  
   \- Badge: NEW (Soft Blue / Cyan)\[cite: 1\].  
   \- Subtitle: "Belum pernah dibuat. Sales menentukan tipe produk, dimensi, bahan, dan finishing dari nol."\[cite: 1\]  
   \- Checklist Operasional: "Perlu: tipe produk \+ dimensi \+ bahan"  
   \- Indikator Alur: "Alur: Sales \-\> Design \-\> Costing"\[cite: 1\]  
   \- Tombol: \[Pilih data tersimpan\] (Ghost Secondary) dan \[+ Buat data baru\] (Primary Blue).  
2\. Kartu 2: Hitung Ulang Harga (RECALC)\[cite: 1\]  
   \- Badge: RECALC (Soft Indigo / Purple)\[cite: 1\].  
   \- Subtitle: "Produk & layout sudah ada, tidak ada yang berubah. Hanya perlu harga baru."\[cite: 1\]  
   \- Checklist Operasional: "Perlu: pilih produk existing \+ qty"\[cite: 1\]  
   \- Indikator Alur: "Alur: Sales \-\> Costing (lewati Design)"\[cite: 1\]  
   \- Tombol: \[Pilih data tersimpan\] (Ghost Secondary) dan \[+ Buat data baru\] (Primary Purple).  
3\. Kartu 3: Ubah Spesifikasi (REVISE / Revisi)\[cite: 1\]  
   \- Badge: REVISE (Soft Amber / Orange)\[cite: 1\].  
   \- Subtitle: "Produk sudah ada tapi ada yang berubah: dimensi, bahan, warna, atau finishing."\[cite: 1\]  
   \- Checklist Operasional: "Perlu: pilih produk existing \+ apa yang berubah"\[cite: 1\]  
   \- Indikator Alur: "Alur: Sales \-\> Design \-\> Costing"\[cite: 1\]  
   \- Tombol: \[Pilih data tersimpan\] (Ghost Secondary) dan \[+ Buat data baru\] (Primary Orange).

\#\#\# 2.2. Inkuiri Tersimpan (Data Grid Table)  
Tabel daftar inkuiri berada langsung di bawah 3 kartu panduan:  
1\. Header Filter & Search:  
   \- Global Search Bar: Pencarian live berbasis No. Inkuiri, Nama Produk, atau Nama Pelanggan\[cite: 1\].  
   \- Dropdown Status: Filter Semua Status, DRAFT, SUBMITTED, CONFIRMED, PROCESSED\[cite: 1\].  
   \- Dropdown Tipe: Filter Semua Tipe, NEW, RECALC, REVISE\[cite: 1\].  
2\. Kolom Tabel:  
   \- Produk / Customer: Avatar Ikon \+ Nama Produk (font bold) \+ Subtitle Nama Customer\[cite: 1\].  
   \- No. Inkuiri: Format standar, contoh: IQ2690058.  
   \- Tanggal: Format date YYYY-MM-DD\[cite: 1\].  
   \- Tipe Produk: Straight Tuck End (STE), Sleeve, Mailer, dll.  
   \- Qty Tier Utama: Volume pesanan (contoh: 1.000, 10.000 pcs)\[cite: 1\].  
   \- Status: Badge warna (Draft \= Abu, Submitted \= Biru, Confirmed \= Hijau)\[cite: 1\].  
   \- Jenis: Badge kode (NEW, RECALC, REVISE)\[cite: 1\].  
   \- Aksi: Tombol kursor langsung atau pilih baris dengan tombol aksi \[Buka Inkuiri \-\>\].

\---

\#\# 3\. PARAMETRIC CONSTRUCTION CATALOG & DIELINE ENGINE

\#\#\# 3.1. Pacdora-Style Visual Catalog & Preset Pasaran  
1\. Katalog Tipe Produk (m\_product\_type):  
   \- Straight Tuck End (STE)  
   \- Reverse Tuck End (RTE)  
   \- Auto / Crash Lock Bottom\[cite: 1\]  
   \- Sleeve & Inner Tray  
   \- Gift Box (Lid & Base)  
   \- Mailer Box (CORRUGATED)  
   \- Hang Tag, Kalender, Brosur/Flyer\[cite: 1\]  
2\. Preset Ukuran Pasaran:  
   \- Selector yang secara otomatis mengisi data dimensi pasaran industri:  
     \- Serum 30ml (40 x 40 x 110 mm)  
     \- Skincare 50ml (50 x 50 x 150 mm)  
     \- Kosmetik Sedang (60 x 60 x 100 mm)  
     \- Dus Obat Strip (105 x 32 x 22 mm)  
     \- Kotak Teh (70 x 70 x 120 mm)

\#\#\# 3.2. Real-Time Dimension Modeling & Flat Blank (Bentangan)  
Saat ukuran dipilih atau diinput manual, engine menghitung luas bentangan secara real-time:  
1\. Input Dimensi Utama: Panjang (L), Lebar (W), Tinggi/Kedalaman (D) dalam satuan mm\[cite: 1\].  
2\. Toleransi Konstruksi Khas:  
   \- Lidah Lem (Glue Flap): Default 13 \- 15 mm\[cite: 1\].  
   \- Top Tuck & Bottom Tuck: Default 15 mm.  
   \- Dust Flap (Kipasan Samping): Default 12 \- 14 mm.  
   \- Overflow / Bleed Margin: Default 3.00 mm\[cite: 1\].  
3\. Output Bentangan (Flat Blank) untuk Costing:  
   \- Flat Width (mm): Rumus bentangan lebar total dari 4 panel utama \+ lidah lem\[cite: 1\].  
   \- Flat Height (mm): Rumus bentangan tinggi total dari panel tinggi \+ tuck flap atas & bawah\[cite: 1\].  
   \- Flat Area (mm2): Luas fisik karton yang ditaksir\[cite: 1\].  
   \- Toggle Mode: Auto (rumus engine) vs Manual (override ukuran untuk kotak kustom).

\#\#\# 3.3. Live 2D Vector Dieline Canvas  
Canvas interaktif berbasis SVG/Canvas yang menampilkan garis pisau real-time dengan standar warna CAD:  
1\. Garis Merah (\#FF0000): Pisau Potong (Outer Cut Line).  
2\. Garis Hijau (\#00FF00): Pisau Lipat / Rel (Crease / Score Line).  
3\. Garis Abu-abu putus-putus: Batas Area Bleed / Overflow.  
4\. Garis Biru (\#0000FF): Perforasi / Lubang Jilid / Window Punch.

\---

\#\# 4\. MULTI-PART ASSEMBLY & JOINT CONFIGURATION

\#\#\# 4.1. Multi-Part Assembly Builder  
Untuk produk kemasan yang memiliki beberapa komponen fisik (contoh: Rigid Box \= Body \+ Lid \+ Partisi)\[cite: 1\]:  
\- Setiap komponen memiliki parameter dimensi, dieline, dan bagian bahan sendiri\[cite: 1\].  
\- Penomoran child part: \[INQUIRY\_NO\]-P\[nn\] (contoh: IQ2600001-P01 untuk badan, IQ2600001-P02 untuk tutup).

\#\#\# 4.2. Single vs. Double Joint Engine  
Saat ukuran bentangan karton melampaui mesin cetak offset maksimum:  
1\. Single Joint: Bentangan 1 keping standar dengan 1 lidah lem\[cite: 1\].  
2\. Double Joint: Bentangan dipecah otomatis menjadi 2 keping (Piece A & Piece B) dengan 2 lidah lem yang berlawanan\[cite: 1\]. Mengaktifkan dual-pass lem di Costing dan notifikasi khusus di Master Card\[cite: 1\].

\---

\#\# 5\. DATABASE SCHEMA \- MODUL SALES (tm3sdi)

\`\`\`sql  
SET FOREIGN\_KEY\_CHECKS \= 0;  
CREATE DATABASE IF NOT EXISTS \`tm3sdi\` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4\_unicode\_ci;  
USE \`tm3sdi\`;

\-- View L1: Proyeksi Customer dari tm3mst  
CREATE OR REPLACE VIEW \`tm3sdi\`.\`v1\_m\_customer\` AS  
SELECT id\_customer, customer\_code, customer\_name, customer\_phone, activate  
FROM \`tm3mst\`.\`cust\_customer\`;

\-- 1\. Tabel Inkuiri Induk (Header Assembly)  
CREATE TABLE IF NOT EXISTS \`tm3sdi\`.\`iqp\_inquiry\` (  
  \`inquiry\_id\` BIGINT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,  
  \`inquiry\_no\` VARCHAR(32) NOT NULL UNIQUE,  
  \`inquiry\_type\` ENUM('NEW', 'RECALC', 'REVISE') NOT NULL DEFAULT 'NEW',  
  \`revision\_no\` INT UNSIGNED NOT NULL DEFAULT 0,  
  \`customer\_id\` BIGINT UNSIGNED NULL,  
  \`customer\_name\` VARCHAR(255) NOT NULL,  
  \`product\_name\` VARCHAR(255) NOT NULL,  
  \`status\` ENUM('DRAFT', 'SUBMITTED', 'CONFIRMED', 'PROCESSED', 'CANCELLED') NOT NULL DEFAULT 'DRAFT',  
  \`is\_multipart\` TINYINT(1) NOT NULL DEFAULT 0,  
  \`qty\_tier\_1\` INT UNSIGNED NOT NULL DEFAULT 0,  
  \`qty\_tier\_2\` INT UNSIGNED NULL DEFAULT 0,  
  \`qty\_tier\_3\` INT UNSIGNED NULL DEFAULT 0,  
  \`global\_notes\` TEXT NULL,  
  \`created\_by\` VARCHAR(100) NOT NULL,  
  \`created\_at\` TIMESTAMP NOT NULL DEFAULT CURRENT\_TIMESTAMP,  
  \`updated\_at\` TIMESTAMP NOT NULL DEFAULT CURRENT\_TIMESTAMP ON UPDATE CURRENT\_TIMESTAMP,  
  INDEX \`idx\_inq\_no\` (\`inquiry\_no\`),  
  INDEX \`idx\_status\` (\`status\`)  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4\_unicode\_ci;

\-- 2\. Tabel Detail Part / Komponen Inkuiri  
CREATE TABLE IF NOT EXISTS \`tm3sdi\`.\`iqp\_part\` (  
  \`part\_id\` BIGINT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,  
  \`inquiry\_id\` BIGINT UNSIGNED NOT NULL,  
  \`part\_code\` VARCHAR(32) NOT NULL,  
  \`part\_name\` VARCHAR(100) NOT NULL,  
  \`ptd\` ENUM('DC', 'DC+SF', 'DC+SHEET', 'KRAFT', 'SHEET', 'SHEET\_DIGITAL') NOT NULL DEFAULT 'DC',  
  \`m\_product\_type\` VARCHAR(50) NOT NULL DEFAULT 'STE',  
  \`joint\_type\` ENUM('SINGLE\_JOINT', 'DOUBLE\_JOINT', 'MULTI\_JOINT') NOT NULL DEFAULT 'SINGLE\_JOINT',  
    
  \-- Dimensi Karton Jadi (mm)  
  \`dim\_length\_mm\` DECIMAL(8,2) NOT NULL DEFAULT 0.00,  
  \`dim\_width\_mm\` DECIMAL(8,2) NOT NULL DEFAULT 0.00,  
  \`dim\_height\_mm\` DECIMAL(8,2) NOT NULL DEFAULT 0.00,  
    
  \-- Toleransi & Flap (mm)  
  \`glue\_flap\_mm\` DECIMAL(6,2) NOT NULL DEFAULT 15.00,  
  \`top\_tuck\_mm\` DECIMAL(6,2) NOT NULL DEFAULT 15.00,  
  \`bottom\_tuck\_mm\` DECIMAL(6,2) NOT NULL DEFAULT 15.00,  
  \`dust\_flap\_mm\` DECIMAL(6,2) NOT NULL DEFAULT 12.00,  
  \`bleed\_mm\` DECIMAL(4,2) NOT NULL DEFAULT 3.00,  
    
  \-- Kalkulasi Bentangan (Flat Blank manual/auto)  
  \`is\_manual\_flat\` TINYINT(1) NOT NULL DEFAULT 0,  
  \`flat\_width\_mm\` DECIMAL(8,2) NOT NULL DEFAULT 0.00,  
  \`flat\_height\_mm\` DECIMAL(8,2) NOT NULL DEFAULT 0.00,  
  \`flat\_area\_mm2\` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,  
    
  \-- Substrat & Arah Serat  
  \`material\_type\` VARCHAR(50) NOT NULL,  
  \`grammage\_gsm\` SMALLINT UNSIGNED NOT NULL,  
  \`required\_grain\` ENUM('PARALLEL\_TO\_LENGTH', 'PARALLEL\_TO\_WIDTH') NOT NULL DEFAULT 'PARALLEL\_TO\_LENGTH',  
  \`dieline\_svg\_json\` LONGTEXT NULL,  
    
  CONSTRAINT \`fk\_inq\_part\` FOREIGN KEY (\`inquiry\_id\`) REFERENCES \`tm3sdi\`.\`iqp\_inquiry\` (\`inquiry\_id\`) ON DELETE CASCADE  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4\_unicode\_ci;

SET FOREIGN\_KEY\_CHECKS \= 1;

# 02\_PRODEV\_ENGINE

\# 02\_PRODEV\_ENGINE.md  
\#\# PACKAGING CAD, IMPOSITION & NESTING CALCULATION ENGINE  
\#\#\# Module: ProDev Engine & Layout Imposition

\---

\#\# 1\. PRODEV WORKSTATION GRID VIEW & WORKLIST  
Antarmuka kerja tim teknis ProDev untuk mengevaluasi inkuiri yang telah berstatus \`CONFIRMED\` dari Sales:

\#\#\# 1.1. Summary KPI Bar  
\* \*\*Total Inquiries to Process:\*\* Total antrean CAD yang masuk.  
\* \*\*Ready for Costing:\*\* Layout bentangan yang sudah tervalidasi dan siap dihitung HPP-nya.  
\* \*\*Action Needed:\*\* Layout dengan yield/efisiensi plano di bawah target (\< 75%) atau terjadi tabrakan arah serat (\*grain conflict\*).  
\* \*\*Pessimistic Lock Indicator:\*\* \`🔒 Locked by ProDev Engineer\` saat file CAD sedang diedit.

\#\#\# 1.2. Imposition Matrix & Calculation Models  
Engine menghitung dua skenario tata letak cetak secara otomatis:  
1\. \*\*Plano-to-Pieces (Mencari Kapasitas Muat):\*\*  
   \* Input: Ukuran plano standar yang dipilih (misal $79 \\times 109\\text{ cm}$) dan ukuran bentangan flat blank ($P \\times L$).  
   \* Output: Jumlah UP cetak maksimal yang muat, orientasi potong (potong 2, 4, 6), dan sisa waste trim.  
2\. \*\*Pieces-to-Plano (Mencari Ukuran Plano Paling Efisien):\*\*  
   \* Input: Ukuran bentangan dan target UP cetak per lembar bahan (misal 2 UP atau 4 UP).  
   \* Output: Rekomendasi dimensi plano khusus (\*custom sheet\*) yang meminimalkan buangan bahan.

\#\#\# 1.3. Machine Tolerances & Safety Margins  
\* \*\*Gripper Margin Mesin Cetak:\*\* $12.00\\text{ mm}$ (area tepi cengkeram mesin cetak offset yang tidak boleh ada gambar/pisau).  
\* \*\*Margin Keliling Non-Gripper:\*\* $8.00\\text{ mm}$.  
\* \*\*Jarak Antar-Pisau (Gutter / Inter-Carton Gap):\*\* $3.00\\text{ mm}$.  
\* \*\*Bleed Line:\*\* $3.00\\text{ mm}$.

# 03\_PRODEV\_MASTER\_CARD

\# 03\_PRODEV\_MASTER\_CARD.md  
\#\# SHOP-FLOOR PRODUCTION MASTER CARD ("KARTU INDUK") SPECIFICATION  
\#\#\# Module: Shop-Floor Traveler & Factory Floor Output

\---

\#\# 1\. TUJUAN & KONTRAK DOKUMEN  
Master Card (Kartu Induk) adalah dokumen Single Source of Truth (SSOT) fisik yang menyertai material di lantai produksi pabrik, dari gudang kertas, mesin cetak, mesin pond, hingga folder-gluer.

\#\# 2\. FORMAT KARTU INDUK (A4/A3 TRAVELER)  
1\. \*\*Header Dokumen:\*\*  
   \* No. Dokumen: \`MC\[YY\]\[NNNNN\]\`  
   \* No. Desain Dieline: \`DS\[YY\]\[NNNNN\]\`  
   \* Customer, Nama Produk, dan Tanggal Terbit  
   \* Dimensi Jadi Produk ($P \\times L \\times T\\text{ mm}$)  
2\. \*\*Spesifikasi Bahan Baku:\*\*  
   \* Jenis Kertas (Duplex / Ivory / Kraft / Corrugated Sheet)  
   \* Gramatur ($gsm$) & Ukuran Plano Kertas Cetak ($P \\times L\\text{ cm}$)  
   \* Ukuran Lembar Cetak / Potong ($P \\times L\\text{ cm}$) serta Jumlah UP Cetak  
3\. \*\*Dual-Canvas Layout Visualization:\*\*  
   \* \*\*Canvas A (Layout Cetak Offset):\*\*  
     \* Visual step-and-repeat posisi gambar pada lembar cetak.  
     \* Penempatan color bar control CMYK, register cross mark, dan gripper edge.  
   \* \*\*Canvas B (Layout Mesin Pond / Plong):\*\*  
     \* Blueprint pisau potong (merah) dan rel garis lipat (hijau).  
     \* Titik nicking (pengikat agar karton tidak rontok di mesin otomatis).  
     \* Arah punch mesin: \`Plong dari Belakang\` atau \`Plong dari Depan\`.  
     \* \*\*PANAH BESAR ARAH SERAT (GRAIN DIRECTION):\*\* Menunjukkan sumbu serat kertas.  
4\. \*\*Tabel Alur Proses Produksi (Standard Routing):\*\*  
   \* 1\. Potong Bahan (Guillotine)  
   \* 2\. Cetak Offset  
   \* 3\. Surface Finishing (Coating / Laminasi)  
   \* 4\. Mesin Pond (Die-Cut)  
   \* 5\. Kupas Rontokan (Stripping)  
   \* 6\. Lipat Lem (Folder-Gluer)  
   \* 7\. QC & Packing  
5\. \*\*Security Stamp Footer:\*\*  
   \* Menampilkan serial footprint: \`\[MOD5\]-\[DATETIME14\]-\[USER4\]-\[HOST3\]-\[IP\]\`.

# 04\_PROCOST\_TAXONOMY\_DDL

\# 04\_PROCOST\_TAXONOMY\_DDL.md  
\#\# PRODUCT COSTING TAXONOMY & MYSQL 8.X DDL REPOSITORY  
\#\#\# Module: Costing Structure & Schema Definitions

\---

\#\# 1\. KLASIFIKASI TIPE PART (\`part\_type\`)

1\. \*\*\`DC\` (Duplex Carton):\*\*  
   \* Folding carton murni tanpa gelombang (Duplex, Ivory, Art Carton).  
   \* Default Pisau: Rp 900.000. Flag Lam: \`0\`.  
2\. \*\*\`DC+SF\` (Duplex \+ Single Face):\*\*  
   \* Karton cetak duplex ditempel ke flute gelombang 2 lapis (\*Flute E/F\*).  
   \* Default Pisau: Rp 900.000. Flag Lam: \`1\`.  
3\. \*\*\`DC+SHEET\` (Duplex \+ Corrugated Sheet):\*\*  
   \* Karton cetak duplex ditempel ke lembaran sheet boks jadi utuh (\*Single Wall / Double Wall\*).  
   \* Default Pisau: Rp 900.000. Flag Lam: \`1\`.  
   \* Komponen sheet masuk via ongkos laminasi sheet flat ($J\_{36}$). Nilai \`c\_sheet\_cost\_pc \= 0\`.  
4\. \*\*\`KRAFT\` (Kraft Box):\*\*  
   \* Karton berbahan dasar kertas kraft (perhitungan basis Rp/kg).  
   \* Default Pisau: Rp 1.500.000. Flag Lam: \`0\`.  
5\. \*\*\`SHEET\` (Corrugated Sheet / Outer Box \- OB):\*\*  
   \* Lembaran karton boks gelombang polos/flexo untuk kardus luar.  
   \* Default Pisau: Rp 0\. Flag Lam: \`0\`.  
6\. \*\*\`SHEET\_DIGITAL\` (OB Digital):\*\*  
   \* Master box lembaran gelombang khusus cetak digital.  
   \* Default Pisau: Rp 0\. Flag Lam: \`0\`.

\---

\#\# 2\. DATABASE DDL (\`tm3hpp\`)

\`\`\`sql  
SET FOREIGN\_KEY\_CHECKS \= 0;  
CREATE DATABASE IF NOT EXISTS \`tm3hpp\` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4\_unicode\_ci;  
USE \`tm3hpp\`;

\-- Tabel Header Kalkulasi HPP  
CREATE TABLE IF NOT EXISTS \`hpp\` (  
  \`hpp\_id\` BIGINT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,  
  \`doc\_no\` VARCHAR(50) NULL UNIQUE,  
  \`doc\_date\` DATE DEFAULT (CURDATE()),  
  \`doc\_status\` VARCHAR(30) DEFAULT 'DRAFT',  
  \`i\_calc\_type\` VARCHAR(20) DEFAULT 'NEW',  
  \`customer\_id\` BIGINT UNSIGNED NULL,  
  \`customer\_name\` VARCHAR(255) NULL,  
  \`product\_name\` VARCHAR(255) NULL,  
  \`qty\_order\_pcs\` DECIMAL(18,2) DEFAULT 0.00,  
  \`is\_ppn\` TINYINT(1) DEFAULT 1,  
  \`ppn\_percent\` DECIMAL(5,2) DEFAULT 11.00,  
  \`margin\_percent\` DECIMAL(8,2) DEFAULT 0.00,  
  \`tolerance\_percent\` DECIMAL(8,2) DEFAULT 0.00,  
  \`rounding\_mode\` VARCHAR(30) DEFAULT 'NEAREST',  
  \`total\_material\_cost\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`total\_process\_cost\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`total\_finishing\_cost\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`total\_hpp\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`total\_after\_margin\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`total\_after\_ppn\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`price\_per\_pcs\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`stat\_confirm\` TINYINT UNSIGNED DEFAULT 0  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

\-- Tabel Detail Part Kalkulasi HPP  
CREATE TABLE IF NOT EXISTS \`hpp\_parts\` (  
  \`hpp\_parts\_id\` BIGINT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,  
  \`hpp\_id\` BIGINT UNSIGNED NOT NULL,  
  \`no\_urut\` TINYINT UNSIGNED DEFAULT 1,  
  \`qty\_part\` DECIMAL(12,2) DEFAULT 0.00,  
  \`part\_name\` VARCHAR(100) NULL,  
  \`part\_type\` ENUM('DC', 'DC+SF', 'DC+SHEET', 'KRAFT', 'SHEET', 'SHEET\_DIGITAL') DEFAULT 'DC',  
  \`gramatur\_id\` INT UNSIGNED NULL,  
  \`i\_dc\_persen\_override\` DECIMAL(5,2) NULL,  
  \`i\_panjang\_plano\_cm\` DECIMAL(10,4) DEFAULT 0.0000,  
  \`i\_lebar\_plano\_cm\` DECIMAL(10,4) DEFAULT 0.0000,  
  \`i\_cut\_per\_plano\` DECIMAL(8,4) DEFAULT 1.0000,  
  \`i\_isi\_per\_lb\` DECIMAL(8,4) DEFAULT 1.0000,  
  \`i\_jumlah\_warna\` TINYINT UNSIGNED DEFAULT 4,  
  \`supplier\_sheet\_code\` VARCHAR(20) NULL,  
  \`sheet\_rate\_id\` INT UNSIGNED NULL,  
  \`i\_sheet\_panjang\_cm\` DECIMAL(12,4) NULL,  
  \`i\_sheet\_lebar\_cm\` DECIMAL(12,4) NULL,  
  \`i\_lebar\_trim\_cm\` DECIMAL(12,4) NULL,  
  \`i\_isi\_per\_trim\` SMALLINT UNSIGNED DEFAULT 1,  
  \`i\_discount\_rm\` DECIMAL(5,4) DEFAULT 0.0000,  
  \`i\_ongkos\_cetak\_rim\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`i\_ongkos\_plong\_lb\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`i\_ongkos\_lem\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`i\_lam\_sheet\_flat\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`sf\_ef\_rate\_id\` INT UNSIGNED NULL,  
  \`i\_film\_faktor\` DECIMAL(8,4) DEFAULT 0.2500,  
  \`i\_pisau\_faktor\` DECIMAL(8,4) DEFAULT 0.2500,  
  \`i\_pisau\_rate\_rp\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`i\_ongkos\_plate\_warna\` DECIMAL(18,4) DEFAULT 90000.0000,  
  \`c\_area\_cetak\_cm2\` DECIMAL(18,4) NULL,  
  \`c\_paper\_cost\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`c\_sheet\_cost\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`c\_process\_cost\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`c\_tooling\_cost\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`c\_total\_coating\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`c\_sf\_ef\_cost\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  \`c\_total\_part\_cost\_pc\` DECIMAL(18,4) DEFAULT 0.0000,  
  INDEX (\`hpp\_id\`),  
  CONSTRAINT \`fk\_hpp\_parts\_parent\` FOREIGN KEY (\`hpp\_id\`) REFERENCES \`hpp\` (\`hpp\_id\`) ON DELETE CASCADE  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

\-- Tabel Child Coating Sub-Layer  
CREATE TABLE IF NOT EXISTS \`hpp\_parts\_coating\` (  
  \`parts\_coating\_id\` BIGINT UNSIGNED AUTO\_INCREMENT PRIMARY KEY,  
  \`hpp\_parts\_id\` BIGINT UNSIGNED NOT NULL,  
  \`coating\_type\` VARCHAR(50) NULL,  
  \`index\_coating\` DECIMAL(20,4) UNSIGNED NULL,  
  \`cost\_per\_lc\` DECIMAL(20,6) NULL,  
  \`cost\_per\_pc\` DECIMAL(20,4) NULL,  
  INDEX (\`hpp\_parts\_id\`),  
  CONSTRAINT \`fk\_coating\_part\` FOREIGN KEY (\`hpp\_parts\_id\`) REFERENCES \`hpp\_parts\` (\`hpp\_parts\_id\`) ON DELETE CASCADE  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN\_KEY\_CHECKS \= 1;

# 05\_PROCOST\_CALC\_ENGINE

\# 05\_PROCOST\_CALC\_ENGINE.md  
\#\# MATHEMATICAL COSTING FORMULAS AND AJAX LIFECYCLE (calc\_cogs())  
\#\#\# Module: Calculation Core and Aggregation Rules

\---

\#\# 1\. GUARD PEMBAGI DAN INISIALISASI BIDANG CETAK

Untuk mencegah galat division by zero pada saat kalkulasi\[cite: 2\]:

\- Q\_part \= max(1, qty\_part)\[cite: 2\]  
\- C\_plano \= max(1, i\_cut\_per\_plano)\[cite: 2\]  
\- I\_lb \= max(1, i\_isi\_per\_lb)\[cite: 2\]  
\- A\_cetak \= c\_uk\_cetak\_panjang\_cm \* c\_uk\_cetak\_lebar\_cm\[cite: 2\]  
\- is\_sheet \= (part\_type IN ('SHEET', 'SHEET\_DIGITAL')) ? 1 : 0\[cite: 2\]

\---

\#\# 2\. FORMULASI BIAYA MATERIAL KERTAS (c\_paper\_cost\_pc)

\#\#\# 2.1. Duplex Substrates (Price List per Rim)  
Faktor penyesuaian Duplex Cut (F\_dc)\[cite: 2\]:  
\- Jika i\_dc\_persen\_override \> 0:  
  F\_dc \= 1 \+ (i\_dc\_persen\_override / 100)\[cite: 2\]  
\- Jika tidak ada override:  
  F\_dc \= 1 \+ (duplex\_dc\_percentage / 10000)\[cite: 2\]

Rumus Biaya Kertas Duplex per PC\[cite: 1, 2\]:  
\- Luas\_Plano \= i\_panjang\_plano\_cm \* i\_lebar\_plano\_cm\[cite: 1, 2\]  
\- Pembagi\_Duplex \= 8611 \* 500 \* C\_plano \* I\_lb\[cite: 1, 2\]  
\- c\_paper\_cost\_pc \= (Luas\_Plano \* duplex\_price\_list \* F\_dc) / Pembagi\_Duplex\[cite: 1, 2\]

Catatan: 8611 adalah konstanta PLANO\_REF\_AREA (79 x 109 cm), dan 500 adalah RIM\_SHEETS.

\#\#\# 2.2. Ivory, Art Paper, dan Kraft Substrates (Basis Harga per Kg)  
\- Ivory dan Art Paper\[cite: 1, 2\]:  
  c\_paper\_cost\_pc \= (Luas\_Plano / 10000\) \* ivory\_ap\_price\_per\_kg \* (grammage / 1000\) / (500 \* C\_plano \* I\_lb)\[cite: 1, 2\]

\- Kraft Board\[cite: 1, 2\]:  
  c\_paper\_cost\_pc \= (Luas\_Plano / 10000\) \* kraft\_price\_per\_kg \* (grammage / 1000\) / (C\_plano \* I\_lb)\[cite: 1, 2\]

\#\#\# 2.3. Lembaran Corrugated Outer Box (SHEET dan SHEET\_DIGITAL)  
\- Derivasi Dimensi Lembaran (cm)\[cite: 1, 2\]:  
  P\_sheet \= (2 \* (i\_dim\_panjang\_mm \+ i\_dim\_lebar\_mm) \+ lebihan\_panjang \+ i\_lidah\_lem\_mm) / 10\[cite: 1, 2\]  
  L\_sheet \= ((i\_dim\_lebar\_mm \+ i\_dim\_tinggi\_mm) \+ lebihan\_lebar) / 10\[cite: 1, 2\]

\- Kalkulasi Trim Mesin Corrugator\[cite: 1, 2\]:  
  I\_trim \= max(1, floor((lebar\_mesin \- trim\_allowance) / L\_sheet))\[cite: 1, 2\]  
  W\_trim \= ceil(((L\_sheet \* I\_trim) \+ trim\_allowance) / 5\) \* 5\[cite: 2\]

\- Standardized Running Meter (RM)\[cite: 2\]:  
  RM \= (Q\_part / I\_lb) \* (1 / I\_trim) \* (P\_sheet / 100)\[cite: 2\]

\- Biaya Sheet Corrugated per PC (c\_sheet\_cost\_pc)\[cite: 2\]:  
  Disc\_factor \= i\_discount\_rm / 100\[cite: 2\]  
  Luas\_Sheet\_m2 \= (W\_trim \* P\_sheet) / 10000\[cite: 2\]  
  c\_sheet\_cost\_pc \= Luas\_Sheet\_m2 \* (index\_substance / I\_trim) \* (1 \- Disc\_factor)\[cite: 2\]

\---

\#\# 3\. FORMULASI ONGKOS PROSES DAN FINISHING (c\_process\_cost\_pc)

\#\#\# 3.1. Cetak Offset Lembaran  
c\_cetak\_cost\_pc \= i\_ongkos\_cetak\_rim / (500 \* C\_plano \* I\_lb)\[cite: 2\]

\#\#\# 3.2. Mesin Plong atau Pond (Die-Cut)  
c\_plong\_cost\_pc \= i\_ongkos\_plong\_lb / I\_lb\[cite: 2\]  
(Catatan: Mesin plong dihitung per lembar cetak LB yang masuk, tidak dibagi C\_plano)\[cite: 1, 2\].

\#\#\# 3.3. Lem Lipat (Seam Gluing)  
c\_lem\_cost\_pc \= i\_ongkos\_lem\_pc\[cite: 1, 2\]  
(Catatan: Tarif lem flat per unit jadi)\[cite: 1, 2\].

\#\#\# 3.4. Sub-Layer Surface Coating (hpp\_parts\_coating)  
cost\_per\_lc \= A\_cetak \* index\_coating\[cite: 1, 2\]  
cost\_per\_pc \= cost\_per\_lc / I\_lb\[cite: 1, 2\]  
c\_total\_coating\_pc \= SUM(cost\_per\_pc)\[cite: 1, 2\]

\#\#\# 3.5. Single Face Corrugated Attachment (SF EF / J37)  
c\_sf\_ef\_cost\_pc \= (A\_cetak / 10000\) \* harga\_sf \* (1 / I\_lb) \* i\_sf\_ef\_flag\[cite: 1, 2\]

\#\#\# 3.6. Laminasi Sheet-to-Sheet (J36 \+ J38)  
J36\_flat \= i\_lam\_sheet\_flat\_pc \* i\_lam\_sheet\_flag\[cite: 1, 2\]  
J38\_ongkos \= (dim\_p \* dim\_l \* (i\_lam\_sheet\_flag \* rate\_lam\_sh \+ i\_sf\_ef\_flag \* rate\_lam\_sf)) / I\_lb\[cite: 1, 2\]  
c\_ongkos\_lam\_pc \= J36\_flat \+ J38\_ongkos\[cite: 2\]

\#\#\# 3.7. Akumulasi Proses Corrugated (SHEET)  
c\_process\_cost\_pc \= (2 \* i\_ongkos\_print\_pc) \+ i\_ongkos\_diecut\_pc \+ i\_ongkos\_slitter\_pc \+ i\_ongkos\_slotter\_pc \+ i\_ongkos\_join\_pc \+ i\_ongkos\_partisi\_pc \+ i\_ongkos\_layer\_pc \+ i\_ongkos\_qc\_pc \+ i\_ongkos\_kirim\_pc \+ i\_ongkos\_packing\_pc\[cite: 1, 2\]

\#\#\# 3.8. Akumulasi Proses Offset (DC, DC+SF, DC+SHEET, KRAFT)  
c\_process\_cost\_pc \= c\_cetak\_cost\_pc \+ c\_plong\_cost\_pc \+ c\_lem\_cost\_pc \+ i\_ongkos\_hotprint\_pc \+ i\_ongkos\_kupas\_pc \+ i\_ongkos\_qc\_pc \+ i\_ongkos\_kirim\_pc \+ i\_ongkos\_packing\_pc \+ c\_ongkos\_lam\_pc \+ c\_sf\_ef\_cost\_pc \+ c\_total\_coating\_pc\[cite: 1, 2\]

\---

\#\# 4\. FORMULASI TOOLING DAN AMORTISASI (c\_tooling\_cost\_pc)

\- Film Amortization (Faktor kuadrat)\[cite: 1, 2\]:  
  c\_film\_cost\_pc \= ((i\_film\_faktor \* i\_film\_faktor) \* A\_cetak \* film\_rate) / (Q\_part \* I\_lb)\[cite: 1, 2\]  
  (Catatan: Default i\_film\_faktor \= 0.25; 0.25 \* 0.25 \= 0.0625)\[cite: 1, 2\].

\- Plat CTP\[cite: 1, 2\]:  
  c\_plate\_cost\_pc \= (i\_ongkos\_plate\_warna \* i\_jumlah\_warna) / (Q\_part \* I\_lb)\[cite: 1, 2\]  
  (Catatan: Default i\_ongkos\_plate\_warna \= Rp 90.000 per warna)\[cite: 1, 2\].

\- Pisau Pond atau Die Board\[cite: 1, 2\]:  
  c\_knife\_cost\_pc \= (i\_pisau\_faktor \* i\_pisau\_rate\_rp) / (Q\_part \* I\_lb)\[cite: 1, 2\]  
  (Catatan: Default i\_pisau\_rate\_rp: DC \= 900.000, KRAFT \= 1.500.000, SHEET \= 0)\[cite: 1, 2\].

\- Subtotal Tooling per Unit\[cite: 1, 2\]:  
  c\_tooling\_cost\_pc \= c\_film\_cost\_pc \+ c\_plate\_cost\_pc \+ c\_knife\_cost\_pc\[cite: 1, 2\]  
  (Catatan: Khusus part\_type SHEET, c\_tooling\_cost\_pc bernilai 0)\[cite: 1, 2\].

\---

\#\# 5\. TOTAL HPP PART DAN AGREGASI HEADER (update\_hpp\_totals)

\#\#\# 5.1. Total Biaya Pokok Produksi per Part  
c\_total\_part\_cost\_pc \= c\_paper\_cost\_pc \+ c\_sheet\_cost\_pc \+ c\_process\_cost\_pc \+ c\_tooling\_cost\_pc\[cite: 1, 2\]

\#\#\# 5.2. Query Rollup ke Header hpp

\`\`\`sql  
UPDATE hpp h  
JOIN (  
  SELECT   
    hpp\_id,  
    SUM((COALESCE(c\_paper\_cost\_pc, 0\) \+ COALESCE(c\_sheet\_cost\_pc, 0)) \* qty\_part) AS agg\_material,  
    SUM(c\_process\_cost\_pc \* qty\_part) AS agg\_process,  
    SUM(c\_total\_coating\_pc \* qty\_part) AS agg\_finishing,  
    SUM(c\_tooling\_cost\_pc \* qty\_part) AS agg\_tooling,  
    SUM(c\_total\_part\_cost\_pc \* qty\_part) AS agg\_total\_hpp  
  FROM hpp\_parts  
  GROUP BY hpp\_id  
) p ON h.hpp\_id \= p.hpp\_id  
SET   
  h.total\_material\_cost  \= p.agg\_material,  
  h.total\_process\_cost   \= p.agg\_process,  
  h.total\_finishing\_cost \= p.agg\_finishing,  
  h.total\_hpp            \= p.agg\_total\_hpp,  
  h.total\_after\_margin   \= p.agg\_total\_hpp \* (1 \+ (h.tolerance\_percent / 100)) \* (1 \+ (h.margin\_percent / 100)),  
  h.total\_after\_ppn      \= CASE   
                             WHEN h.is\_ppn \= 1 THEN (p.agg\_total\_hpp \* (1 \+ (h.tolerance\_percent / 100)) \* (1 \+ (h.margin\_percent / 100))) \* (1 \+ (h.ppn\_percent / 100))  
                             ELSE (p.agg\_total\_hpp \* (1 \+ (h.tolerance\_percent / 100)) \* (1 \+ (h.margin\_percent / 100)))  
                           END,  
  h.price\_per\_pcs        \= CASE   
                             WHEN h.qty\_order\_pcs \> 0 THEN h.total\_after\_ppn / h.qty\_order\_pcs   
                             ELSE 0   
                           END;

# 06\_PROMPT\_PIPELINE\_AI

\# 06\_PROMPT\_PIPELINE\_AI.md  
\#\# MASTER STEP-BY-STEP PROMPTS FOR GOOGLE AI STUDIO  
\#\#\# Module: Prompt Execution Pipeline

\---

\#\#\# MASTER PROMPT 1: DATABASE & LEVEL-1 VIEW INITIALIZATION  
\> "You are an expert Laravel and MySQL Enterprise Architect. Using MySQL 8.x, create migrations and database configurations for a multi-database schema architecture named \`tm3\*\`. Initialize schemas \`tm3sys\`, \`tm3mst\`, \`tm3sdi\`, and \`tm3hpp\`. Set up database connections in Laravel's \`config/database.php\`. Build local Level-1 Database Views (\`v1\_\*\`) in \`tm3sdi\` and \`tm3hpp\` projecting from \`tm3mst\`. Implement the thread-safe sequence generator \`sp\_generate\_doc\_number\`."

\---

\#\#\# MASTER PROMPT 2: SALES GUIDED LANDING & LIVE DIELINE CANVAS  
\> "Generate a reactive Livewire component with Tailwind CSS and Alpine.js for the Sales Inquiry module (\`01\_SALES\_INQPROD.md\`). Include the 3-column action cards (\`NEW\`, \`RECALC\`, \`REVISE\`), the searchable saved inquiry data table, and the parametric box size presets. Implement the interactive 2D Vector SVG Dieline Canvas in Alpine.js showing red cut lines, green crease lines, and bleed margins with zero network latency. Include the Coded Audit Footprint serial on the footer."

\---

\#\#\# MASTER PROMPT 3: CALCULATION ENGINE (\`calc\_cogs()\`) & FORM COMPONENT LIBRARY  
\> "Build the PHP calculation service \`calc\_cogs()\` inside \`tm3hpp\` based on \`05\_PROCOST\_CALC\_ENGINE.md\`. Implement the 10-session mathematical calculation covering Duplex Paper ($8,611\\text{ cm}^2$ base), Ivory/Kraft ($H\_{\\text{kg}}$ base), Corrugated Sheet running meter, platen die-cut (strictly per-LB), tooling amortization (squared film factor), and header rollup. Enforce the UI component standards from \`UIUX\_components\_part1.html\` (searchable location dropdown, tick-marked multi-select, compound prefix/suffix, split-action buttons, and QR code generation)."  
