# CLAUDE.md — Konteks Proyek ERP PT. Telasih Makmur (Versi 2)

> File ini dibaca Claude Code tiap sesi. Ini "memori" & aturan proyek.
> Sumber kebenaran: dokumen Tier di `/docs`. Bila ragu, baca Tier, jangan menebak.

---

## 1. APA INI

ERP cetak **offset & packaging** untuk PT. Telasih Makmur. Migrasi bertahap
dari ScriptCase (V1) ke **Laravel 13 + Livewire** (V2). Kita mengerjakan **V2**.

- Bahasa jawaban ke user: **Bahasa Indonesia**.
- Gaya: ringkas, padat, jujur. Tidak bertele-tele. Jangan asumsi — cek dulu.
- V2 pakai database `tm2*` yang **permanen** (menggantikan `tm*` lama setelah cutover).

---

## 2. ARSITEKTUR DATABASE (LOCKED — jangan diubah tanpa persetujuan)

**Database-per-module.** 1 database per modul fungsional. Saat ini 1 server MySQL
lokal (MySQL 8.0.46 · InnoDB · utf8mb4 · utf8mb4_unicode_ci).

| Connection | Database | Isi |
|---|---|---|
| mst | tm2mst | **Master netral** (single source of truth): customer, product, material, geo, currency, uom |
| sdi | tm2sdi | **Sales-domain**: inquiry (`iqp_*`), quotation, customer order, delivery |
| pur | tm2pur | Procurement |
| inv | tm2inv | Inventory |
| prd | tm2prd | Produksi / work order |
| acc | tm2acc | Finance & accounting |
| hrm | tm2hrm | HR master/organisasi |
| hrr | tm2hrr | HR transaksi |
| pyr | tm2pyr | Payroll |
| sys | tm2sys | Sistem: user, role, notifikasi, audit trail |

### Aturan lintas-DB (LOCKED — KRITIKAL)
- **FK MySQL TIDAK BISA lintas-database.** Jangan pernah bikin constraint FK antar-DB.
- **FK dalam DB yang sama** = tetap fisik (constraint normal). Contoh: `iqp_part.inquiry_id → iqp_inquiry` (sama-sama di tm2sdi) = FK fisik OK.
- **Relasi antar-modul (beda DB):**
  - Baca via **VIEW read-only** ATAU **Eloquent relationship** (query terpisah, cocokkan di PHP).
  - **JANGAN** pakai query builder `->join()` antar tabel beda-DB (error/lambat).
  - Selalu eager-load: `Inquiry::with('customer')`, bukan join manual.
- **Integritas lintas-modul = tanggung jawab Laravel**, bukan DB:
  - Validasi keberadaan: `exists:mst.cust_customer,id_customer` saat simpan.
  - Cascade/restrict lintas-DB: logika manual di Service, bukan ON DELETE.

---

## 3. KONVENSI PENAMAAN (LOCKED)

### File & folder
- **DILARANG emoji/karakter non-ASCII di nama file atau folder** — di seluruh project, kode
  maupun docs. Emoji tetap boleh di **konten** (label tombol, badge, heading di dalam halaman).
  Rinci: `docs/GLOBAL_RULES.yaml → file_naming`.

### Prefix tabel per domain
**Master (tm2mst):**
`cust_` customer · `prod_` product & proses · `mat_` material & harga bahan ·
`uom_` satuan · `geo_` wilayah · `fin_` finance (currency/kurs/payment_term) ·
`sup_` supplier · `emp_` employee · `org_` struktur organisasi ·
`sys_` sistem/parameter · `ref_` lookup generik lintas-domain.

**Transaksi (tm2sdi):**
`iqp_` inquiry product (+ costing turunan) · `quo_` quotation · `co_` customer order · `do_` delivery order.

**Aturan:** prefix = **domain/entitas**, bukan "pemakai". Lookup ber-domain nempel
ke induk (`iqp_type`), lookup generik ke `ref_`. Prefix `cost_` DIHAPUS (costing
transaksi = `iqp_costing`, ikut inquiry).

### Kolom
- PK: `id_<entitas>` (kecuali `prod_type`, `iqp_type` = kode varchar).
- Nama: English, snake_case.
- Master: `is_active` + `created_at/by` + `updated_at/by`.
- Transaksi: `created_at/by`, `updated_at/by`. Ber-approval: `approved_by/at`.
  Confirm/cancel: flag `is_confirmed`/`is_cancelled` + `cancel_reason` (pelaku via `updated_by`).
- State transisi dokumen → tabel `doc_state_log` (polymorphic), BUKAN kolom per-transisi.

---

## 4. PRINSIP DESAIN (LOCKED)

- **Snapshot/beku:** transaksi membekukan nilai dari master saat momen penting.
  Inquiry deep-copy struktur produk (`prod_*` → `iqp_part/_material/_process`).
  Costing membekukan rate (`rate_snapshot`). Master berubah → transaksi lama TIDAK berubah.
- **Rate fluktuatif** (coating/film/sheet): pola versioned + `is_active`; **1 baris
  `is_active=1` per jenis** = harga berlaku. `effective_date` untuk riwayat.
