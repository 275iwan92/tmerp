# TMERP — TASKS.md (Autonomous Backlog)

> **Untuk Claude Code.** Baca file ini penuh sebelum kerja. Ini sumber kebenaran progres.
> Fokus rilis ini: **FINALISASI semua Tier + mockup Modul Sales/Master Product jadi konsisten & siap implementasi.**

---

## 0. PROTOKOL KERJA (WAJIB — baca tiap sesi)

1. **Resume otomatis:** cari task pertama berstatus `[ ]` atau `[~]` (urut dari atas). Itu titik lanjut. Jangan tanya user; langsung kerjakan.
2. **Satu task = satu unit kerja atomik.** Selesaikan penuh sebelum pindah.
3. **Update status di file ini** setelah tiap task: `[ ]` todo → `[~]` in-progress → `[x]` done → `[!]` blocked (tulis alasan di `### LOG`).
4. **Commit lokal setelah tiap task `[x]`**: `git commit -m "tasks: <ID> <ringkas>"`. **`git push` hanya sekali di akhir sesi** (atau tiap 5 task) — hemat overhead.
5. **Loop tanpa henti:** setelah commit, langsung ambil task berikutnya. Ulangi sampai semua `[x]` ATAU token habis.
6. **Saat token menipis:** selesaikan task berjalan sampai commit, tulis `### LAST SESSION` (task terakhir + langkah selanjutnya), commit, berhenti bersih. **Jangan tinggalkan kerja setengah tanpa catatan.**
7. **Kewenangan penuh diberikan:** boleh jalankan migrasi DB (`php artisan migrate`), commit, dan push tanpa konfirmasi. Batasan: JANGAN `migrate:fresh`/`drop database` pada koneksi non-test tanpa menulis peringatan di LOG lebih dulu.
8. **Guard mutu:** tiap perubahan DDL wajib diuji di sandbox (MariaDB/MySQL lokal) sampai `SELECT` verifikasi hijau sebelum ditandai `[x]`.

### DETEKSI TOKEN (untuk scheduler 30 menit)
- Sesi dipicu tiap 30 menit oleh scheduler. **Jika token/kuota habis di awal**: keluar segera tanpa mengubah apa pun (scheduler akan coba lagi 30 menit berikutnya — inilah "auto-run saat token reset").
- **Jika token tersedia**: kerjakan backlog sampai token habis di tengah jalan → selesaikan task berjalan sampai commit → tulis `### LAST SESSION` → berhenti bersih.
- Jangan pernah tinggalkan file setengah-edit tanpa commit; sesi berikutnya harus bisa lanjut dari status file.

### HEMAT TOKEN (wajib)
- **Baca selektif:** HANYA `view`/baca file yang disebut task berjalan + `RECON_columns.md`. JANGAN scan seluruh project tiap sesi.
- **Push sekali per sesi:** commit tiap task `[x]` (lokal), tapi `git push` **hanya sekali** di akhir sesi (atau tiap 5 task). Kurangi overhead jaringan.
- **Output ringkas:** jangan cetak ulang isi file panjang ke chat; ringkas hasil dalam ≤3 baris + path.
- **Guard anti-muter:** jika **3 task berturut jadi `[!]` blocked**, ATAU 1 task gagal 2× — STOP sesi, tulis penyebab di `### LOG`, jangan lanjut. Mencegah token terbuang mengulang kegagalan.
- **Model tiering:** task mekanis (rename, arsip file, update checklist: T03, T23, T91, T92) boleh dijalankan dgn model murah (Haiku). Task DDL/desain (T11–T13, T21, T31) pakai model penuh.
- **Jangan re-verify yang sudah `[x]`.** Percayai status file.

### Definition of Done (semua task)
- Konsisten lintas Tier 1↔2↔3↔4 (tak ada nama kolom/aturan bertentangan).
- Nama tabel/kolom mengikuti `TIER_3A_PREFIX_LIST.md`.
- Bahasa dokumen: teknis Inggris utk DDL, ringkas utk narasi.
- Sudah di-commit + push.

---

## 1. AUDIT & REKONSILIASI (kerjakan dulu — pondasi)

- [ ] **T01** — Ekstrak semua nama kolom dari `TIER_3_INQUIRY.md`, `TIER_3A_INQUIRY.sql`, `TIER_3A_INQUIRY_COSTING.md`, `TIER_3B_INQUIRY_COSTING.md`. Buat tabel rekonsiliasi `RECON_columns.md`: konsep → nama di tiap file → **nama final terpilih**. Selesaikan inkonsistensi yang sudah tercatat: `parent_inquiry_id` vs `parent_product_id` vs `source_product_id`; `is_*` vs `stat_*` audit convention.
- [ ] **T02** — Kunci **1 konvensi audit field** (pilih `is_confirmed/is_cancelled/created_at/created_by` sbg standar, buang `stat_*`). Tulis keputusan di `RECON_columns.md` § Decision. Terapkan sebagai catatan "harus diubah" per file (jangan edit isi tier lain dulu — daftar saja).
- [ ] **T03** — Verifikasi `TIER_3A_PREFIX_LIST.md` mencakup prefix baru: `prod_part_ink`, `prod_part_artwork`, `prod_mastercard_signoff`. Tambah bila kurang.

