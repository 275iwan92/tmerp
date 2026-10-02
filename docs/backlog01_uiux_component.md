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
| UI-03 | Setelan grid "compact": kerapatan baris + baris per sel | 🟡 SIAP | — | Tempatnya sudah ada: drawer Configuration → Grid ("segera") |
| UI-16 | Grid spreadsheet: panah, multi-sel, tanda "berubah", undo | ⚪ IDE | — | `spreadsheetgridstandard.md` §8 |
| UI-17 | Edit tampilan nota lewat GUI (WYSIWYG) | ⚪ IDE | — | `printnotastandard.md` §8 |

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

### UI-03 — Grid compact (kerapatan + baris per sel)  🟡
**Keputusan user 2026-10-02 (menggantikan sebagian keputusan 2026-09-22 di bawah):** (1) setelan cukup seperti yang terpasang --
per grid, tersimpan di browser lewat tombol Kerapatan di toolbar grid; kolom `sec_user.grid_density` / `grid_lines` TIDAK dibangun.
(2) Grid Pre-Entry tetap tanpa tombol Kerapatan. (3) Pintu kedua di menu Profile: MENUNGGU user melihat preview
`docs/mockups/ui03_grid_door_preview.png` (A = tanpa pintu, B = halaman "Grid" di Profile v2 sebagai bawaan semua grid di browser).
Catatan: tulisan "segera" hanya ada di drawer Configuration menu lama (v1); menu v2 tidak punya.
Keputusan lengkap (2026-09-22): Rapat/Normal/Longgar + Otomatis/Tetap N; **satu setelan untuk semua grid, per USER** (kolom `sec_user.grid_density`, `sec_user.grid_lines`), **tanpa** deteksi resolusi otomatis. Dua pintu: drawer Configuration → Grid, dan panel cepat di toolbar grid. Gotcha: `line-clamp` jangan di `<td>` langsung. Mockup: `docs/mockups/grid_kerapatan_baris.jpg`.

