# BACKLOG 02 — INQUIRY PRODUCT S/D SIAP PRODUKSI

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Rantai depan: Inquiry Product -> ProDev -> ProCost -> Quotation -> Customer Order, plus master yang menopangnya (Master Card, Master Mesin, Master Proses) sampai pekerjaan siap dilempar ke produksi.

> **ZONA BEKU:** Inquiry Product, ProDev, dan ProCost tidak boleh diubah — kode, data dummy, maupun UI/UX — sampai user sendiri yang meminta. Lihat CLAUDE.md §6B.

---


## 0. PRODUCT DEVELOPMENT — FOLLOW-UP FASE 1 (2026-09-02)

Fase 1 ProDev SUDAH SELESAI & LIVE (lihat `docs/LOG_JOB_DONE.md` tanggal
2026-09-02 "ProDev Fase 1 TUNTAS", spesifikasi lengkap
`docs/inquiry_product_costing/productdevelopment_tier1.md`). Item di bawah
ini ada di katalog tools §4.3 dokumen itu tapi TIDAK termasuk checklist
"Fase 1 SELESAI kalau" eksplisit — follow-up, bukan blocker:

- **DC+SHEET/DC+SF formula** (`CostingEngine::calculateDcSheetPart()`) masih
  DITANDAI belum diverifikasi ke Excel/sumber asli (lihat docblock class) —
  kalau ada Excel riil kasus DC+SHEET/DC+SF, cross-check dulu sebelum dipakai
  harga produksi sungguhan.
---

---

## 3. SALES / INQUIRY / CUSTOMER ORDER (tm2sdi)

- **Marketing Management** (CLAUDE.md §2, ditambahkan 2026-08-30) — modul
  BARU, belum ada 1 baris kode/dokumen Tier pun. Cakupan yang dideskripsikan
  user: log aktivitas sales (email/kirim quotation/kunjungan per customer)
  + tracking supply chain customer reguler/berkala. **Belum di-scoping
  detail** — kalau mulai dikerjakan, treat seperti modul baru (baca/tulis
  Tier doc dulu, jangan langsung bikin skema dari 1 paragraf deskripsi).
- **ProCost: rate & nama proses SHEET_DIGITAL/KRAFT** — parameter HPP DC
  sudah live (`docs/LOG_JOB_DONE.md` 2026-09-04). **Scope sengaja DC saja** — proses SHEET_DIGITAL/KRAFT (diecut/slitter/
  slotter/join/partisi/layer/packing/kirim, 8 dari 10 bucket ongkos) TIDAK
  punya padanan nama di `prod_process` saat ini (isinya migrasi offset lama:
  CETAK/LAMINASI/PLONG/LEM MESIN/POTONG/UV/KETREK/KSS/KUPAS/QC PACK/QC
  CETAK/QC/QC HITUNG/HITUNG/JEMUR/WRAPPING/STITCHING/LAMINATING DOFT — cuma
  KUPAS & QC PACK yang match) — memaksa map 8 bucket sisanya jadi tebakan,
  SENGAJA di-skip. **Masih terbuka kalau mau dilanjutkan**: (a) rate master
  Rupiah per proses (kolom baru di `prod_process` atau tabel versioned baru
  pola `mat_coating_rate` — perlu keputusan desain + angka rate ASLI dari
  user, bukan ditebak Claude), (b) tambah process_name SHEET_DIGITAL/KRAFT
  ke `prod_process` kalau memang proses itu nyata dipakai perusahaan (cek
  ke user dulu, jangan asumsi nama).
---

---

## 19. [MALAM] LANJUTAN ENGINE PRODEV & SUBKON (instruksi user 2026-09-18)

3 dari 4 sudah ditutup 2026-09-18 (`docs/LOG_JOB_DONE.md`): 19.3 (jalur ke WO,
sekaligus menutup 18.12), engine takaran pemakaian bahan otomatis, Imagick+
Ghostscript. **Sisa satu:**

