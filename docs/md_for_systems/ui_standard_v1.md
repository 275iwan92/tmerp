# Standar UI v1 (satu berkas): grid, form, cetak, menu, gantt, kanban, spreadsheet

> Disarikan 2026-10-14 dari 15 berkas (3.370 baris): `griduistandard`, `FORM_UI_STANDARD`, `componentstandard`, `printnotastandard`, `sidebarmenustandard`, `ganttboardstandard`, `kanbanboardstandard`, `spreadsheetgridstandard`, `reportstandard`, dan 6 katalog `uistandard/` (digantikan `resources/views/components/_manifest.yaml`). Keputusan Jo 14-Okt. Asli: `tmerp_unused/` dan `php artisan md:library search "grid"`.
> **Berlaku untuk layar v1 (Livewire SFC, `x-form.*`, `x-grid.*`).** Layar v2 = objek DevLib `x-dl::*` yang membawa aturan ini (rulebook C.3-C.11, C.22, C.28-C.29). Komentar kode yang menulis `griduistandard.md §n` merujuk nomor § di bagian Grid di bawah.

## Grid (ex griduistandard.md, nomor § asli)
- **§1** Tanpa header halaman: shell sudah menampilkan nama layar lewat tab; grid langsung mulai dari kotak statistik / filter.
- **§2** Kotak statistik pastel solid (bukan gradient, bukan teks putih); lima warna baku: Total biru, Aktif hijau, Perhatian kuning, Kritis merah, Tambahan ungu. Maks 5 per baris. Jangan `grid-cols-N` Tailwind mentah: `.grid{}` di `app.css` tidak berlapis dan selalu menang.
  **§2b (LOCKED 2026-09-21)** kotak statistik = filter: klik menyaring, klik lagi melepas (trait `HasGridStatFilter`, `statFilterDefs()`); angka selalu menghitung keseluruhan (bukan hasil saringan); kotak non-kategori (Total, rupiah, tabel lain) tanpa `'filter'`; klausa filter sama persis dengan klausa penghitung. (Di v2: kotak diganti satu baris filter teks, C.22.)
- **§3** Kolom identitas tunggal paling kiri: avatar inisial (gradient dari PK modulo palet) + Nama tebal + Kode mono; entitas tanpa kode = hanya nama, jangan mengarang kode.
- **§4** Klik 2x baris = satu-satunya cara membuka detail (`cursor-pointer`, title "Klik 2x untuk buka detail"); tanpa radio / checkbox select; ikon Aksi memakai `.stop`.
- **§5** Kolom Aksi grid Master = tepat 2 ikon berbingkai Font Awesome (LOCKED 2026-09-21): `fa-pen` Edit + toggle aktif lewat `<x-grid.action-icon :active=...>` (ikon dan warna ditentukan komponen). Ikon baku: Buka `fa-up-right-from-square`, Confirm `fa-check`, Batal `fa-xmark`, Lihat `fa-eye`. Grid non-Master tidak pernah punya toggle aktif: ↗ Buka + ✓ Confirm kondisional (DRAFT dan punya baris, `wire:confirm`).
- **§6** Toolbar hanya search + "+ New Record" (LOCKED); grid worklist yang recordnya lahir dari layar lain = search saja plus satu catatan pastel. Tanpa filter / dropdown / checkbox tambahan. Pengecualian: tombol Kolom / Sort / Export (§10) dan toggle List / Card (§11).
- **§8** Approval Center = antrian grid; Approve / Reject / Hold diputuskan di form asli dokumen (read-only saat status ≠ DRAFT) dengan blok "Keputusan Approval" + timeline; Hold tidak mengubah status, hanya baris `flow_log` (`FlowEngineService::hold()`); tanpa "+ New Record".
- **§9** Expand-row (`›`, kolom paling kiri, `w-[32px]`): preview item read-only inline, bukan modal; `wire:click.stop`; eager-load relasi item; boleh banyak terbuka.
- **§10** Custom view opsional per grid (trait `HasCustomizableGrid`, 5 method kontrak `gridKey/gridColumnDefs/gridDefaultColumns/gridBaseQuery/columnValue`; preferensi di `sys_grid_preference`): modal Kolom (dual-listbox, Terapkan/Batal), Sort satu kolom, group-by dari kolom tampil (per halaman), Export Excel/CSV/PDF (PDF maks 200 baris). Kolom identitas dicari lewat `gridIdentitySearchColumns()`; "Sama Dengan" wajib `CAST(col AS CHAR)`; kolom relasi lintas-DB wajib `gridCrossDbSearchResolvers()` (jangan `whereHas` bertingkat); dropdown di toolbar `overflow-x-auto` wajib `x-teleport="body"`; footer pagination 3 bagian (Go to / View 10-25-50-100 / rentang); multi-delete hanya baris draft dengan guard di query.
- **§11** Card View hanya Master Employee (foto asli); klik tetap 2x; state di properti biasa.
- **§12** Chrome gabungan PowerGrid + Filament untuk grid ber-`HasCustomizableGrid`: tombol Filters + panel baris, sort-header selalu berpanah, Export dropdown ikut kolom tampil, Aksi dropdown "⋮", toggle-switch (tetap `wire:confirm`), chevron `›`, Ctrl / Shift multi-select, filter selalu `CAST(col AS CHAR)`.
- **§13** Scrollbar tipis `.tm-scroll` di semua wrapper scroll grid dan form.

