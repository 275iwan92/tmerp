---
name: uistandard_component_catalog_index
description: Indeks konsolidasi seluruh komponen Blade reusable (resources/views/components/**) + cross-reference ke dokumen standar UI/UX existing.
---

# Indeks Katalog Komponen Blade (tmerp)

> Pintu masuk katalog KOMPONEN KONKRET (nama tag, props, contoh pakai) —
> pelengkap dokumen KONSEP di `docs/*standard*.md` (lihat `uistandardindex.md`
> untuk peta dokumen konsep). Dokumen ini TIDAK menjelaskan aturan visual/
> alasan desain — cek file katalog per-kategori atau dokumen standar terkait
> untuk itu.

## Cara pakai

Cari nama tag di tabel bawah → buka file katalog yang tercantum di kolom
"File Katalog" → baca props/slot/contoh lengkapnya di sana. 5 file katalog:

| File Katalog | Cakupan |
|---|---|
| `uistandard_component_form.md` | `resources/views/components/form/` (8 komponen) |
| `uistandard_component_grid.md` | `resources/views/components/grid/` (18 komponen) |
| `uistandard_component_print.md` | `resources/views/components/print/` (9 komponen) |
| `uistandard_component_gantt_kanban.md` | `resources/views/components/gantt/` (3 komponen) + konvensi Kanban (belum ada komponen) |
| `uistandard_component_other.md` | `alert/` (2), `auth/` (2), `inquiry/` (1), `org-chart/` (1), `prodev/` (1) |

---

## Tabel ringkas — semua ~45 komponen

### Form (`components/form/`)
| Tag | Fungsi 1-baris |
|---|---|
| `<x-form.input>` | Label+input+error, dukung dialek legacy CSS global & Tailwind |
| `<x-form.textarea>` | Label+textarea+error, opsi `autogrow` (tinggi otomatis) |
| `<x-form.select>` | Label+select+error dari array `options` |
| `<x-form.checkbox>` | 1 baris checkbox+label inline |
| `<x-form.toggle-group>` | Wrapper tombol 2-3 pilihan (pengganti `<select>`, workaround bug morph Livewire v4) |
| `<x-form.toggle-option>` | 1 tombol pilihan di dalam `toggle-group` |
| `<x-form.badge>` | Pill status (DRAFT/CONFIRMED/dst), 5 tone baku |
| `<x-form.date-picker>` | Date/datetime picker kustom (pengganti native `<input type=date>`) |

### Grid (`components/grid/`)
| Tag | Fungsi 1-baris |
|---|---|
| `<x-grid.stat-row>` | Wrapper baris kotak statistik pastel |
| `<x-grid.stat-box>` | 1 kotak statistik, 5 tone baku |
| `<x-grid.identity-cell>` | Sel `<td>` avatar-gradient+nama+kode |
| `<x-grid.action-icon>` | 1 tombol ikon Aksi (Edit/Toggle/Buka/Confirm) |
| `<x-grid.toggle-switch>` | Visual switch aktif/nonaktif (pengganti ikon ⏻, tetap wajib confirm) |
| `<x-grid.action-dropdown>` | Dropdown "⋮" kumpulan aksi per-baris (pilot ala Filament) |
| `<x-grid.table-shell>` | Wrapper scroll+sticky-header tabel |
| `<x-grid.empty-row>` | Baris "Belum ada data" |
| `<x-grid.group-header-row>` | Baris pemisah saat fitur Group By aktif |
| `<x-grid.pagination-footer>` | Footer pagination 3-bagian ala ScriptCase |
| `<x-grid.toolbar>` | Wrapper baris toolbar (search + tools + newRecord) |
| `<x-grid.column-modal>` | Modal pilih kolom tampil + Group By (`@include`) |
| `<x-grid.sort-modal>` | Modal pilih kolom+arah sort (`@include`) |
| `<x-grid.filters-panel>` | Tombol "🔽 Filters" (pilot, HANYA co/list) |
| `<x-grid.filters-row>` | Panel filter penuh-lebar (pilot, HANYA co/list, `@include`) |
| `<x-grid.toolbar-buttons>` | Grup tombol ☰ Kolom/↕ Sort/⬇ Export (`@include`) |
| `<x-grid.export-dropdown>` | Dropdown export CSV/Excel/PDF ikut kolom tampil (`@include`) |
| `<x-grid.spreadsheet-table>` | Tabel spreadsheet-editable (klik sel, drag-fill AutoFill) |

### Print (`components/print/`)
| Tag | Fungsi 1-baris |
|---|---|
| `<x-print.document>` | Kerangka luar dokumen Nota (bar cetak, tema warna) |
| `<x-print.sheet>` | 1 halaman A4 fisik |
| `<x-print.header>` | Kop dokumen (logo+nama perusahaan, judul+no dokumen) |
| `<x-print.parties>` | Wrapper grid kotak "pihak" |
| `<x-print.party>` | 1 kotak pihak (Kepada Yth./Dari/dst) |
| `<x-print.section>` | Header section bernomor |
| `<x-print.signatures>` | Blok "PENGESAHAN & PERSETUJUAN" |
| `<x-print.signature>` | 1 sel tanda tangan |
| `<x-print.footer>` | Foot fisik per halaman |

### Gantt (`components/gantt/`)
| Tag | Fungsi 1-baris |
|---|---|
| `<x-gantt.board>` | Papan Gantt penuh (ruler jam + baris + popover + modal edit) |
| `<x-gantt.row>` | 1 baris resource di papan Gantt |
| `<x-gantt.task-block>` | 1 blok task (drag/resize/klik/dblklik) |

*(Kanban belum punya komponen reusable — lihat `uistandard_component_gantt_kanban.md`.)*

### Lainnya
| Tag | Kategori | Fungsi 1-baris |
|---|---|---|
| `<x-alert.toast>` | alert | Kontainer toast global (1x per layout) |
| `<x-alert.banner>` | alert | Banner inline sukses/error dari session flash |
| `<x-auth.hero-brand>` | auth | Brand besar panel hero login/register |
| `<x-auth.top-brand>` | auth | Brand kecil kartu halaman auth sekunder |
| `<x-inquiry.readonly-summary>` | inquiry | Ringkasan Inquiry Product cross-nav read-only |
| `<x-org-chart.node>` | org-chart | Simpul rekursif Struktur Organisasi |
| `<x-prodev.wizard-bar>` | prodev | Wizard step-bar lintas modul (Inquiry/ProDev/Costing/Master Card) |

**Total: 45 komponen** (form 8, grid 18, print 9, gantt 3, alert 2, auth 2,
inquiry 1, org-chart 1, prodev 1).

---

## Cross-reference singkat — 7+ dokumen standar existing

| Dokumen | Soal apa |
|---|---|
| `docs/uistandardindex.md` | Peta/indeks semua dokumen standar UI/UX — baca ini dulu kalau bingung dokumen mana yang relevan |
| `docs/griduistandard.md` | Aturan visual & interaksi grid Master/Worklist — kotak statistik pastel, kolom identitas, kolom Aksi, toolbar minimal |
| `docs/FORM_UI_STANDARD.md` | Layar form/entry header navy + tombol Back |
| `docs/printnotastandard.md` | Dokumen cetak transaksi ke pihak luar (Nota) — kop+parties+tanda tangan, beda dari laporan tabel |
| `docs/ganttboardstandard.md` | Jadwal resource×waktu dengan drag geser & resize (Scheduling Board) |
| `docs/kanbanboardstandard.md` | Kolom kategori/status diskrit dengan kartu drag-drop antar kolom |
| `docs/spreadsheetgridstandard.md` | Grid spreadsheet-editable (klik sel edit, drag-fill AutoFill) — opt-in master kecil/scalar saja |
| `docs/admindashboardstandard.md` | Navigasi/chrome panel admin Filament (`/admin`) |
| `docs/sidebarmenustandard.md` | Struktur menu sidebar per modul |
| `docs/configurationstandard.md` | Layar Configuration — konstanta sistem, identitas aplikasi (dipakai komponen `auth/*`) |
| `docs/reportstandard.md` | Kerangka report 4 level (belum diimplementasi) |
