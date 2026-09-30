# BACKLOG 01 — UI/UX, KOMPONEN & FASILITAS GRID/FORM

> Pecahan dari `BACKLOG.md`. Indeks semua backlog: **[`BACKLOG.md`](BACKLOG.md)**.
> Item SELESAI dipindah ke **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)** — tidak menumpuk di sini.
> Dirapikan ulang 2026-09-23 atas permintaan user: *"rapikan sehingga saya mudah
> memonitoring apa yang belum dikerjakan"*.

---

## CARA MEMBACA

| Status | Arti | Siapa yang bergerak |
|---|---|---|
| 🔴 **KEPUTUSAN** | Tidak bisa dikerjakan sebelum Anda memutuskan (pertanyaannya tertulis) | **Anda** |
| 🟡 **SIAP** | Keputusan lengkap, tinggal dikerjakan | Agen |
| 🔵 **BERJALAN** | Sedang dikerjakan bertahap, ada angka progres | Agen |
| ⚪ **IDE** | Dicatat, belum diminta / belum dijadwalkan | — |

Aturan: item hanya boleh berpindah status dengan menulis tanggal + alasan di
kolom Catatan. Item yang selesai **dihapus dari papan** dan dicatat di LOG.

---

## PAPAN MONITOR (per 2026-09-30)

> **Keputusan user 2026-09-30:** layar v1 tidak lagi dirapikan — cukup diganti layar v2
> (DevLib). Semua item perapian v1 (migrasi `x-ui`, statistik jadi filter, header form,
> angka rata kanan, filter dropdown, tema iframe) dihapus dari papan. Panel Filament
> `/admin` **bukan lagi arah ke depan** — item tema Filament ikut dihapus.

| ID | Pekerjaan | Status | Yang dibutuhkan untuk lanjut | Progres / catatan |
|---|---|---|---|---|
| UI-20 | Semua layar daftar v2 = turunan `DbGrid` (data DB, keluarga Grid Master/Transaksi/Report DevLib) | 🔵 BERJALAN | — | Selesai 2026-09-30: 13 HR, 15 Master/Produksi/COA/Pro-Cost/Product Market/Win-Rate, 3 laporan CO + General Report. Sisa: detail di bawah |
| UI-03 | Setelan grid "compact": kerapatan baris + baris per sel | 🟡 SIAP | — | Tempatnya sudah ada: drawer Configuration → Grid ("segera") |
| UI-16 | Grid spreadsheet: panah, multi-sel, tanda "berubah", undo | ⚪ IDE | — | `spreadsheetgridstandard.md` §8 |
| UI-17 | Edit tampilan nota lewat GUI (WYSIWYG) | ⚪ IDE | — | `printnotastandard.md` §8 |
| UI-18 | Kategori `sys_constant_category` baru (mis. HRM) | ⚪ IDE | Tambah saat ada konstanta baru | Sekarang 5 kategori |
| UI-19 | Layar cetak SPB/Penerimaan, Invoice Penjualan, Pembayaran Pembelian — dengan pilihan kop Non PPN/PPN | 🟡 SIAP | — (mekanisme kop PPN sudah ada) | Ketiga layar cetak belum ada; pakai `x-print.* :ppn` |

---

## BUKAN BACKLOG — keputusan sengaja (jangan dikira lupa)

- **Kotak cari di toolbar grid** tetap input mentah tanpa badge huruf (standar grid sendiri, `griduistandard.md` §6).
- **Email portal karier** tetap hardcode "TELASIH MAKMUR" — ditujukan ke calon karyawan eksternal (nickname perusahaan).
- **Gantt/Kanban/Nota**: daftar "yang sengaja tidak ditangani" di standarnya masing-masing (auto-assign, validasi konflik, zoom dinamis, dst) adalah batas desain, bukan tunggakan.
- **Login, Register, Portal Karier, kotak chat & AI, layar legacy `old_prodev*`/`inquiry/imposition*`** sengaja tidak ikut rollout komponen form.
- **`sec_user.active`** (kolom legacy) sengaja tidak di-rename ke `activate`.

---

## DETAIL PER ITEM

### UI-20 — Rollout DbGrid (sisa)  🔵
Pola: `app/Support/V2/Grid/DbGrid.php` + satu class per layar + daftar di `Grids::ALL`; buka/new ke form v1 lewat `?open=`/`?new=1`
(trait `OpensFromQuery`). Menu ddtsys `target='v2:<slug>'`. Laporan = submenu **Report** di dalam grupnya. Harga penjualan
dibuang di server lewat `restrictedKeys()` + `SecUser::canViewSalesPrice()`.
- **Grid Lookup** (edit di sel, `x-dl::lookup-grid`, butuh sambungan simpan DB): UoM, Currency, Kurs, Hari Libur, Kategori Supplier.
- **BOP Rule** (belum punya model Eloquent).
- **Recruitment**: Permohonan Tenaga Kerja, Lowongan, Talent Pool, Review Formulir, Jadwal Interview, Hasil Psikotes, Offering, Provisioning, Rekap Sync Log.
- **Inventory**: Stock In/Out/Return/Adjustment (transaksi) + Report Stock Balance/Stock Card/Slow Moving/Label History (menu sudah di submenu Report, layar belum).
- **Procurement**: Purchase Quotation, Purchase Return (grid v1), Report Outstanding PR/PO/PV.
- **Finance**: Cash & Bank, Down Payment, Journal, GL, Trial Balance, dst.
- **Production**: Work Order, Rule Library, Target Rate, Jadwal Shift, Output.
- **Setup**: Account/Group (Security), Language, Support Tickets.
- Menu ddtsys belum disalin ke tm2sys; migration `2026_09_30_000011` (field pengiriman CO) baru jalan di ddt*.

### UI-03 — Grid compact (kerapatan + baris per sel)  🟡
Keputusan lengkap (2026-09-22): Rapat/Normal/Longgar + Otomatis/Tetap N; **satu setelan untuk semua grid, per USER** (kolom `sec_user.grid_density`, `sec_user.grid_lines`), **tanpa** deteksi resolusi otomatis. Dua pintu: drawer Configuration → Grid, dan panel cepat di toolbar grid. Gotcha: `line-clamp` jangan di `<td>` langsung. Mockup: `docs/mockups/grid_kerapatan_baris.jpg`.

### UI-19 — Tiga layar cetak dokumen ber-PPN  🟡
SPB/Penerimaan Barang (`pur/pv`), Invoice Penjualan (`fin_invoice`/`dp-invoice`), Pembayaran Pembelian (`fin_voucher`) belum punya layar cetak. Keputusan user 2026-09-23: dokumen ber-PPN diberi pilihan kop Non PPN (default, "Telasih Makmur") / PPN ("CV. Langgeng Jaya Makmur" + NPWP), hanya saat cetak/preview. Mekanisme siap: `x-print.document :ppn` + `x-print.header :ppn` (`printnotastandard.md` §3a). Sumber flag PPN tiap dokumen ditentukan saat layarnya dibangun (Voucher `ppn_type`, Invoice `tax_amount`).

