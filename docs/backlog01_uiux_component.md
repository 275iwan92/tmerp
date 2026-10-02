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
| UI-20 | Semua layar daftar v2 = turunan `DbGrid` (data DB, keluarga Grid Master/Transaksi/Report DevLib) | 🔵 BERJALAN | — | Selesai 2026-09-30: 13 HR, 15 Master/Produksi/COA/Pro-Cost/Product Market/Win-Rate, 3 laporan CO + General Report. 2026-10-02: + 25 (Inventory, WO, Outstanding, Recruitment), malam + 19 (lookup Master Data, laporan stok, Purchase Return, Support Tickets, Finance). Sisa: detail di bawah |
| UI-16 | Grid spreadsheet: panah, multi-sel, tanda "berubah", undo | ⚪ IDE | — | `spreadsheetgridstandard.md` §8 |
| UI-17 | Edit tampilan nota lewat GUI (WYSIWYG) | ⚪ IDE | — | `printnotastandard.md` §8 |
| UI-21 | Multi language — sisa setelah satu kamus tanpa hardcode (2026-10-02) | 🔴 KEPUTUSAN / 🟡 SIAP | Lihat detail | Konversi selesai (PR 275iwan92/tmerp#8); sisa = data di DB, format angka zona beku, verifikasi lokal |

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
- **Sisa = layar v1 yang BUKAN daftar** (papan, proses, dasbor) -- menunggu rancangan v2 masing-masing, bukan pekerjaan DbGrid:
  Finance (Bank & Reconciliation, Period Closing, Tax, Balance Sheet, Income Statement, Cash Flow, Financial Statements, Dashboard, Process Engine),
  Production (Target Rate -- sedang dikerjakan ulang oleh speed-target engine; Jadwal Shift, MPS Gantt / Kanban / Spreadsheet),
  Setup (Group per Submodule, Migration, Language, Localization, Identifikasi, Dummy & Legacy Data),
  HR (Struktur Organisasi, Self-Service, Upload Presensi, Generator Transfer Bank, Roster Cash, Pipeline Board, Shift Roster), Laporan Manajemen (7 dasbor).
- Inventory: Label History belum punya layar / data.
- Semua daftar v1 lain SELESAI 2026-10-02 (LOG): lookup Master Data, laporan stok, Purchase Return, Support Tickets, Finance (9 layar), Constants,
  Constant Categories, Account, Group, Talent Pool, BOP Rule, Product Category.
- Migration `2026_09_30_000011` (field pengiriman CO) baru jalan di ddt*.

### UI-21 — Multi language: sisa setelah konversi (2026-10-02)
- 🟡 **Verifikasi lokal**: `php artisan migrate` (tm2 + `--env=mockup`), `npm run build`, `php artisan test`. Test yang
  meng-assert teks Indonesia lulus hanya setelah migrasi kamus `000901`–`000931` jalan dan bahasa test = id.
- 🟡 Cek di browser bahasa id: layar ProDev v1 (`pages/production/prodev`, isi `@verbatim` diterjemahkan lewat
  `JS_TEXT` + satu pass DOM; teks JS baru wajib masuk `JS_TEXT`).
- 🔴 **Teks Indonesia yang DISIMPAN ke database** (sengaja tidak diubah): narasi jurnal & voucher, catatan stok/PO/PR,
  isi notifikasi yang di-insert, `sales_note` RECALC/REVISE, nama default (Langkah baru, BAHAN LAIN, Part n), label cetak
  approval `PRINT_LABELS`, `cust_customer.note` stempel unblock, nilai `field_name` koreksi karyawan. Aturan B.3 (DB English)
  -> perlu keputusan: ubah ke English + tampilkan lewat kamus (data lama dikonversi) atau biarkan.
- 🔴 Format angka `toLocaleString('id-ID')` di JS `mockup-parts` (formation, flow-config, dieline, workbench — zona beku)
  -> pindah ke `Devlib.h.NUM` / Locale perlu izin.
- 🟡 Opsi select English yang belum lewat `t()`: `MntCode::TYPES`, `UsageStandardLookup::BASIS` (pola: `array_map('t', ...)`;
  DevLib sengaja tidak menerjemahkan `options` karena sering berisi data).
- 🔴 `tm2sys.sys_translations` boleh di-drop? (sudah tidak dipakai kode); menu `SET_LOCALIZATION` kini hanya mengalihkan.
- ⚪ Kata milik paket DevLib (`Devlib\Lang::DICT`, `PrintDoc\Labels`) punya pasangan ID bawaan paket (devlib.test tanpa DB);
  bahasa ketiga lewat kamus. Help (`resources/data/help/*.json`) dua bahasa per entri — sistemnya sendiri.
- ⚪ `App\Support\V2\DemoData` tidak dipakai (kandidat hapus). `app/Console` (output developer) & Filament (dipensiunkan) tidak dikonversi.

