---
name: screen_quotation_list
description: Grid worklist Quotation — mulai dokumen baru via picker customer, buka, atau confirm/publish langsung dari grid.
---

# Daftar Quotation

**File**: `resources/views/pages/quotation/list.blade.php`
**Route**: `quotation.list` — `GET /quotation`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery

## Tujuan
Grid worklist (mengikuti `griduistandard.md`) untuk daftar seluruh Quotation. Sales bisa mulai Quotation baru (pilih customer dulu lewat picker modal, redirect ke Entry), membuka dokumen existing, atau langsung Confirm/Publish (DRAFT→PUBLISHED) dari grid tanpa masuk Entry. Bukan master data — kolom Aksi bukan Edit+Toggle-aktif, tapi ↗ Buka + ✓ Confirm kondisional (hanya saat DRAFT).

## Field & Kolom
**Stat box**: Total Quotation, Draft, Published.
**Grid kolom**: identitas (avatar+nama customer+`quotation_no`), Tanggal (`issued_date`), Status (badge DRAFT/PUBLISHED), Nilai excl PPN (sum `subtotal` per quotation, dihitung hanya untuk baris yang tampil di halaman ini — bukan full scan), Aksi.
**Toolbar**: search (`search`, cocok `quotation_no` atau `customer.customer_name`), tombol "+ New Record" → buka picker customer.
**Modal picker customer**: `customerSearch` (live debounce 300ms, min 2 huruf, cocok `customer_name`/`customer_code`, hanya customer `activate=true`, limit 15).

## Aksi / Method
- `quotations()` [Computed] — query dgn search + pagination server-side (`HasGridPagination`).
- `customerResults()` [Computed] — hasil pencarian customer untuk picker.
- `openPicker()` / `closePicker()` — toggle modal.
- `startForCustomer(int $customerId)` — panggil `QuotationBuilder::findOrCreateDraft($customerId)`, redirect ke `quotation.entry`.
- `openQuotation(int $id)` — redirect ke `quotation.entry` (kalau DRAFT) atau `quotation.print` (kalau sudah PUBLISHED).
- `confirmQuotation(int $id)` — guard DRAFT & items tidak kosong; set `doc_status='PUBLISHED'`.
- `grandTotals()` [Computed] — sum `subtotal` per `quotation_id` untuk baris di halaman aktif saja.
- `statTotal()`, `statDraft()`, `statPublished()` [Computed].

## Tabel & Model yang Disentuh
- `App\Models\Sdi\QuoQuotation` → `tm2sdi.quo_quotation`
- `App\Models\Sdi\QuoQuotationItem` → `tm2sdi.quo_quotation_item` (untuk grandTotals)
- `App\Models\Master\CustCustomer` → tm2mst (koneksi mst), lintas-DB via picker & relasi `customer` (Eloquent query terpisah, bukan join lintas-DB).
- `App\Services\Sales\QuotationBuilder::findOrCreateDraft()`

## Alur / Business Logic Penting
- Quotation baru selalu lahir dari grid ini (via picker customer) — tidak ada tombol "+ New Record" polos tanpa pilih customer dulu.
- `confirmQuotation()` di sini adalah cara alternatif mem-publish selain tombol "Terbitkan Quotation" di layar Entry — logic sama (guard DRAFT + items non-kosong), tapi TIDAK memanggil method publish yang sama secara eksplisit (duplikasi kecil inline, bukan pemanggilan shared method) — perlu diverifikasi konsistensinya kalau logic publish berkembang di kemudian hari.

## Catatan
- Komentar kode menegaskan pola griduistandard.md §5 (grid transaksi/worklist boleh punya "+ New Record" karena dokumen memang lahir dari grid ini, beda dari worklist Imposition/Costing).
