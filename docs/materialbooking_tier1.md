# Material Booking (dari Customer Order) — Desain Tier 1

> **Status: DESAIN, menunggu persetujuan user** (2026-10-01). Belum ada kode.
> Preview interaktif: artifact "Tata Letak Gudang TMERP" tab *Material Booking*
> (salinan repo: `docs/mockups/inventory_booking_preview_v2.html`).
> Nama file mengikuti menu baru yang diusulkan **"Material Booking"**
> (`doc_slug = materialbooking`). Terkait: `warehouselayout_tier1.md` (stok per bin, Available).
> Implementasi nanti mengikuti aturan multi-bahasa 2026-10-01: nilai DB/status English,
> label layar English + kamus `sys_dictionary`.

## 1. Keadaan sekarang (cek ddt* 2026-10-01)

| Hal | Fakta |
|---|---|
| `ddtinv.rm_booking` / `rm_booking_d` | **ada, 0 baris, tidak dipakai kode**. Kolom detail sudah kaya: `co_id`, `co_detail_id`, `material_id`, `location_id`, `qty_need`, `qty_book`, `qty_purchase`, `qty_replacement`, `qty_issued`, `material_replace_id`, `replace_ratio`, `pr_detail_id`, `wo_id` (header) |
| `prd_wo_material` | ada, 0 baris (`qty_required`, `material_needed_by`, substitusi) |
| `pr_line` | sudah punya `i_co_line_id`, `i_need_date` |
| BOM produk | `prod_part_material` 6 produk master card baru: **material_id NULL**, hanya gramatur + ukuran |
| Layout | `prod_layout_group` (CONFIRMED) punya `up_count`, `waste_pct`, `plano_w_mm/h_mm` |
| Lead time bahan | `mat_raw.lead_time_days` **kosong semua** |
| MRP/MPS lama | diarsipkan (CLAUDE.md §6B); booking ini BUKAN menghidupkannya — hanya berbasis CO, tanpa forecast |

## 2. Alur

1. **Pemicu**: CO Confirmed; ulang otomatis saat CO/CPDEL berubah, CO Cancel, PV diterima,
   adjustment di-post, dan tiap malam. Hanya baris yang belum di-Confirm PPIC yang dihitung ulang.
2. **Langkah 0 — FG**: bila repeat order, tampilkan stok FG produk yang sama (booking FG = Tahap 2).
3. **Langkah 1 — Kebutuhan** per part: lembar plano = ⌈qty ÷ `up_count`⌉ × (1 + `waste_pct`).
   Material dari `prod_part_material.material_id`; bila kosong → baris `NEED_ITEM`, engine
   mengusulkan `mat_raw` yang cocok (kategori + gramatur + ukuran ≥ plano, dengan rasio potong).
   Tahap 2: tinta/varnish/lem dari standar pemakaian (per 1000 lembar / per m²) yang harus
   punya layar input (CLAUDE.md §7.12).
4. **Langkah 2 — Tanggal butuh** = tanggal kirim `co_order_delivery` − lead produksi
   (`sys_constant` BOOKING_LEAD_DAYS default 7 hari kerja, override per produk).
5. **Langkah 3 — Netting** per bahan, urut tanggal butuh, hot job didahulukan:
   Available (FIFO lot; zona STORAGE/STAGING; pemilik kosong atau customer CO sama; dikurangi
   hard booking lain) → PO terbuka tiba ≤ tanggal butuh (`PEGGED_PO`) → PO terlambat (`LATE_PO`)
   → PR terbuka (`PEGGED_PR`) → sisa `SHORT`.
6. **Langkah 4 — Tindakan PPIC**: Confirm (alokasi stok jadi *hard*, mengurangi Available),
   Create PR (dikelompokkan per supplier usulan, dibulatkan ke kemasan beli, `i_co_line_id` +
   `i_need_date` terisi, tanggal pesan = tanggal butuh − lead time), Substitute (item pengganti +
   rasio, persetujuan), Reschedule.
7. **Pemakaian**: WO dari CPDEL mewarisi booking (`wo_id`) → Pick List (Warehouse Tahap 2) →
   Stock Out mengisi `qty_issued`; sisa dilepas saat WO selesai; CO Cancel melepas semua.
8. **Rebutan stok**: tanggal butuh paling awal menang; hot job boleh ambil alih (rebook) dengan
   persetujuan Ops Manager, tercatat.

## 3. Skema (pakai ulang, tm2inv)

- `rm_booking` (ada): 1 header per CO — tambah `co_id`, `run_at`, `doc_status`
  (Draft/Confirmed/Closed/Cancelled). Nomor `BK2600001`.
- `rm_booking_d` (ada): tambah `co_delivery_id`, `part_id`, `need_date`, `line_status`
  (`BOOKED`/`PEGGED_PO`/`LATE_PO`/`PEGGED_PR`/`SHORT`/`NEED_ITEM`/`ISSUED`/`RELEASED`),
  `source` (`LAYOUT`/`BOM`/`MANUAL`), `calc_up`, `calc_waste_pct`, `spec_category_id`,
  `spec_gram`, `spec_w_mm`, `spec_h_mm`, `priority`.
- `rm_booking_alloc` **baru**: `id_booking_alloc`, `booking_detail_id`, `source_type`
  (`STOCK`/`PO`/`PR`), `source_id` (layer / po line / pr line), `location_id`, `lot_no`,
  `pallet_id`, `qty`, `is_hard`, audit. Satu kebutuhan bisa ditutup beberapa sumber.
- Relasi ke `co_*` (tm2sdi), `po_*`/`pr_*` (tm2pur), `prod_*` (tm2mst) = logis.

## 4. Layar (lihat preview)

| Layar | Isi |
|---|---|
| Booking Workbench | Daftar CO + bar coverage (stok / PO / PO telat / short / pilih item), kotak status = filter, Run All |
| Booking per CO | Baris per item CO → part → bahan, rumus perhitungan terlihat, sumber penutup, Pick Item untuk baris spec; tombol Run, Undo, Save, Create PR, Confirm |
| Shortage Planner | Per bahan (horizon 30 hari): butuh, available, incoming, kurang, butuh pertama, pesan paling lambat, usulan PR; centang → Create PR |
| Material Timeline | Proyeksi saldo per tanggal satu bahan, dengan/tanpa usulan PR |
| Stock On Hand | Kolom Booked / Available / Incoming dari booking ini |

## 5. Keputusan (rekomendasi)

| # | Keputusan | Rekomendasi |
|---|---|---|
| B1 | Pemicu | Otomatis saat CO Confirmed + ulang per kejadian & tiap malam |
| B2 | Soft vs hard | Usulan (soft); terkunci (hard) setelah PPIC Confirm |
| B3 | Bahan dihitung | Tahap 1 kertas/board dari layout; Tahap 2 tinta/varnish/lem |
| B4 | Tanggal butuh | Kirim CPDEL − lead produksi (default 7 hari kerja, override per produk) |
| B5 | Rebutan stok | Tanggal butuh terawal; hot job rebook dengan persetujuan Ops Manager |
| B6 | BOM spec saja | PPIC pilih item dari usulan; disimpan jadi default Master Product setelah konfirmasi |
| B7 | Stok FG repeat order | Tampilkan dulu; booking FG Tahap 2 |
| B8 | PR | Per supplier usulan, dibulatkan ke kemasan beli; lead time per kategori wajib diinput |
| B9 | Hubungan MRP lama | Terpisah, berbasis CO saja |
