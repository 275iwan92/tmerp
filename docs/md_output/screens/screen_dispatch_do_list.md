---
name: screen_dispatch_do_list
description: Grid worklist Delivery Order — "+ New Record" via picker customer (bukan create langsung), sumber VIEW v_do_list, expand-row preview baris.
---

# Daftar Delivery Order

**File**: `resources/views/pages/dispatch/do/list.blade.php`
**Route**: `dispatch.do.index` — `GET /dispatch/do`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery (Dispatch)

## Tujuan
Grid worklist Delivery Order mengikuti `griduistandard.md`. DO memang lahir dari grid ini (beda dari CO yang lahir dari Quotation), tapi lewat picker customer dulu — bukan langsung buka Entry kosong — karena `do_delivery.customer_id` NOT NULL (DISPATCH-01, dokumen terkunci ke 1 customer). Kolom Aksi: ↗ Buka selalu ada, ✓ Confirm kondisional (`status='DRAFT'` & `line_count>=1`) memanggil `DispatchService::confirm()` yang sama dengan tombol Confirm di layar Entry (tidak ada duplikasi logic). Aktor: Dispatcher/Gudang/Sales.

## Field & Kolom
**Grid** (`deliveries` computed, sumber VIEW `v_do_list` — bukan Eloquent model, `DB::connection('tm2sdi')->table('v_do_list')`):
- Kolom identitas: Customer / No. DO (`customer_name` + `do_no`, tampil "(Draft)" kalau belum terbit).
- Tanggal (`do_date`), Baris (`line_count`), Asal CO (`co_count` — badge ungu kalau >1 CO tergabung dalam 1 DO), Status, Nilai (`total_value`).
- Aksi: ↗ Buka, ✓ Confirm (kondisional).
- Expand-row (klik chevron ▸/▾): tabel baris `do_line` per DO (No. CO, Item, Qty, Harga, Subtotal) via `expandedLines()` — eager-load sekaligus untuk semua ID ter-expand (bukan per-klik N+1).

**Stat box**: Total DO, Draft, Confirmed, Shipped.

**Modal Picker Customer** (`pickerOpen`): search `customerSearch` (min 2 huruf, cocok nama/kode), hasil dari `CustCustomer::where('activate', true)`, limit 15.

## Aksi / Method
- `deliveries()` [Computed] — query `v_do_list`, filter `search` (cocok `do_no`/`customer_name`), paginate.
- `expandedLines()` [Computed] — `DoLine::whereIn('delivery_id', $expanded)` (exclude CANCELLED), grouped per `delivery_id`.
- `toggleExpand(int $id)`.
- `customerResults()` [Computed] — search customer aktif untuk modal picker.
- `openPicker()` / `closePicker()`.
- `startForCustomer(int $customerId)` — buat `DoDelivery` baru (`status='DRAFT'`, `do_date=now()`), redirect ke `dispatch.do.entry`.
- `openDelivery(int $id)` — redirect ke `dispatch.do.entry`.
- `confirmDelivery(int $id)` — guard `status='DRAFT'` & lines tidak kosong; panggil `DispatchService::confirm()`.
- `statTotal()`/`statDraft()`/`statConfirmed()`/`statShipped()` [Computed].

## Tabel & Model yang Disentuh
- VIEW `tm2sdi.v_do_list` — sumber grid utama (query builder mentah).
- `App\Models\Sdi\DoDelivery` → `tm2sdi.do_delivery` — dipakai untuk create baru & stat count.
- `App\Models\Sdi\DoLine` → `tm2sdi.do_line` — untuk expand-row.
- `App\Models\Master\CustCustomer` → tm2mst (koneksi `mst`) — lintas-DB, dipakai modal picker customer.
- `App\Services\Dispatch\DispatchService::confirm()` — dipanggil sama persis dengan yang di `dispatch/do/entry.blade.php`.

## Alur / Business Logic Penting
- **Deviasi disengaja dari desain awal** (dicatat di komentar kode): Tier4 §2 aslinya minta "klik New, masuk ke Entry kosong, pilih customer" — tapi karena `customer_id` NOT NULL di skema, alurnya jadi "+ New Record" → modal picker customer DULU → baru redirect ke Entry yang sudah terisi customer (0 baris). Ini konsisten dengan constraint DB, bukan improvisasi bebas.
- Sumber picker Blok C di `dispatch/do/entry.blade.php` (baris outstanding dari CO) adalah VIEW `v_co_delivery_outstanding`, berbeda dari VIEW `v_do_list` yang dipakai grid ini (list DO, bukan list baris CPDEL outstanding).

## Catatan
Tidak ditemukan gap/TODO eksplisit dalam komentar kode file ini.
