---
name: screen_inqprod_recalc
description: Layar REKALKULASI — repeat order harga baru, 100% reuse spec/dieline/material/proses dari inquiry baku, cuma refresh harga bahan & qty.
---

# Hitung Ulang Harga (RECALC)

**File**: `resources/views/pages/inquiry/recalc.blade.php`
**Route**: `inquiry.recalc` (`/inquiry/recalc`, param `#[Url] id` atau `#[Url] source_id`)
**Koneksi DB utama**: sdi (tm2sdi), baca lintas-DB ke mst (tm2mst) untuk harga bahan
**Modul**: Inquiry Product

## Tujuan
Layar entry doc-type **REKALKULASI** — dipakai Sales untuk repeat order di mana spec produk sama persis dengan inquiry sebelumnya ("baku"), hanya qty dan/atau harga bahan yang berubah. Berbeda 180 derajat dari REVISE: di sini TIDAK ADA field spec yang bisa diedit — part/material/process di-clone verbatim dari inquiry baku (`IqpInquiry::cloneStructureFrom()`), read-only total. Halaman menampilkan preview artwork + dieline agar officer tidak salah pilih inquiry baku hanya dari teks nama tipe. HPP & harga jual baru dihitung Costing setelah submit — layar Sales ini tidak menghitung apa pun.

## Field & Kolom
- Trace produk asal: `source->inquiry_no` (baku) -> `inquiry->inquiry_no` (RECALC baru, kalau sudah tersimpan).
- Editable: `qty` (Qty Order PCS, required numeric > 0, mengisi 1 baris tier primary `iqp_tier`), `deadline_quotation` (date, opsional).
- Readonly (identitas): Customer, Nama Produk.
- Reuse per part (`reuseParts`, 100% read-only): preview Artwork (zoom), preview Diecut (render `window.TMDieline.svg()` dari `dieline_spec` beku), Dimensi (`flat_w`×`flat_h`), Dieline/Tipe (`dieline_spec.type_code`), Warna (`cfg_color`+`cfg_ink`), Qty/SKU, Material (nama gabungan), Proses (nama gabungan, "belum ada data" kalau kosong — modul routing proses belum digarap).
- Tabel Perbandingan Harga Bahan (`materialPriceRows`, computed): Material, Harga Baku (`mat_raw.avg_unitprice`), Harga Kini (`mat_raw.last_unitprice`), Delta % (▲/▼, threshold <0.05% dianggap 0%).

## Aksi / Method
- `mount()` — 2 jalur: `$id` (buka RECALC yang sudah ada, load `inquiry` + `source` via `parentInquiry()`, set `readOnly` dari `stat_confirm`) atau `$source_id` (draft baru dari inquiry baku, pre-fill qty/deadline dari tier primary sumber). Tanpa keduanya -> `abort(404)`.
- `#[Computed] reuseParts()` — parts milik `$inquiry` (kalau sudah tersimpan) atau `$source` (draft baru).
- `#[Computed] materialPriceRows()` — bandingkan `avg_unitprice` vs `last_unitprice` dari `MatRaw` untuk semua `rawmat_id` yang dipakai part.
- `statusPillClass()` — mapping badge warna dari `sub_state`.
- `persistDraft()` (protected) — transaksi `tm2sdi`: update existing atau create `IqpInquiry` baru (`inquiry_type=RECALC`, `parent_product_id` dari source, `revision_no=0`) + `cloneStructureFrom($source)` + create tier primary.
- `saveDraft()` — persist + flash "Tersimpan ... (DRAFT)".
- `submitToCosting()` — persist + set `stat_confirm=true`, `sub_state=SUBMITTED`, kunci form (`readOnly=true`).

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (`iqp_inquiry`, sdi) — RECALC baru & relasi `parentInquiry()` ke inquiry baku (self-referencing via `parent_product_id`), `tiers`.
- `App\Models\Sdi\IqpInquiry::cloneStructureFrom()` — deep-copy part/material/process dari source (implementasi ada di Model, bukan di file ini).
- `App\Models\Master\MatRaw` (`mat_raw`, mst, lintas-DB pola 1) — `avg_unitprice`/`last_unitprice` untuk tabel perbandingan.
- Relasi part read-only: `parts.materials.rawMaterial`, `parts.processes` (sdi, dalam-DB).

## Alur / Business Logic Penting
- **100% reuse, 0 field spec editable** — sesuai `docs/inquiryproduct_delta_revise.md` R-RC-1: hanya qty & harga bahan yang di-refresh; kalau spec/dimensi/artwork/warna/proses perlu berubah, harus lewat REVISE, bukan RECALC.
- Belum ada snapshot harga bahan per-inquiry (costing engine bertarif versioned belum digarap untuk RECALC) — perbandingan harga murni baca 2 kolom real `mat_raw` (bukan angka dikarang).
- Submit mengunci form total (`readOnly`), sama pola dengan form.blade.php/revise.blade.php.

## Catatan
- Preview artwork+diecut ditambahkan 2026-09-04 atas permintaan user ("RECALC harus ada preview image dan die cut nya juga agar tidak salah") — karena RECALC 100% reuse spec teks yang gampang salah baca kalau ada banyak tipe mirip.
- Kolom Proses menampilkan "belum ada data (modul routing proses belum digarap)" bila kosong — bukan bug, memang belum ada isian di form BARU untuk field ini (lihat catatan serupa di `screen_inqprod_new.md`).