## Form / entry (ex FORM_UI_STANDARD.md)
- **Back** = pill navy + lingkaran panah putih; kembali ke layar sebelumnya (LOCKED 2026-09-18); form berubah memakai `data-dirty-guard`; kata "Back" selalu English.
- **Tombol aksi baku satu kata di semua layar (LOCKED 2026-09-10)**; "+ Tambah [objek]" di dalam form dikecualikan. v2: New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back (C.5).
- **Input teks (LOCKED 2026-09-21):** `x-form.input` / `x-form.textarea` membawa badge aa / Aa / AA; nilai yang ada di master wajib lookup (`x-form.searchable-select` / `select`); warna lewat token `--color-field-*`, bukan hex.
- Jangan pakai Tailwind `grid grid-cols-N` mentah (kena `.grid{}` tak berlapis); gunakan komponen.
- Komponen input acuan `docs/mockups/UIUX_components_part1.html`; scrollbar `.tm-scroll`; popover date-picker dan dropdown searchable wajib `tm-scroll`.

## Komponen OOP (ex componentstandard.md)
Empat lapis: token → primitive `x-ui.*` (Button / Badge / Card / Tabs / Table / Modal) → komponen domain → layar; warisan lewat `@props` + slot. Aturan wajib halaman baru: dilarang `<button class=...>`, hex literal, `fixed inset-0`; angka rata kanan, teks rata kiri (2026-09-23). Migrasi layar lama = "kulit saja, esensi tetap" (boleh juga di zona beku). Penerus v2: skill `devlib-oo` dan D.15 / D.17 / D.18.

## Cetak "Nota" (ex printnotastandard.md)
Dua keluarga cetak jangan dicampur: **Nota** (dokumen transaksi ke pihak luar: kop, pihak, tanda tangan) dan **Laporan** (header navy + Back, landscape). Nota = komponen reusable (props, tema warna lewat prop `theme`, bukan kelas per dokumen); nama perusahaan + pilihan kop PPN + angka rata kanan; class generik boleh dipakai langsung. Penerus: generator OO `Devlib\PrintDoc\Document` (C.24).

## Menu sidebar (ex sidebarmenustandard.md)
Setiap modul punya grup input (transaksi / approval / worklist) dan tepat satu grup **"Laporan"**; item laporan memakai nama asli layarnya (`#[Title]`) tanpa awalan "Laporan"; grup otomatis collapsible; item belum jadi (`NOT_BUILT`) tetap tampil sebagai placeholder, hanya yang retired di-`activate=0`; `sys_app_menu.id` bukan auto_increment (MAX+1). Inventory: tiga kelas stok = tiga menu terpisah. Procurement: Purchase Receive punya 3 jenis penerimaan, semua menjadi Stock IN setelah confirm + approval atasan.

## Gantt, Kanban, Spreadsheet grid
- **Gantt** (`<x-gantt.board>`): kontrak data Row VM / Block VM, drag geser + resize, highlight lintas-boundary lewat Alpine store `gantt`; jangan menulis nama tag komponen di dalam komentar (parser Blade).
- **Kanban**: kolom = status diskret, kartu bisa digeser; **validasi di server** (UI hanya saran), selalu sediakan tombol aksi non-drag; ekstrak pola hanya bila ≥ 2 pemakai.
- **Spreadsheet grid** (`HasSpreadsheetGrid`, `<x-grid.spreadsheet-table>`): hanya master kecil / scalar (Currency, UOM); simpan BATCH (bukan autosave); AutoFill sederhana (tanpa deteksi seri ala Excel); lisensi DataTables Editor tidak dipakai, versi sendiri.

## Report 4 level (ex reportstandard.md, desain terkunci 2026-09-02, belum diimplementasi)
Level Data Entry / Supervisor / Manager / Top Management memakai skala `sec_group.mgmt_level` yang sudah ada; approval 2 tingkat di dalam Top Management; mekanisme "kode otorisasi supervisor" baru; matriks level × bidang. Katalog laporan Top Management: `topmanagementreport_tier1.md`. Penerus: Executive Summary / Laporan Manajemen di v2.
