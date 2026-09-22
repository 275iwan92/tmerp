---
name: screen_finance_dp_invoice
description: Tagihan Uang Muka (Down Payment) customer — terbitkan DP, catat pembayaran, alokasikan ke invoice final.
---

# Tagihan Uang Muka (Down Payment) — Customer DP

**File**: `resources/views/pages/finance/dp-invoice.blade.php`
**Route**: `finance.dp-invoice` (`/finance/dp-invoice`)
**Koneksi DB utama**: acc (tm2acc)
**Modul**: Finance & Accounting

## Tujuan
Mengelola alur uang muka (DP) dari customer, sisi SALES DP (kasus utama pabrik) — Purchase DP (arah sebaliknya) belum dibangun. Layar ini mengimplementasikan 3 dari 4 event alur DP: (1) Terbitkan DP Invoice, (2) Catat Pembayaran, (4) Alokasikan ke invoice final. Event (3) — pembuatan invoice final per shipment — milik modul Delivery Order yang belum ada, sehingga fitur Alokasi diuji dengan invoice final yang saat ini di-insert manual di luar layar ini. `co_reference` (referensi SO/CO) masih input teks bebas, belum di-rewiring ke `co_order.id_co` asli meski tabel itu sudah ada sejak 2026-08-20. Aktor: staf Finance/Sales yang menerbitkan tagihan DP dan memproses pembayarannya.

## Field & Kolom

### Modal Terbitkan DP (Event 1)
- `customer_id` — select dari `CustCustomer::where('activate',true)`, wajib; menampilkan info bebas PPN kalau `tax_profile === 'EXEMPT'`
- `co_reference` — No. Referensi CO/SO, teks bebas opsional (BELUM link ke `co_order` asli — lihat Catatan)
- `contract_value` — Nilai Kontrak (Nilai SO), wajib, numerik min 0.01
- `dp_method` — `percent` (persentase dari nilai kontrak) atau `fixed` (nominal tetap)
- `dp_percent` — wajib jika method=percent (0.01–100)
- `dp_fixed_amount` — wajib jika method=fixed (min 0.01)
- `entry_date`, `due_date` — tanggal faktur & jatuh tempo DP (due_date >= entry_date)
- `print_note` — catatan/instruksi cetak, opsional
- Akun Kewajiban UM ditampilkan readonly (`213200 — Uang Muka Penjualan dari Pelanggan`)
- Kalkulasi real-time (`calc` computed): subtotal DP, PPN 11% (0 jika customer EXEMPT), total tagihan

### Modal Alokasi DP (Event 4)
- `alloc_final_invoice_id` — select invoice final terbuka milik customer yang sama (`invoice_type=CUSTOMER_INVOICE`, `payment_status != PAID`), wajib, harus berbeda dari DP invoice-nya sendiri
- `alloc_base` — Potong Pokok (Rp), wajib min 0.01
- `alloc_tax` — Potong PPN (Rp), opsional min 0
- Info sisa DP tersedia ditampilkan sebagai referensi

### Grid daftar DP Invoice
- Kolom: No. Invoice DP, Customer, Tanggal, Total DP, Sisa Belum Teralokasi, Status (badge UNPAID/PARTIAL/PAID), Aksi (Catat Pembayaran / Alokasikan / "Habis Terpakai")
- Stat box: Menunggu Dibayar, Lunas, Sisa DP Belum Teralokasi (total)

