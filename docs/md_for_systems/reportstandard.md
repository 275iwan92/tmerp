# STANDAR — REPORT 4 LEVEL (DESAIN DIPUTUSKAN, BELUM DIIMPLEMENTASI)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-09-02, direvisi sama hari setelah diskusi user menjelaskan
> detail tiap level. **Keputusan desain di dokumen ini SUDAH DIKUNCI**
> (akses, mekanisme kode supervisor, library chart) — yang **BELUM ADA**
> cuma implementasi (0 layar report/kode supervisor sungguhan dibangun).
> Jangan anggap fitur ini "sudah ada" cuma karena dokumen ini detail — cek
> dulu apakah ada Resource/halaman NYATA sebelum bilang ke user "sudah
> dikerjakan". Dokumen adik: `docs/topmanagementreport_tier1.md` (katalog
> laporan detail khusus level Top Management — Sales/Procurement/Finance/HR
> per domain, ditulis 2026-08-27, ternyata sudah ada duluan).

---

## 1. KENAPA DITUNDA DARI FASE 1 CONFIGURATION

Sistem report 4-level + graphics report adalah modul baru setingkat Costing
Engine atau Production Scheduling — bukan penambahan kecil. Akses & mekanisme
kode sudah diputuskan (§2-3), tapi **belum ada pilot/laporan pertama
ditunjuk** ("saya tentukan sendiri nanti", user 2026-09-02) — jangan mulai
bangun 1 layar pun sebelum itu ditunjuk eksplisit.

---

## 2. 4 LEVEL — PERAN, CAKUPAN DATA, AKSES (KEPUTUSAN FINAL 2026-09-02)

| Level | Peran (contoh nyata) | Sifat data | Cakupan | `mgmt_level` |
|---|---|---|---|---|
| **4. Top Management** | Direksi, **GM** (= grup `OPS_MGR`/Manager Operasional yang SUDAH ADA, bukan grup baru), Manager Operasional | **Read-only + report** — WAJIB drill-down interaktif (klik angka summary → turun ke baris transaksi asli, prinsip Summary→Detail `topmanagementreport_tier1.md` §2) | **Seluruh perusahaan**, lintas modul | `4` (GM/OPS_MGR) dan `5` (Direksi) |
| **3. Manager** | Manager Purchasing, Manager Produksi, dst | Read-only + report, SAMA sifatnya dgn Top Management (bukan summary skala total) | **Dibatasi per departemen/fungsi** — Manager Purchasing TIDAK lihat Produksi/Sales, dst | `3` |
| **2. Supervisor** | Supervisor Sales, dst | Bisa BUKA layar **data entry** (bukan cuma report) sbg backup/koreksi | Milik tim/areanya | `2` |
| **1. Data Entry / Operator** | Staf input harian — **mayoritas user aplikasi ada di sini** | Report HANYA utk cek pekerjaan sendiri, bukan analisis | Data yang dia input sendiri | `1` |

### 2.1 Skala `sec_group.mgmt_level` SUDAH ADA & SUDAH PAS — reuse, bukan tabel baru

Kolom `mgmt_level` (tinyint, `tm2sys.sec_group`) **sudah punya komentar DB**
`1=STAFF 2=SUPERVISOR 3=MANAGER 4=GM 5=DIREKSI` — persis skala 4-level yang
diminta, ditemukan 2026-09-02 (bukan didesain ulang, murni kebetulan skema
lama sudah menyediakan). **Baris yang ADA sekarang** (cek `SELECT * FROM
tm2sys.sec_group` kalau berubah):

| `group_code` | `group_name` | `mgmt_level` |
|---|---|---|
| SALES_STAFF | Staf Sales | 1 |
| SALES_SPV | Supervisor Sales | 2 |
| PRODUKSI | Staf Produksi | 1 |
| QA | Quality Assurance | 1 |
| PROD_MGR | Manager Produksi | 3 |
| OPS_MGR | Manager Operasional | 4 |
| DIREKSI | Direksi | 5 |

**Tidak ada grup "GM" terpisah** — level 4 diisi `OPS_MGR`. Keputusan user
2026-09-02: **GM = OPS_MGR**, akun GM (mis. akun user yang menulis dokumen
ini) tinggal jadi anggota grup `OPS_MGR`, TIDAK perlu `sec_group` baru.