- **Engine pattern:** "engine suggests, officer confirms" (scheduling, imposition, routing).
- **Costing rahasia (Sisi A):** HPP/margin hanya Costing+Direksi. Publish (approve
  Direksi) = deklasifikasi harga ke Sisi B (Sales).
- **Due date** = 14 hari kerja dari **approval prove FISIK** (bukan PO/mockup).
- **Diskon** jarang (occasional): item di `iqp_tier`, dokumen kolom-kembar header↔co_order, NULL default.

---

## 5. STATUS TIER 3A (per sesi ini)

**SELESAI & terpasang di MySQL lokal:**
- tm2mst: geo_* + fin_currency + cust_customer + prod_* (product/part/material/process/type/category) + mat_raw + uom_* (16 tabel).
- tm2sdi: `iqp_*` 12 tabel (inquiry/type/tier/part/part_material/part_process/tooling/external_cost/prove/prove_log/costing/costing_detail).
- FK internal (dalam-DB) tegak. FK lintas-DB = logis (Laravel).

**GAP tertunda (tambal via ALTER, non-blocking):**
1. Estimasi kecepatan F15 (est_days/speed/override/status) — ada di mockup, belum di DDL.
2. ~~Rate costing versioned (coating/film/sheet)~~ — **CLOSED**: `mat_coating_rate`/`mat_sheet_rate`/`mat_grammage` + `sys_constant` sudah ada & dipakai `CostingEngine.php` (Costing Hub live di `inquiry.costing`). `product_costing.html` yang tadinya dikira gap ini ternyata duplikat Costing Hub — dibatalkan.
3. Selaras diskon qty (mockup teks vs DDL angka).

---

## 6. DOKUMEN ACUAN (di /docs)

- `TIER_0_GLOBAL_ARCHITECTURE.md` — arsitektur global, keputusan LOCKED.
- `TIER_1_INQUIRY_v2_1.md` — alur & proses inquiry (aktor, state, aturan bisnis).
- `TIER_2_INQUIRY_v3.md` — business logic, state machine, pseudocode HPP.
- `TIER_3A_*.sql` — DDL (geo, master, inquiry). **Skema aktual = ini.**
- `TIER_3A_PREFIX_LIST.md` — konvensi prefix lengkap.
- Mockup Tier 4 (HTML/CSS) — acuan UI: `inquiry_new/recalc/revise.html`,
  `inquiry_costing_*.html`, `inquiry_proof.html`, `inquiry_quotation.html`,
  `product_costing.html`, `*_nota_*.html`.
- `GRID_UI_STANDARD.md` — **wajib dibaca sebelum bikin/ubah grid Master apa pun**:
  tanpa header halaman, kotak statistik pastel, kolom identitas avatar+nama+kode,
  klik 2x buka detail, kolom Aksi 2 ikon (Edit+toggle aktif), toolbar satu baris,
  tombol "+ New Record", tanpa tombol Hapus di grid.

---

## 7. WORKFLOW CODING (aturan kerja Claude Code)

1. **Baca dokumen dulu.** Sebelum bikin model/migration, cek DDL di `/docs` — jangan tebak kolom.
2. **Model Eloquent** wajib set `$connection` (mst/sdi/dst), `$table`, `$primaryKey`
   (karena PK bukan `id` default).
3. **Migration** cerminkan DDL 3A yang sudah ada (jangan bikin skema baru yang beda).
   FK lintas-DB: JANGAN dibuat. FK dalam-DB: boleh.
4. **Livewire component** ikut mockup HTML sebagai acuan tampilan & field.
5. **Iterasi kecil** — 1 layar/fitur per waktu (mulai `inquiry_new`), bukan semua sekaligus.
6. **Validasi** ganti FK lintas-DB yang hilang (rule `exists:conn.table,col`).
7. Bila ada keputusan desain baru, konfirmasi ke user dulu — jangan putuskan sendiri hal arsitektural.
8. **WAJIB `npm run build` setelah ubah class Tailwind di Blade/Livewire.** Project
   ini pakai Vite production build (`public/build/`), **bukan** `npm run dev` yang
   hot-reload — jadi class Tailwind baru (`w-64`, dsb.) di file yang baru diedit
   **TIDAK otomatis muncul di CSS terkompilasi**. Insiden 2026-08-21: 8 grid Master
   diedit toolbar-nya tapi CSS terakhir di-build sehari sebelumnya → elemen
   tampil tanpa styling sama sekali (search box selebar layar, tombol
   "+ New Record" tak kelihatan). Jangan cuma `php -l` (itu cuma cek syntax PHP,
   tidak cek CSS). **Checklist wajib tiap sesi yang mengubah tampilan (Blade/Volt
   dengan class Tailwind baru):** (a) selesai edit → (b) `npm run build` → (c) baru
   laporkan selesai / minta user cek browser. Kalau ragu apakah class-nya baru,
   build saja — build ini murah (~2 detik) dan tidak merusak apa pun.

---

## 8. MULAI DARI MANA

Modul pertama: **Inquiry** (fondasi seluruh order). Layar pertama: **inquiry_new**.
Urutan: Model (master + iqp) → Livewire inquiry_new (CRUD + dieline preview) →
costing → quotation → proof. Costing engine & rate versioned menyusul.