## Aksi / Method
- `openEntryModal()` — reset form, default `dp_method=percent`, `dp_percent=50`, tanggal hari ini + due 7 hari
- `issueDpInvoice()` — Event 1: validasi, hitung PPN via `calc`, buat `FinInvoice` (`invoice_type=CUSTOMER_DP`, `payment_status=UNPAID`) + `FinVoucher` (journal SAL, status langsung POSTED) + baris jurnal (AR debit total; Uang Muka kredit subtotal; PPN Keluaran kredit jika ada) dalam transaksi `tm2acc`
- `recordPayment(int $invoiceId)` — Event 2: buat `FinVoucher` (journal BNK) + 2 baris jurnal (Bank debit, AR kredit sejumlah `total_amount`), update invoice jadi `paid_amount=total_amount`, `payment_status=PAID`
- `openAllocModal(int $dpInvoiceId)` / `allocateDp()` — Event 4: validasi jumlah alokasi tidak melebihi sisa DP maupun sisa outstanding invoice final; buat `FinDpAllocation` + `FinVoucher` (journal GEN) + baris jurnal (DP debit, PPN debit jika ada, AR kredit); update `paid_amount`/`payment_status` invoice final (PARTIAL/PAID)
- `accountId(string $code)` / `journalId(string $code)` — helper lookup ID dari kode
- `nextInvoiceNumber()` / `nextVoucherNumber(string $journalCode)` — generator nomor dokumen (format `INV-DP-YYYY-MM-NNNN` / `VCH-{kode}-YYYYMM-NNNN`), **tanpa** `lockForUpdate()` (beda dari cash-bank.blade.php)

## Tabel & Model yang Disentuh
- `App\Models\Acc\FinInvoice` — tabel `fin_invoice` (koneksi `tm2acc`, PK `invoice_id`)
- `App\Models\Acc\FinDpAllocation` — alokasi DP ke invoice final (koneksi `tm2acc`)
- `App\Models\Acc\FinDpOutstanding` — kemungkinan VIEW/model untuk sisa DP per invoice (dipakai hitung `dp_remaining`), koneksi `tm2acc`
- `App\Models\Acc\FinVoucher` / `FinVoucherLine` — jurnal GL (koneksi `tm2acc`)
- `App\Models\Acc\FinAccount`, `FinJournal` — lookup kode akun/jurnal (koneksi `tm2acc`)
- `App\Models\Master\CustCustomer` — tabel `cust_customer` (koneksi `tm2mst`, **lintas-DB** dari layar ini — dipakai murni untuk lookup nama/kode customer & `tax_profile`, tidak di-JOIN, sesuai pola 1 CLAUDE.md §2)

## Alur / Business Logic Penting
- **Akun hardcode by kode**: AR (`112100` domestik / `112200` import berdasarkan `customer_type`), Uang Muka (`213200`), PPN Keluaran (`212100`), Bank (`111200`) — semua di-resolve via `accountId()` memanggil `FinAccount::where('account_code', $code)`, bukan hardcode ID.
- **PPN 11% otomatis kecuali EXEMPT**: `calc()` computed menghitung PPN berdasarkan `selectedCustomer->tax_profile === 'EXEMPT'` (kawasan berikat).
- **DP langsung POSTED**: berbeda dari `cash-bank.blade.php` yang draft-first, semua voucher di layar ini dibuat langsung `status=POSTED` — tidak lewat `JournalPostingService`, jurnal ditulis manual per event (komentar kode menyebut `JournalPostingService` sengaja digeneralisasi belakangan dari pola inline di file ini).
- **Gate produksi**: pesan UI eksplisit "🔒 Work Order tidak bisa dibuka sampai DP ini lunas" — menandakan DP lunas adalah prasyarat WO (implementasi gate itu sendiri ada di modul lain, tidak di file ini).
- **Validasi alokasi ganda**: `allocateDp()` mengecek alokasi tidak melebihi sisa DP (`allocatingDpRemaining`) DAN tidak melebihi sisa outstanding invoice final tujuan.

## Catatan
- `co_reference` masih teks bebas — komentar kode eksplisit menyebut kolom `co_id` "tetap disimpan logis-opsional untuk rewiring sesi lain", belum benar-benar dipakai untuk link ke `co_order` asli meski tabel itu sudah ada.
- Purchase DP (arah sebaliknya, simetris) belum dibangun di layar ini — disebut di komentar sebagai scope menyusul (`§2B-5`).
- Event 3 (invoice final per shipment, milik Delivery Order) belum ada — fitur Alokasi bergantung pada invoice final yang saat ini harus di-insert manual di luar alur normal.
- Generator nomor dokumen (`nextInvoiceNumber`/`nextVoucherNumber`) TIDAK pakai `lockForUpdate()` — berbeda dari pola di `cash-bank.blade.php`, berpotensi race condition pada concurrent submit (tidak diverifikasi ada mitigasi lain).
