# BACKLOG REFERENSI — Ide Peningkatan Imposition (dari riset eksternal)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

| | |
|---|---|
| **Status** | 🗂️ **BACKLOG / REFERENSI SAJA** — bukan keputusan terkunci, bukan scope aktif, JANGAN dieksekusi Claude Code langsung dari file ini |
| **Sumber** | Riset eksternal "Optimasi Arsitektur Sistem... Product Inquiry dan Imposition" (benchmark Sistrade/PrintVis/ePS), diberikan Telma 2026-08-27 |
| **Modul terkait** | 01 · Sales (Imposition) — sudah ✅ live/locked; ini catatan enhancement masa depan, bukan revisi arsitektur berjalan |
| **Kenapa dokumen ini ada** | Riset sumbernya bagus tapi sebagian asumsinya tak cocok dgn arsitektur TMERP (dieline DSL parametrik, bukan CAD-import), dan sebagian rekomendasinya berlebihan skala utk tim ini. Dicatat di sini biar ide baik tak hilang, tapi tak menyesatkan sebagai spec siap-jalan. |

---

## 1. Perbedaan asumsi dasar

Paper sumber mengasumsikan alur: **customer kirim file CAD/DXF → ERP parsing otomatis → imposition**.
TMERP memakai alur berbeda: **pilih tipe box (parametrik) → isi P×L×T → dieline ter-generate dari DSL**
(`m_product_type.dieline_spec`, engine `TMDieline`). Ini bukan celah yang belum digarap — ini pilihan
arsitektur yang sudah berjalan (lihat `inquiryproduct_tier1.md` §5.3).

**Konsekuensi:** rekomendasi paper yang berbasis "parsing CAD eksternal" (DXF/CFF2/ARD parser, Rotating
Calipers utk ekstraksi geometri dari file asing) **hanya relevan jika** customer TMERP memang mengirim
file CAD mentah yang perlu diinterpretasi. **Belum dikonfirmasi** — lihat §4.

---

## 2. Ide yang genuinely relevan (worth dipertimbangkan sebagai Tier baru nanti)

### 2.1 Die-Tool Reuse Matching (disederhanakan dari versi paper)
**Masalah nyata:** saat ini tooling lifecycle sudah tracked (status ADA/ADA_WARNING/AUS/TIDAK_ADA,
`prod_part.dieline_ref`) tapi sistem belum **mengusulkan otomatis** "pakai pisau existing ini" saat
order baru punya dimensi mirip dengan pisau yang sudah ada di gudang.

**Kenapa versi paper (Hausdorff Distance/Procrustes Analysis) berlebihan untuk TMERP:** itu perlu
karena di ERP lain, dieline berasal dari CAD arbitrary (poligon freeform bebas bentuk) sehingga
butuh algoritma pencocokan bentuk geometris. **Dieline TMERP parametrik** — bentuknya deterministik
dari kombinasi (tipe produk + parameter dimensi). Artinya pencocokan bisa jauh lebih sederhana:
cek kesamaan **tipe produk + dimensi dalam toleransi** (mis. P/L/T ± beberapa mm) terhadap pisau
existing yang tersimpan — query database biasa, **bukan** komputasi geometri berat.

**Kalau mau digarap:** jadi fitur di alur Inquiry/Imposition — saat spec produk baru disimpan, sistem
cek `prod_part.dieline_ref` existing dengan tipe+dimensi mirip, usulkan reuse (hapus biaya pisau baru
dari estimasi HPP) sebelum lanjut ke costing.

### 2.2 Grain Direction Guard (arah serat kertas)
**Masalah nyata:** untuk folding carton, garis lipat utama harus tegak lurus arah serat kertas (LG/SG)
supaya tak retak saat dilipat/dilem. Ini **belum ada** validasi/atribut di master material TMERP saat ini.

**Kalau mau digarap:** tambah atribut `grain_direction` (LG/SG/isotropic) di `mat_raw`/`mat_paper`,
tambah validasi (warning, bukan hard-block — konsisten prinsip "engine suggests, officer confirms"
yang sudah dipakai di routing) saat imposition/rotasi produk berlawanan arah serat.

### 2.3 Gang-Run / Multi-Job Batching (disederhanakan dari GA)
**Masalah nyata — ini yang paling relevan, nyambung langsung ke `production_scheduling_tier1.md`**:
short-run 1-2 hari, banyak job kecil paralel → menggabungkan beberapa order berbeda yang pakai
bahan+gramatur+jumlah warna sama ke satu lembar cetak besar mengurangi ganti-plate & efisiensi kertas.

**Kenapa versi paper (Genetic Algorithm) berlebihan untuk v1:** GA cocok kalau kombinasi kemungkinan
tata letak sangat besar & perlu true optimization. Untuk mulai, **heuristik pengelompokan sederhana**
sudah menangkap sebagian besar manfaat: kelompokkan order dengan (bahan sama + gramatur sama +
jumlah warna sama + window due-date berdekatan), tampilkan sebagai usulan gabungan ke PPIC/Costing
officer — keputusan gabung tetap manual.

**Ini perlu dibahas bareng Production Scheduling** (bukan cuma Sales/Imposition) karena gang-run
memengaruhi urutan antrean mesin cetak — cross-module, jangan didesain terpisah dua kali.

---

## 3. Yang TIDAK direkomendasikan diadopsi sekarang

- **NFP Irregular Bin Packing (No-Fit Polygon, Minkowski Difference)** — true computational-geometry
  engine, kompleksitas implementasi tinggi, manfaat marginal untuk TMERP karena dieline parametrik
  (bukan freeform CAD) — sebagian besar produk sudah rektangular/prediktabel bentuknya.
- **Microservices architecture (gRPC, REST API terpisah)** — proporsional untuk software house yang
  jual produk ke banyak klien ERP berbeda; tidak proporsional untuk 1 instalasi internal dengan tim
  1 arsitek + Claude Code.
- **Roadmap 12 bulan 4 fase** — dari paper sumber, ditulis untuk skala proyek yang beda jauh dari
  scope aktif TMERP (10 minggu). Jangan dipakai sebagai acuan waktu.

---

## 4. Pertanyaan yang harus dijawab SEBELUM ide §2.1 dikerjakan

**Apakah customer TMERP pernah mengirim file CAD/DXF mentah**, atau **semua desain memang dibuat
in-house lewat sistem DSL parametrik** (pilih tipe box + isi dimensi)? Ini menentukan apakah perlu
ada langkah "impor spec dari file luar" sama sekali di alur Inquiry, atau cukup tetap 100% input
manual terstruktur seperti sekarang.

---

## 5. Status

Tidak ada tindakan lanjut sampai Telma putuskan: (a) apakah §2.1–2.3 masuk prioritas kerja, dan
(b) jawaban §4. Sampai saat itu, dokumen ini murni referensi — **jangan jadi dasar Tier 2/3/4** tanpa
diskusi ulang.