**Helper `SecUser` yang PERLU ditambah saat mulai implementasi** (pola sama
`isProdManagerOrAbove()`/`isOpsManagerOrAbove()` yang sudah ada di
`app/Models/Sys/SecUser.php`, JANGAN dirombak, cuma ditambah baru):
```php
public function isSupervisorOrAbove(): bool   // maxMgmtLevel() >= 2
public function isManagerOrAbove(): bool      // maxMgmtLevel() >= 3
public function isTopManagementOrAbove(): bool // maxMgmtLevel() >= 4 (GM+Direksi)
public function isDireksiOnly(): bool          // hasGroup('DIREKSI') -- approval khusus Direksi, TIDAK termasuk GM
```

### 2.2 Approval 2 tingkat DI DALAM Top Management

User eksplisit: "ada pula screens for approvals. Baik level direksi ataupun
di bawahnya level GM" — Top Management BUKAN 1 gerbang approval flat.
Direksi (`mgmt_level=5`) dan GM/OPS_MGR (`mgmt_level=4`) approve hal yang
BEDA — layar approval mana butuh Direksi-only vs GM-ke-atas **belum
dikatalogkan** (per-kasus, ditentukan saat laporan/approval spesifiknya
diminta, bukan aturan global).

### 2.3 MATRIX LEVEL x BIDANG (keputusan user 2026-09-02: "level 4 hingga level 1... matrix dengan bidang yang dikerjakan, misal purchasing, sales")

Level (§2) BUKAN 1 sumbu berdiri sendiri — tiap **bidang/domain** (Sales,
Purchasing, dst) punya susunan Level 1→4 SENDIRI, isinya beda-beda sesuai
apa yang sudah dibangun di domain itu. Level 4 (Top Management) SATU-SATUNYA
yang menembus lintas domain (Direksi/GM lihat SEMUA baris tabel ini
sekaligus); Level 1-3 terkurung di 1 domain saja (Manager Purchasing tidak
otomatis lihat baris Sales).

**6 domain** dipetakan dari modul yang sudah ada (`sec_module`): Sales,
Purchasing (Procurement), Inventory, Production, Finance & Accounting, HR.
Isi kolom Level 1/2/3 = status BUKTI implementasi (layar apa yang sudah ada
vs yang masih murni laporan/kode belum dibangun) — Level 4 rujuk ke katalog
detail `topmanagementreport_tier1.md` (jangan diduplikasi di sini).