| # | Pekerjaan | Status |
|---|---|---|
| 19.1 | Layar Surat Jalan Subkon sendiri (entry + cetak): penerima supplier, rujukan PR & CO, barang setengah jadi keluar/masuk. Saat ini surat jalan SUBKON dibuat otomatis dari ProDev dan tampil di daftar DO, tapi layar entry & cetaknya masih memakai bentuk surat jalan customer. **Keputusan user 2026-09-30: tetap perlu** — dibangun sebagai DO jenis SUBCON di v2 (`do_delivery.do_type='SUBCON'`, kolom `subcon_*` sudah ada). User journey menunggu persetujuan. | **BELUM** |

---

---

## 25. [PLAN — MUST BE CONTINUE] RANTAI DUMMY UAT TERINTEGRASI (rencana Iwan 2026-09-22, dilanjutkan Jo)

> **Instruksi user 2026-09-22:** *"rantai baru dari nol boleh, kalau bisa
> menyangkut semua yang ada master card nya, seperti Alsamsi dll"* +
> *"data dummy yang bagus yang terintegrasi dengan gambar mockup yang bisa
> di jadikan acuan"*.
> Ini pembayaran utang `docs/queue.md` item 5 yang sejak awal belum tercentang:
> *"Always create dummy data that is relationally linked across modules ...
> so cross-module flows (Inquiry -> Imposition -> Costing -> Master Card -> CO)
> can be tested end-to-end with connected data"*.

### 25.1 Keadaan sekarang (hasil audit DB 2026-09-22 — jangan diaudit ulang)

Tidak ada satu pun rantai dummy yang utuh. Yang ada dua, dan keduanya putus
dari arah yang berlawanan:

| Rantai | Lengkap sampai | Putus di |
|---|---|---|
| IQ2600001 -> CO2600001 (dummy lama, `data_origin='LIVE'`, migration `2026_08_28_000018_seed_wo_generation_dummy_chain`) | Plan Delivery + WO | **hulunya**: Quotation tidak pernah ada, costing masih DRAFT, inquiry masih SUBMITTED padahal CO sudah CONFIRMED (mustahil secara bisnis) |
| IQ99DUM005 -> CO2600002 (dummy baru, `data_origin='DUMMY'`, migration `2026_09_21_000007_seed_inquiry_to_co_win_chain`) | Inquiry -> Costing PUBLISHED -> Quotation -> CO -> Plan Delivery #706 | **hilirnya**: belum ada WO; part-nya kosong (`proses=0`, `material=0`, `prod_part_id=NULL`, `parent_product_id=NULL`) sehingga WO lahir tanpa operasi |

Master Card — belum ada satu pun yang jadi:
- `prod_mastercard_signoff` = **0 baris** (butuh 5: Desainer/Marketing/PPIC/QA/Plant Manager). Itu sebabnya watermark **NOT CONFIRMED** masih muncul di mockup.
- `prod_product.card_status` = `DRAFT` untuk **seluruh 14.013** produk.
- `vw_master_card` = `prod_product JOIN prod_part` -> hanya **37 baris**, dan **34 dari 37 kosong melompong** (tanpa pisau, finishing, warna, routing, layer).
- `prod_dieline_template` = 0 baris.

Tiga sampel UAT yang sudah ada (dari `2026_09_17_000002_seed_uat_samples...` & `2026_09_17_000005_align_alsamsi_top_sample...`):