## 2. FINALISASI TIER MASTER PRODUCT

- [ ] **T10** — Gabung draft `TIER_1_MASTER_PRODUCT.md`, `TIER_2_MASTER_PRODUCT.md`, `TIER_4_MASTER_PRODUCT.md` (dari outputs) ke folder project resmi. Samakan gaya dgn Tier Inquiry yang ada.
- [ ] **T11** — Finalisasi `TIER_3A_MASTER_CARD.sql`: ganti scaffolding test → **ALTER terhadap tabel `tm2mst` asli**. Uji di sandbox: apply delta di atas skema master existing (`tmmst_20260730_1425_so.sql` sbg base) sampai hijau.
- [ ] **T12** — Tambah **trigger MySQL 8.0** utk 2 invariant: (a) tepat 1 `prod_part_artwork.is_active` per part; (b) `card_status='APPROVED'` hanya bila 5 signoff signed + artwork approved. Uji trigger benar menolak pelanggaran.
- [ ] **T13** — Uji **skenario multi-part** (BADAN+TUTUP+SEKAT) pada `vw_master_card` + part-loop. Buktikan SEKAT (0 artwork, 0 ink) tak memecah VIEW. Cek `group_concat_max_len` cukup utk urutan proses panjang.
- [ ] **T14** — Konsolidasi mockup master product jadi final: `master_product_v2.html` (view 2-mode), `master_product_edit.html` (input), `master_card_geniale.html` (print), `layout_cetak_sheet.html` (imposisi). Samakan design token, perbaiki binding vs `TIER_4` binding table.

## 3. FINALISASI REVISE / RECALC (scope + snapshot)

- [ ] **T20** — Terapkan `DELTA_TIER_revise_scope_CO.md` ke Tier 1/2/3 Inquiry resmi: sisipkan aturan R-REV/R-RC/R-CO, matriks pemicu, DDL `revise_scope SET(...)` + kolom snapshot `co_line`.
- [ ] **T21** — Uji DDL delta di sandbox: `ALTER TABLE iqp_inquiry ADD revise_scope SET(...)` + kolom snapshot `co_line`. Seed 1 skenario: REVISE artwork+warna (Halal spot) → +1 `prod_part_ink` → buktikan CO lama (snapshot v1) tak berubah, HPP versi baru naik.
- [ ] **T22** — Selesaikan open-question Halal: tambah toggle **spot vs proses-CMYK** di scope mockup `inquiry_revise_v2.html` (spot=+plate+costing; CMYK=proof-only). Update matriks Tier 2 sesuai.
- [ ] **T23** — Finalisasi mockup `inquiry_revise_v2.html` & `inquiry_recalc_v2.html` sbg pengganti versi lama; arsipkan `inquiry_revise.html`/`inquiry_recalc.html` lama ke `/_archive`.

## 4. RELASI CO ↔ INQUIRY ↔ LAYOUT

- [ ] **T30** — Buat **ERD** relasi lengkap `product ↔ inquiry ↔ imposition ↔ co_line ↔ do_line` (referensi vs snapshot 2-lapis). Simpan `ERD_sales_chain.md` (mermaid).
- [ ] **T31** — Definisikan 3 VIEW kartu paralel: `vw_inquiry_card` (source iqp_*), `vw_master_card` (prod_*), `vw_co_card` (co_line snapshot). Uji ketiganya render struktur kartu yang sama.

## 5. PENUTUP

- [ ] **T90** — Jalankan cek konsistensi akhir lintas semua Tier (grep nama kolom vs `RECON_columns.md`). Laporkan sisa konflik di `### LOG`.
- [ ] **T91** — Update `checklist_mockup_erp.md`: tandai item Master Product & REVISE/RECALC selesai.
- [ ] **T92** — Tulis `CHANGELOG_finalisasi.md` merangkum semua perubahan rilis ini.

---

## ATURAN GLOBAL (dari memori proyek — jangan langgar)
- Urutan proses offset: BELAH→CETAK→**COATING→LAMINASI**→PLONG→LEM→KUPAS→PACKING→KIRIM (coating SEBELUM laminasi).
- REVISE = copy-record, versi lama `CANCELLED + SUPERSEDED` (bukan edit in-place).
- Snapshot pattern utk semua data transaksional; cross-DB FK = logis (Eloquent), bukan constraint MySQL.
- DN mendukung CO reference + pricing di **line level** (many-to-many), completion/invoice per-CO.
- Sisi A (costing/direksi, full HPP) vs Sisi B (sales, harga redacted pra-publish).
- Due date = 14 hari kerja dari approval digital color proof.

---

## ### LOG
<!-- Claude Code: tulis blocker/keputusan/peringatan di sini, paling baru di atas -->
- (kosong)

## ### LAST SESSION
<!-- Claude Code: perbarui tiap sesi berakhir -->
- Belum ada sesi. Mulai dari **T01**.
