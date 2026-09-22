---
name: screen_production_wo_print
description: Dokumen cetak Work Order (Bill of Process + Bill of Material) sebagai backup/checklist fisik shift/mesin, kolom aktual kosong untuk diisi manual.
---

# Cetak Work Order

**File**: `resources/views/pages/production/wo-print.blade.php`
**Route**: `production.wo.print` (`/production/wo/{wo}/print`, param `wo` numeric)
**Koneksi DB utama**: prd (tm2prd) — model `App\Models\Prd\PrdWo`; sdi (tm2sdi) untuk `IqpPartMaterial`; inv (tm2inv) untuk view `v_rm_available`
**Modul**: Production Scheduling & Product Development

## Tujuan
Cetak form WO (`docs/production_scheduling_wo_generation_delta.md` §7, `docs/printnotastandard.md`) sebagai backup/checklist fisik untuk shift/mesin yang belum tentu selalu pegang tablet. Pencatatan digital tetap lewat F15 "Catat Output" di WO Detail — form cetak ini punya kolom KOSONG (actual start/finish, qty baik/reject/waste, paraf operator) untuk diisi manual di lapangan. Bill of Material ditampilkan sebagai referensi saja (bukan untuk diisi).

## Field & Kolom
Read-only cetak:
- Header: no. CO, part, qty rencana, tipe WO, HOT JOB flag, due date, tanggal bahan dibutuhkan.
- Section 1 Bill of Process: tabel per operasi — Seq, Proses, Mesin (atau "Manual"), Operator (dari `operatorUtama()->employee`), Target/Jam, Rencana Mulai (terisi), lalu kolom KOSONG: Aktual Mulai/Selesai, Qty Baik/Reject/Waste, Paraf.
- Section 2 Bill of Material (kondisional, hanya jika `source_iqp_part_id` ada): Material, Qty Kebutuhan, Qty Tersedia (Gudang), Qty Diambil (kosong, untuk paraf manual).
- Signatures: Dibuat oleh (PPIC Officer), Operator Mesin, Diperiksa QC.

## Aksi / Method
- `mount(wo)` — set `$woId`.
- `wo()` [Computed] — PrdWo with `coOrder`, `product`, `operations.crew.employee` (order by seq_no).
- `company()` [Computed] — OrgCompany kode `TM`.
- `billOfMaterial()` [Computed] — `IqpPartMaterial::where('part_id', $wo->source_iqp_part_id)` order `layer_seq`; kosong kalau WO tidak berasal dari iqp_part.
- `billOfMaterialRows()` [Computed] — map tiap material: cari `MatRaw` untuk unit, hitung `qty_kebutuhan` (= qty WO), `qty_tersedia` (query lintas-DB raw ke `tm2inv.v_rm_available` via `DB::connection('tm2inv')`), `qty_harus_dibeli` = max(0, kebutuhan - tersedia).

## Tabel & Model yang Disentuh
- `App\Models\Prd\PrdWo` → tm2prd (relasi `coOrder`→tm2sdi, `product`→tm2mst, `operations.crew.employee`→tm2mst, semua lintas-DB logis).
- `App\Models\Sdi\IqpPartMaterial` → tm2sdi.
- `App\Models\Master\MatRaw` → tm2mst.
- View `tm2inv.v_rm_available` — diakses via `DB::connection('tm2inv')->table(...)` (query builder raw, BUKAN Eloquent relasi lintas-DB — sesuai pola yang diizinkan CLAUDE.md §2 pola 2/query langsung).

## Alur / Business Logic Penting
- Pola query BOM/Bill-of-Material di sini **sengaja diduplikasi** dari `billOfMaterialRows()` di `wo-entry.blade.php`, bukan diekstrak ke trait — supaya layar print tetap berdiri sendiri (komentar eksplisit di kode).
- `qty_harus_dibeli` dihitung real-time saat cetak dengan membandingkan qty kebutuhan WO vs stok tersedia gudang.

## Catatan
- Dokumen ini eksplisit disebut "dokumen kerja internal, bukan dokumen ke pelanggan" di footer template.