| Bidang | Level 1 (Data Entry — cek kerjaan sendiri) | Level 2 (Supervisor — cakupan tim + backup akses) | Level 3 (Manager — laporan departemen) | Level 4 (Top Management) |
|---|---|---|---|---|
| **Sales** | ADA: Inquiry/Quotation/CO/Delivery entry (`inquiry/*`, `quotation/*`, `co/*`, `dispatch/do/*`) — layar transaksi itu SENDIRI jadi "laporan cek sendiri" (belum ada 1 layar report ringkasan terpisah). Butuh: kode supervisor (§3) di titik mana? BELUM ditentukan. | BELUM ADA layar/report bertingkat tim. `SALES_SPV` (mgmt_level=2) sudah ada sbg grup, belum dipakai gate apa pun report. | SEBAGIAN: belum ada "Laporan Sales" ringkasan departemen — yang ada baru grid transaksi biasa (griduistandard.md), bukan summary+pivot. | Katalog di `topmanagementreport_tier1.md` §4.1 (Corong Inquiry→Quotation, Margin per Produk/Customer, Win-rate — win-rate blocked kolom baru, lihat §5). |
| **Purchasing (Procurement)** | ADA: PR/PO/PV/PRT entry penuh (`pur/pr,po,pv,prt/*`). | BELUM ADA. | SEBAGIAN: **Approval Center PR & PO SUDAH ADA** (`pur/pr/approval.blade.php`, `pur/po/approval.blade.php`) — ini APPROVAL (aksi setuju/tolak), BUKAN laporan/summary — beda kebutuhan dari "Manager Purchasing lihat total spend departemennya" yang diminta di sini, masih perlu dibangun terpisah. Outstanding PR/PO report (`pur/report/*`) sudah ada tapi levelnya masih grid biasa, belum pivot. | Katalog `topmanagementreport_tier1.md` §4.2 (Spend per Supplier/Kategori, Outstanding PR/PO — tinggal ditambah lapis pivot). |
| **Inventory** | ADA: Stock In/Out/Return/Adjustment entry penuh, 3 kelas RM/FG/SFG. | BELUM ADA. | BELUM ADA laporan ringkasan departemen (yang ada: Master Stok/Kartu Stok = laporan operasional, bukan ringkasan manajerial). | Katalog `topmanagementreport_tier1.md` §4.2 (Nilai & Turnover Stok, Movement Bahan — sumber VIEW sudah ada `v_rm_onhand` dst). |
| **Production** | ADA: WO Detail, Scheduling Board, Output Recording (F15) — paling lengkap dari semua domain (lihat BACKLOG.md §6). | BELUM ADA. | BELUM ADA laporan ringkasan departemen (Monitoring Dashboard yang ada = real-time kartu per mesin/proses, bukan laporan periode). | **TIDAK ADA di `topmanagementreport_tier1.md`** (dokumen itu cuma Sales/Procurement-Inventory/Finance/HR) — kalau Production jadi pilot, katalog laporannya HARUS ditulis dulu, belum ada sama sekali. |
| **Finance & Accounting** | ADA: Cash & Bank Journal, DP Invoice, COA — tapi subsistem AR/AP (Invoice/Billing/Payment/Aging) **belum ada** (BACKLOG.md §12.3). | BELUM ADA. | BELUM ADA laporan ringkasan departemen. | Katalog `topmanagementreport_tier1.md` §4.3 (Posisi Kas, AR/AP Aging — **AR/AP sendiri belum ada datanya**, Trial Balance sudah ada view `v1_fin_trial_balance`). |
| **HR** | ADA: Master Employee, Recruitment (L1-L9 minus L8), Correction Requests. | BELUM ADA. | BELUM ADA laporan ringkasan departemen. | Katalog `topmanagementreport_tier1.md` §4.4 (Headcount, Kedisiplinan, Cross-check Keluarga — notifikasi cross-check sudah jalan). |

**Pola yang konsisten di semua baris**: Level 1 (data entry) paling matang di
SEMUA domain (itu memang fokus pembangunan aplikasi sejauh ini) — Level 2
(Supervisor) **kosong total di semua domain** (mekanisme kode §3 belum
dibangun sama sekali, di domain manapun). Level 3 (Manager) SEBAGIAN
domain punya Approval Center (aksi, bukan laporan) tapi laporan
ringkasan-nya sendiri belum ada di domain manapun. Level 4 sudah
terkatalog detail utk 4 dari 6 domain (Production tidak, HR/Sales/
Purchasing/Inventory/Finance ya) — implementasi tetap 0 di semua.

---

## 3. MEKANISME "KODE OTORISASI SUPERVISOR" (BARU, 0 PRESEDEN — DIPUTUSKAN 2026-09-02)

**Dicek 2026-09-02: TIDAK ADA mekanisme serupa di mana pun di aplikasi ini**
(bukan cuma belum ada UI — pola "supervisor kasih kode ke bawahan utk buka
akses sementara" belum pernah ada sama sekali, beda dari `stat_cancel`
biasa yang cuma gate permission normal). Ini modul kecil BARU, bukan
perluasan sesuatu yang sudah ada.

**Alur yang disepakati** (Opsi "Supervisor generate kode sekali-pakai"):
1. Data Entry salah input di 1 dokumen yang sudah tersimpan (kasus konkret
   per layar BELUM dikatalogkan — dokumen sudah "terkunci" oleh state apa,
   ini keputusan per-modul saat diimplementasi).
2. Data Entry lapor **lisan** ke Supervisor-nya (di luar sistem — bukan
   request formal via layar, TIDAK sama dgn `FlowEngineService` approval
   biasa yang berbasis dokumen).
