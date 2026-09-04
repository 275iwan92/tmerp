---
name: screen_sales_win_rate
description: Laporan Win-Rate Sales — agregasi read-only inquiry berstatus CONVERTED/LOST, breakdown per PIC Sales + detail deal kalah.
---

# Win-Rate Sales

**File**: `resources/views/pages/sales/win-rate.blade.php`
**Route**: `sales.win-rate` — `GET /sales/win-rate`
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Sales Order & Delivery (Laporan)

## Tujuan
Layar REPORT murni (read-only) yang mengagregasi win-rate Sales dari `iqp_inquiry.sub_state` bernilai `CONVERTED` (menang/jadi CO) atau `LOST` (kalah). Tidak ada input/edit di sini — hanya filter tanggal dan tabel agregasi. Aktor: Sales Manager/Direksi (evaluasi performa Sales).

## Field & Kolom
**Filter**: `dateFrom`, `dateTo` (date-picker, live) — filter `inquiry_date` inquiry yang di-agregasi; tombol "Reset filter" muncul kalau salah satu terisi.

**Stat box**: Deal Menang (CONVERTED), Deal Kalah (LOST), Win-Rate (%).

**Tabel "Win-Rate per PIC Sales"** (`byPic`): PIC Sales (grouped dari `pic_sales`, `'(tanpa PIC)'` kalau kosong), Menang, Kalah, Total, Win-Rate % — urut `total` desc.

**Tabel "Detail Deal Kalah (LOST)"** (`lostRows`): Inquiry (identitas: `product_draft_name` + `inquiry_no`), Tanggal, PIC Sales, Alasan Kalah (`cancel_reason`), aksi ↗ Buka.

## Aksi / Method
- `terminalInquiries()` [Computed] — `IqpInquiry::whereIn('sub_state', ['CONVERTED', 'LOST'])`, filter `dateFrom`/`dateTo` (`whereDate('inquiry_date', ...)`), order `inquiry_date` desc.
- `wonCount()` / `lostCount()` [Computed] — count per `sub_state`.
- `winRatePct()` [Computed] — `won / (won+lost) * 100`, dibulatkan 1 desimal, 0 kalau total 0.
- `byPic()` [Computed] — group `terminalInquiries` per `pic_sales`, hitung won/lost/total/rate per grup, urut `total` desc.
- `lostRows()` [Computed] — subset `terminalInquiries` yang LOST, urut `inquiry_date` desc.
- `resetFilter()` — kosongkan `dateFrom`/`dateTo`.
- `openInquiry(int $id)` — redirect ke `inquiry.hub` (Hub polos, BUKAN langsung ke inquiry spesifik — lihat Catatan).

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` → `tm2sdi.iqp_inquiry` (kolom kunci: `sub_state`, `pic_sales`, `inquiry_date`, `cancel_reason`).

## Alur / Business Logic Penting
- **Sumber data `sub_state`**: kolom `iqp_inquiry.sub_state` dengan value `CONVERTED`/`LOST` sudah ada di skema sejak awal, tapi baru ada 2 titik kode yang benar-benar menuliskannya (ditambahkan 2026-09-02):
  - `CONVERTED` ditulis oleh `App\Services\Sales\CoOrderBuilder::findOrCreateFromQuotation()` — saat CO pertama kali lahir dari Quotation (deal resmi menang), sekaligus set `co_id` = CO yang lahir.
  - `LOST` ditulis oleh `inquiry/form.blade.php::markLost()` (di luar cakupan dokumentasi sesi ini).
  - Layar ini murni agregasi read-only dari kedua titik tulis tersebut.

## Catatan
- `openInquiry()` redirect ke `inquiry.hub` polos (bukan ke inquiry spesifik dengan pre-select) — komentar kode menjelaskan `inquiry.hub` belum mendukung query-param pre-select, jadi officer harus cari manual (nomor inquiry sudah tampil di kolom identitas grid ini sebagai bantuan).
