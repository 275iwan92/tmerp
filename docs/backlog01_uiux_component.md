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

## PAPAN MONITOR (per 2026-09-23)

| ID | Pekerjaan | Status | Yang dibutuhkan untuk lanjut | Progres / catatan |
|---|---|---|---|---|
| UI-01 | Migrasi layar ke komponen `x-ui.*` (tombol, badge, tabel, modal, warna token) | 🔵 BERJALAN | — (per modul, esensi dibuktikan sama) | Selesai: Master Mesin, Master Proses, DevLib. Sisa (ratchet): **990** tombol mentah · **5.661** hex · **71** modal manual · **248** tabel mentah |
| UI-02 | Kotak statistik grid = FILTER (perilaku Master Pegawai) | 🟡 SIAP | — | **28** grid sudah · **42** belum (daftar di detail) |
| UI-03 | Setelan grid "compact": kerapatan baris + baris per sel | 🟡 SIAP | — | Tempatnya sudah ada: drawer Configuration → Grid ("segera") |
| UI-04 | Header form entry (ikon+judul+no dok+badge+cetak+Back) jadi komponen | 🟡 SIAP | — | ≥6 layar menyalin markup yang sama |
| UI-05 | Angka rata kanan di semua grid/form lama | 🔵 BERJALAN | — | Selesai: 7 nota cetak, payroll, Master Pegawai/Mesin/Proses. Sisanya ikut UI-01 per modul |
| UI-12 | Filter dropdown toolbar yang dulu dihapus | 🔴 KEPUTUSAN | Dikembalikan atau tidak? | tipe/kategori/divisi/dimensi/sembunyikan-nonaktif |
| UI-14 | Verifikasi visual tema panel Filament | 🔴 KEPUTUSAN | Perlu Anda login & lihat tiap mode | Mekanisme sudah benar (cek via HTTP) |
| UI-15 | Layar transaksi (iframe) ikut tema gelap `sys_theme` | ⚪ IDE | — | Token warna (UI-01) prasyaratnya |
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

### UI-01 — Migrasi layar ke komponen `x-ui.*`  🔵
- Standar & prosedur: `docs/componentstandard.md` (§6 = bukti esensi: render lama vs baru, 0 `wire:*` hilang, teks & field sama).
- Pagar: `tests/Feature/UiRatchetTest.php` — angka tidak boleh naik; turunkan baseline tiap kali turun.
- Zona beku (Inquiry/ProDev/ProCost): **tanya dulu per perubahan** (CLAUDE.md §6B). Terjemahan ID→EN sudah disetujui.
- Urutan usulan: modul yang sedang aktif dulu (Customer Order berikutnya sesuai urutan kerja), lalu per modul.
- Termasuk: label tombol Simpan/Batal/Tutup yang ikut `lang_code` — otomatis lewat `x-ui.button preset="..."` (kunci `shared_btn_*`), jadi selesai begitu layarnya dimigrasi.

### UI-02 — Kotak statistik = filter  🟡
Mekanisme selesai (`HasGridStatFilter` + `x-grid.stat-filter-row`, acuan hidup di DevLib tab Grid). Per layar cukup `statFilterDefs()` ± 15 baris.
**Belum (42):** co/delivery-plan · finance/cash-bank · finance/dp-invoice · hrm/attendance-daily · hrm/correction-requests · hrm/discipline-report · hrm/employee-contact-gap · hrm/gps-approval · hrm/leave-requests · hrm/payroll-batch-run · hrm/payroll-employee-bank · hrm/payroll-employee-salary · hrm/payroll-payslip-list · hrm/sop-documents · inquiry/hub *(zona beku)* · inquiry/imposition-list *(legacy)* · inventory/stock-adjustment · inventory/stock-adjustment-approval · master/exchange-rate · master/holiday · master/mat-category · master/raw-material/index · production/bop-rule · production/master-crew · production/mts-forecast · production/old_prodev_list *(legacy)* · production/process-rate · production/scheduling-board · pur/po/approval · pur/pq/index · pur/pr/approval · pur/prt/approval · pur/prt/index · pur/pv/index · pur/report/outstanding-po · pur/report/outstanding-pr · pur/report/outstanding-pv · recruitment/sync-log · sales/win-rate · security/index · setup/component-library (tab Kanban) · setup/data-origin
- `master/mat-category` = grid pohon 5 level; filter memutus pohon → putuskan dulu bentuknya saat dikerjakan.
- Grid laporan (outstanding, win-rate, cash): kotak berisi rupiah/rata-rata dibiarkan tidak bisa diklik (bukan kategori).

### UI-03 — Grid compact (kerapatan + baris per sel)  🟡
Keputusan lengkap (2026-09-22): Rapat/Normal/Longgar + Otomatis/Tetap N; **satu setelan untuk semua grid, per USER** (kolom `sec_user.grid_density`, `sec_user.grid_lines`), **tanpa** deteksi resolusi otomatis. Dua pintu: drawer Configuration → Grid, dan panel cepat di toolbar grid. Gotcha: `line-clamp` jangan di `<td>` langsung. Mockup: `docs/mockups/grid_kerapatan_baris.jpg`.

### UI-04 — Header form entry jadi komponen  🟡
Disalin manual di `pur/pr`, `pur/po`, `co`, `pur/pq`, `pur/prt`, `quotation` entry. Variasi yang harus diakomodasi: badge status (4 tone), label tombol cetak, elemen tambahan kanan (CO/Quotation). Tombol Back sudah komponen (`x-back-button`, dipakai 23 layar) dan badge sudah `x-form.badge`.

### UI-05 — Angka rata kanan  🔵
Aturan: class `num` pada judul kolom + sel angka (`componentstandard.md` §5). Default tabel lama sudah di `@layer base` sehingga class per sel kini berlaku di semua layar. Sisa kolom angka di grid lama dikerjakan bersama UI-01 per modul. Zona beku (Inquiry nota & costing, 4 sel) menunggu izin per perubahan.

### UI-12 — Filter dropdown yang dulu dihapus  🔴
`griduistandard.md` §10: filter tipe/kategori/divisi/dimensi/sembunyikan-nonaktif pernah dihapus dari beberapa toolbar. Kembalikan?

### UI-14 — Visual tema Filament  🔴
Mekanisme terverifikasi via HTTP kernel; tampilan tiap mode belum dilihat di browser sungguhan (butuh login Anda).

### UI-19 — Tiga layar cetak dokumen ber-PPN  🟡
SPB/Penerimaan Barang (`pur/pv`), Invoice Penjualan (`fin_invoice`/`dp-invoice`), Pembayaran Pembelian (`fin_voucher`) belum punya layar cetak. Keputusan user 2026-09-23: dokumen ber-PPN diberi pilihan kop Non PPN (default, "Telasih Makmur") / PPN ("CV. Langgeng Jaya Makmur" + NPWP), hanya saat cetak/preview. Mekanisme siap: `x-print.document :ppn` + `x-print.header :ppn` (`printnotastandard.md` §3a). Sumber flag PPN tiap dokumen ditentukan saat layarnya dibangun (Voucher `ppn_type`, Invoice `tax_amount`).