3. Supervisor buka layar sendiri (baru, belum ada), sistem tampilkan
   **kode sementara** (rencana: 6 digit, berlaku beberapa menit — durasi
   exact & apakah 1 kode reusable/1x-pakai BELUM diputuskan detail).
4. Supervisor sebutkan kode itu ke Data Entry (lisan, di luar sistem).
5. Data Entry ketik kode itu di layarnya sendiri → field/dokumen yang
   tadinya terkunci jadi bisa diedit SEMENTARA (durasi sama dgn masa
   berlaku kode, atau sampai disimpan — belum diputuskan).

**Yang BELUM diputuskan (perlu dibahas lagi saat mulai implementasi)**:
- Kode generate per-Supervisor (1 kode dipakai ke SIAPA pun bawahannya) atau
  per-dokumen-spesifik (Supervisor pilih dokumen mana dulu, baru generate)?
- Disimpan di tabel baru (`sec_override_code`?) dgn kolom expiry, atau
  cukup cache/session sementara (tidak perlu jejak permanen di DB)?
- Perlu audit trail (siapa buka kode, dipakai utk edit apa) atau cukup
  fungsional tanpa log formal?
- Berlaku ke SEMUA layar data entry atau cuma yang ditandai butuh proteksi
  ini (kemungkinan besar opsional per-layar, bukan global)?

**JANGAN mulai coding mekanisme ini** sebelum poin-poin di atas dijawab —
ini snippet desain awal, bukan spec siap-implementasi.

---

## 4. KONVENSI FORM/GRID/CHART

- **Grid data report** → ikut `griduistandard.md` (grid non-Master, kolom
  Aksi = Buka, tanpa toggle aktif).
- **Filter periode/level** → ikut `FORM_UI_STANDARD.md` untuk header,
  `x-form.date-picker` (docs/FORM_UI_STANDARD.md §3) untuk filter tanggal.
- **Graphics report** → **DIPUTUSKAN 2026-09-02: Chart.js via CDN**
  (`<script src="https://cdn.jsdelivr.net/npm/chart.js">` atau setara) —
  BUKAN ApexCharts, BUKAN bikin sendiri dari nol (beda dari filosofi
  "buat sendiri" `spreadsheetgridstandard.md`/`x-form.date-picker` — grafik
  dinilai terlalu kompleks utk di-reinvent dgn benar, Chart.js ringan &
  dokumentasinya luas). **Belum ada implementasi nyata** — cara integrasi
  Livewire↔Chart.js (re-render saat data berubah, dsb) belum dicoba.
- **Engine pivot (Summary layer)**: **MASIH TERBUKA** — `HasCustomizableGrid`
  (fitur grid custom view/sort/group-by yang sudah ada) dicek 2026-09-02,
  ternyata group-by-nya cuma VISUAL (garis pemisah), **BUKAN agregasi**
  (tidak ada SUM/COUNT per grup) — jadi TIDAK otomatis jadi fondasi pivot.
  Kalau mulai implementasi, keputusan "komponen generik vs custom per
  laporan" (`topmanagementreport_tier1.md` §5 poin 3) masih perlu dijawab,
  dan engine agregasinya sendiri dibangun baru dari nol.

---

## 5. YANG SENGAJA BELUM DIKERJAKAN

- **Semua implementasi** — 0 layar report, 0 layar generate-kode-supervisor,
  0 helper `SecUser` baru (§2.1), 0 integrasi Chart.js nyata. Dokumen ini
  cuma desain terkunci + pointer, bukan kode.
- **Pilot pertama belum ditunjuk** (§1) — jangan mulai modul/domain mana
  pun tanpa arahan eksplisit user.
- **Win-rate Sales** (kolom baru `iqp_inquiry`), **lokasi menu laporan**,
  **strategi refresh data** (real-time vs snapshot) — 3 keputusan ini masih
  terbuka, lihat `topmanagementreport_tier1.md` §5 poin 1/5/6.
- **Katalog laporan level Manager (3) & Supervisor/Data-Entry (1-2)** — cuma
  level Top Management (4) yang punya katalog detail
  (`topmanagementreport_tier1.md`). Level 1-3 belum ada daftar
  laporan/layar konkret sama sekali, cuma prinsip cakupan data (§2 tabel).