| Kode produk | Pelanggan | Inquiry | routing | ink | material | Hilir |
|---|---|---|---|---|---|---|
| `UAT-ALSAMSI-SIG` (#14184) | AL SAMSI - SARUNG TENUN (#992) | IQUAT260903 | **8** | **4** | **9** | tidak ada costing/quo/CO/WO |
| `UAT-ALSAMSI-TOP` (#14182) | PAK MUKRI (#49) | IQUAT260901 | 0 | 0 | 0 | tidak ada |
| `UAT-OSIMA` (#14183) | PT. SELATAN JADI JAYA (#67) | IQUAT260902 | 0 | 0 | 0 | tidak ada |

Ketiganya: `dieline_ref` kosong, `printsize` kosong, `up_per_print` kosong,
artwork 0, signoff 0, inquiry masih `sub_state='DRAFT'`, `iqp_tier` 0 baris,
2 dari 3 `parent_product_id` NULL.

### 25.2 Rencana (PLAN must be continue)

Bangun rantai utuh DI ATAS ketiga sampel UAT itu — bukan bikin produk karangan
baru — karena nama, dimensi, routing, dan mesinnya sudah dipakai user sebagai
acuan UAT dan sudah cocok dengan `docs/mockups/mockup_mastercard_ver2.jpg`.

Per kasus UAT, satu rantai penuh:

```
Master Card lengkap (part + pisau + printsize + ink + material + artwork + 5 signoff -> card_status=APPROVED)
  -> Inquiry (tier qty, finishing, pack_qty)
    -> Costing -> PUBLISH (Direksi)
      -> Quotation PUBLISHED
        -> CO CONFIRMED
          -> Plan Delivery (CPDEL)
            -> Work Order + operasi terjadwal (BOM/BOP dari ProDev)
```

Syarat yang sudah diketahui, jangan ditemukan ulang:
- `WoGenerationService` menolak generate WO kalau CO belum CONFIRMED **atau**
  costing belum `published_flag=1`/`is_approved=1` (guard Jo 2026-09-18, BACKLOG 19.3).
  Jadi costing WAJIB dipublish dulu di seeder.
- Semua baris baru wajib `data_origin='DUMMY'` supaya ikut fasilitas
  hide/show & reset dummy (BACKLOG 18.7). **Catatan:** 16 baris `prd_wo` yang
  ada sekarang semuanya bertanda `data_origin='LIVE'` padahal dummy — perlu
  dibetulkan, kalau tidak WO tidak akan pernah ikut tersaring.
- Jangan sentuh `iqp_inquiry#5`/`co_order#2` lagi. Rantai lama itu rancu
  (lihat 25.1) dan dipakai banyak test; biarkan apa adanya.

### 25.3 Yang menggantung — butuh jawaban user

| # | Pertanyaan | Status |
|---|---|---|
| 25.3.1 | "PLONG 2" di mockup Master Card = **Pond/Plong kodok manual**, model **ML 750** (foto dikirim user 2026-09-22). Di master mesin ada 4 die cutter: `DIE-01` Ruian, `DIE-02` Shanghai, `DIE-03` Daxiang, `DIE-04` Daxiang Auto. DIE-04 otomatis, jadi yang manual tinggal DIE-01/02/03. **Mana yang "PLONG 2"?** Jangan ditebak | menunggu user |
| 25.3.2 | Spesifikasi 4 die cutter itu KOSONG semua di `prod_machine`: `max_sheet_length_mm`, `max_sheet_width_mm`, `max_speed_per_hour`, `operator_min`, `helper_min`, `brand_model` cuma merek tanpa model. Padahal Master Card butuh ukuran & efisiensi. Riset ke situs pabrikan (ML 750 dst.) atau minta data user | menunggu user |
| 25.3.3 | Angka lain di mockup yang belum ada sumbernya di DB: Kode Pisau, efisiensi material 72%, lembar cetak 114 lbr, pemakaian tinta per KG. Boleh dipakai apa adanya dari mockup, atau konfirmasi dulu ke bagian produksi? | menunggu user |
| 25.3.4 | Mockup sendiri mencatat lubang: *"Material: (kertas belum dipilih di Inquiry)"* — pemilihan kertas di Inquiry belum ada | BELUM |

### 25.4 JANGAN DIHAPUS

- Tag `arsip/mrp-mps-2026-08-30` — satu-satunya salinan kode Engine MRP/MPS/
  Kanban. Lihat section 26.
- `co_order_delivery` #458 (`created_by='operator'`) — hasil aksi Split/Gabung
  CPDEL manual, bukan sampah test.
- WO2600261 & WO2600262 beserta delivery #4 dan #15 — dipakai
  `ProductionWoDetailRebuildTest` & `ProductionWoGenerationTest` sebagai
  fixture read-only.
- WO2600225/227/228/230/231/245/250/260 — dipakai
  `ProductionSchedulingBoardSmokeTest` & `ProductionWorklistWoTest`.

---

---

## 27. [SEKARANG] MASTER MESIN & MASTER PROSES — LURUSKAN UI/UX + DATA (instruksi user 2026-09-22)

> User: *"sebelum itu saya ingin meluruskan dulu master mesin dan master
> process, baik dari UI UX ataupun data nya karena ini yang saya masukkan
> data real. INGAT data real hanya saja banyak mesin yang terdaftar belum
> ada spek."*

Urutan kerja yang dikunci user:
`Inquiry Product -> ProDev -> ProCost` (SELESAI & BEKU) `-> Master Mesin &
Master Proses` (**sekarang**) `-> Customer Order` (berikutnya).

**Ini DATA REAL.** Jangan mengarang spek, jangan tandai `data_origin='DUMMY'`.
Kalau perlu angka: riset ke situs pabrikan atau tanya user.

| # | Pekerjaan | Status |
|---|---|---|
| 27.1 | Lengkapi spek mesin yang kosong. **Cek ulang 2026-09-28: masih 41 dari 45 mesin tanpa ukuran lembar & kecepatan** (turun dari 44 setelah OFF-02 diisi 2026-09-23). Placeholder ML750 di DIE-01/02/03 (migration `2026_09_22_000015`) **BELUM diganti data nyata** — `serial_no` masih NULL, kolom `notes` masih bertuliskan PLACEHOLDER. **User janji foto nameplate Sabtu 2026-09-26 — tanggal itu sudah lewat, follow up: sudah difoto belum?** Kalau belum, jangan dianggap final; kalau sudah tapi belum dikirim, tagih fotonya | **SEBAGIAN, TERTUNDA** |
| 27.3 | Audit menyeluruh mesin mana lagi yang speknya kosong (bukan cuma die cutter) — bikin daftar, lalu isi bertahap | **BELUM** |

---

---

## 28. PERAN PART & MATERIAL ROLE BOP — TEKS BEBAS JADI MASTER? (dipindah dari backlog01 UI-07, 2026-09-23)

User: *"ini bagian dari Inquiry Product"*. Dua field di bawah saat ini diketik bebas, padahal nilainya
seharusnya pilihan baku. **Butuh keputusan user** (zona beku Inquiry/ProDev/ProCost → tanya dulu per perubahan):

1. **Peran Part** — `production/process-rate` kolom `part_code` (contoh data nyata: `BODY`). Daftar
   peran part (body/lid/base/sleeve/tray/insert/divider/left/right/monoform) sekarang cuma array PHP
   di `inquiry/form.blade.php::partRoleOptions()`. Pilihan: (a) jadikan tabel master `ref_part_role`
   dipakai Inquiry + process-rate, (b) biarkan teks bebas.
2. **Material Role BOP** — `production/bop-rule` kolom `material_role` (11 nilai yang hidup di tabel
   itu sendiri: TINTA, FOIL, LEM BOX, VARNISH UV, dst). Pilihan: (a) master kecil `ref_bom_row`,
   (b) dropdown dari nilai yang sudah ada + boleh ketik baru, (c) biarkan.
   (`material_pattern` bukan kandidat — isinya pola LIKE multi-nilai.)

**Aturan CLAUDE.md §7.12:** kalau dijadikan master, layar input master-nya WAJIB ikut dibuat.

---

## DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Konteks lengkap audit ini ada di `BACKLOG.md`.

### 12.2 Inquiry/CO — keputusan bisnis masih menggantung

`inquiryproduct_tier1.md` §6.2 & `tier2.md` §15 — sederet keputusan bisnis
BELUM dikunci (bukan soal teknis, soal kebijakan): granularity bahan
customer-supplied (all-or-nothing vs per-material), basis handling-fee,
kebijakan waste bahan customer, definisi umur/impresi maksimal tooling,
invoice-per-CO vs split, pajak external-cost. Plus 2 dokumen terkait yang
belum pernah dibangun sama sekali: `FORECAST_ENGINE_spec.md` (forecast
demand, butuh ≥1 tahun data) dan `SALES_INITIATIVE_note.md` (modul
"jual kapasitas nganggur" — masih verbal, belum ada sistem).

---
