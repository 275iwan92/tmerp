---
name: screen_dispatch_do_entry
description: Entry/detail Delivery Order — tarik baris outstanding dari CO via DispatchService, Confirm menerbitkan No. DO, Tandai Terkirim menutup jadwal CPDEL.
---

# Delivery Order — Entry

**File**: `resources/views/pages/dispatch/do/entry.blade.php`
**Route**: `dispatch.do.entry` — `GET /dispatch/do/{doId}`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery (Dispatch)

## Tujuan
Layar detail 1 Delivery Order (DO) — dokumen pengiriman fisik ke customer. Satu komponen menangani baik status DRAFT (masih bisa ditarik baris dari CO, diedit) maupun read-only (`CONFIRMED`/`SHIPPED`/`CANCELLED`). DO selalu terikat 1 customer (`customer_id` NOT NULL, DISPATCH-01) — header DO dibuat lebih dulu dari picker customer di `dispatch/do/list.blade.php` sebelum masuk ke layar ini. Semua aksi bisnis (tarik baris, update qty/harga, Confirm, Tandai Terkirim, Batalkan) didelegasikan ke `App\Services\Dispatch\DispatchService` — persist auto-save per aksi, tidak ada tombol "Simpan Draft" terpisah. Aktor: Dispatcher/Gudang/Sales.

## Field & Kolom
**Blok A — Informasi Dokumen** (editable saat DRAFT via `updateHeader()`, field allowed: `do_date`, `scheduled_ship_date`, `ship_address_detail`): No. Delivery Order (readonly, `(auto saat Confirm)` sebelum terbit), Tanggal DO, Rencana Kirim.

**Blok B — Customer & Ship-to**: Customer (readonly), Alamat Kirim via `<livewire:address-picker>` (event `address-picked`, field-name `do-ship-address`), Detail Alamat (editable saat DRAFT).

**Blok C — Tarik Baris dari Customer Order** (hanya tampil saat DRAFT): tabel picker dari VIEW `v_co_delivery_outstanding` (filter `customer_id` dokumen ini, `qty_available > 0`, `co_status='CONFIRMED'`) — kolom No. CO, Item, Qty Tersedia, UoM, Harga/Unit, Jadwal Kirim; checkbox multi-select (`checkedDeliveryIds`), tombol "↓ Tarik ke Delivery Order".

**Blok D — Baris Delivery Order** (`do_line`): No. CO, Item (+ badge "⚠ item sama, harga beda" kalau ada duplikat deskripsi dgn harga beda — `duplicateDescriptions`), Qty DO (editable saat DRAFT), Harga/Unit (editable saat DRAFT), Subtotal, aksi hapus. Footer total qty & value.

## Aksi / Method
- `mount(int $doId)`.
- `do()` [Computed] — `DoDelivery::with(['customer', 'lines' (exclude CANCELLED, order by line_no)])`.
- `totals()` [Computed] — sum `qty_do` dan `subtotal` dari lines.
- `duplicateDescriptions()` [Computed] — badge info UI saja, tidak mengubah data.
- `onAddressPicked(...)` [`#[On('address-picked')]`] — guard DRAFT & `fieldName==='do-ship-address'`.
- `updateHeader(string $field, $value)` — guard DRAFT.
- `pickerLines()` [Computed] — hanya isi kalau DRAFT; query VIEW `v_co_delivery_outstanding`.
- `togglePick(int $coDeliveryId)`.
- `pullSelected()` — panggil `DispatchService::pullLines()`.
- `updateLineQty(int $lineId, $value)` / `updateLinePrice(int $lineId, $value)` — panggil `DispatchService::updateLineQty()`/`updateLinePrice()`.
- `removeLine(int $lineId)` — panggil `DispatchService::removeLine()`.
- `confirmDo()` — panggil `DispatchService::confirm()`.
- `markShipped()` — panggil `DispatchService::markShipped()`.
- `openCancelModal()` / `closeCancelModal()` / `cancelDo()` — panggil `DispatchService::cancel()`, wajib isi `cancelReason`.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\DoDelivery` → `tm2sdi.do_delivery` (PK `id_delivery`).
- `App\Models\Sdi\DoLine` → `tm2sdi.do_line` (PK `id_line`).
- VIEW `tm2sdi.v_co_delivery_outstanding` — query builder mentah (`DB::connection('tm2sdi')->table(...)`), sumber picker Blok C, exclude qty yang sudah terserap DO lain.
- `DoDelivery belongsTo customer` (`App\Models\Master\CustCustomer`, tm2mst) — lintas-DB, Eloquent relationship query terpisah.
- Semua mutasi bisnis lewat `App\Services\Dispatch\DispatchService` — satu-satunya jalur SAH (dipakai sama persis dari layar ini maupun tombol ✓ Confirm di grid `dispatch/do/list.blade.php`).

## Alur / Business Logic Penting
- **Tarik baris** (`DispatchService::pullLines`): 1 `co_delivery_id` terpilih = 1 `do_line` baru (1:1, TIDAK PERNAH digabung — DISPATCH-02). Baris `co_delivery_id` yang sudah ada di DO ini ditolak diam-diam (klik ganda), bukan error keras. Re-validasi `qty_available` LIVE dari VIEW dengan `lockForUpdate()` (bukan percaya angka client), guard ulang: customer match, `co_status='CONFIRMED'`, `qty_available > 0`. `qty_do` = `min(qty_available, ship_qty)`.
- **Update qty** (`updateLineQty`): batas atas dinamis = `qty_available` (VIEW, exclude baris ini sendiri) + `qty_do` baris ini sendiri.
- **Update harga** (`updateLinePrice`): override manual, `original_unit_price` diisi SEKALI saja (baris pertama override); set `price_overridden=true` dan `do_delivery.price_change_flag=true` kalau ada minimal 1 baris overridden.
- **Confirm** (`DispatchService::confirm`): 4 syarat re-validasi server-side (jangan percaya state client) — lines tidak kosong, semua qty > 0, qty masih dalam batas ceiling (re-cek race condition), CO asal masih `CONFIRMED`. Sukses → generate `do_no` (pola `DO/{YY}/{00001}` per tahun, `nextDoNo()`), set `status='CONFIRMED'`.
- **Tandai Terkirim** (`markShipped`): hanya dari `CONFIRMED`; set semua baris `PLANNED`→`SHIPPED`, `do_delivery.status='SHIPPED'` + `actual_ship_date=now()`. Untuk tiap `co_delivery_id` terkait, kalau total `qty_do` SHIPPED sudah >= `ship_qty` baris CPDEL asal, `co_order_delivery.status` ikut di-set `SHIPPED` — inilah titik yang menutup siklus baris di `co/delivery-plan.blade.php`.
- **Batalkan** (`cancelDo`): dari `DRAFT` atau `CONFIRMED` saja (bukan SHIPPED/CANCELLED), wajib isi alasan; semua baris jadi `CANCELLED`. `qty_available` di VIEW otomatis pulih (baris CANCELLED dikecualikan dari VIEW), tidak perlu aksi manual balik ke `co_order_delivery`.
- Tombol cetak "🖨️ Cetak DO" (harga tampil, Sisi A) dan "🖨️ Surat Jalan" (harga disembunyikan, Sisi B) muncul setelah `CONFIRMED`/`SHIPPED`.

## Catatan
Tidak ditemukan gap/TODO eksplisit dalam komentar kode file ini — komentar merujuk `dispatch_tier4.md`/`dispatch_tier2.md` sebagai acuan desain yang sudah diimplementasikan sesuai spec.
