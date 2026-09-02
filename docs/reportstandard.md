# STANDAR — REPORT 4 LEVEL (KERANGKA, BELUM DIIMPLEMENTASI)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-09-02 (keputusan user). **INI KERANGKA SAJA** — mendefinisikan
> konsep 4 level report + konvensi form/grid/chart yang akan dipakai, TAPI
> **belum ada 1 pun layar report sungguhan dibangun**. Jangan anggap fitur
> ini "sudah ada" cuma karena dokumen ini ada — cek dulu apakah ada Resource/
> halaman report NYATA sebelum bilang ke user "sudah dikerjakan".

---

## 1. KENAPA DITUNDA DARI FASE 1

Sistem report 4-level + graphics report adalah modul baru setingkat Costing
Engine atau Production Scheduling — bukan penambahan kecil. Sebelum
dibangun, butuh scoping terpisah yang BELUM dilakukan:
- Laporan APA saja per modul (Sales? Inventory? Finance? Semua sekaligus?)
- Sumber data tiap laporan (query existing vs agregasi baru)
- Library chart (belum dipilih — tidak ada dependency chart terpasang saat ini)
- Level mana lihat data sedetail apa (drill-down policy per level)

Dikerjakan setelah Fase 1 (Configuration/branding/tema dashboard) selesai,
kalau diminta eksplisit oleh user dengan scoping di atas dijawab dulu.

---

## 1a. SUDAH ADA: katalog laporan Top Management (`topmanagementreport_tier1.md`)

**Ditemukan 2026-09-02 saat menyusun `docs/BACKLOG.md`** — dokumen ini
(`reportstandard.md`) awalnya ditulis tanpa tahu `docs/topmanagementreport_tier1.md`
sudah ada (ditulis 2026-08-27). Dokumen itu **jauh lebih detail** untuk
konten level "Top Management" (level 4 di §2 bawah): katalog laporan
lengkap per domain (Sales & Margin, Procurement & Inventory, Finance, HR)
dengan dimensi/ukuran/sumber-data/grafik per laporan, prinsip desain
Summary(pivot)→Detail(drill-down). **Baca itu dulu** kalau mulai dari level
Top Management — jangan bikin katalog laporan baru dari nol. Dokumen ini
(`reportstandard.md`) fokus ke bagian yang BELUM ada di sana: level 1-3
(Data Entry/Supervisor/Manager) dan kerangka konsep 4-level-nya sendiri.

---

## 2. KONSEP 4 LEVEL (kerangka, belum ada gate teknis)

| Level | Peran | Cakupan data (rencana) |
|---|---|---|
| 1 | Data Entry Officer | Data yang dia input sendiri / unit kerjanya |
| 2 | Supervisor | Agregasi timnya |
| 3 | Manager | Agregasi departemen/modul |
| 4 | Top Management | Ringkasan lintas modul, seluruh perusahaan |

**Belum diputuskan**: apakah 4 level ini map langsung ke `sec_group.mgmt_level`
yang sudah ada (dipakai `SecUser::maxMgmtLevel()`, role gate PPIC di
Production Scheduling) atau butuh dimensi baru. Cek `mgmt_level` existing
dulu sebelum bikin sistem level paralel — kemungkinan besar REUSE, bukan
tabel baru (prinsip yang sama dgn kenapa Configuration Fase 1 reuse
`geo_country`/`sys_languages` yang sudah ada alih-alih bikin duplikat).

---

## 3. KONVENSI FORM/GRID/CHART (rencana, belum diverifikasi lewat implementasi nyata)

- **Grid data report** → ikut `griduistandard.md` (grid non-Master, kolom
  Aksi = Buka, tanpa toggle aktif).
- **Filter periode/level** → ikut `FORM_UI_STANDARD.md` untuk header,
  `x-form.date-picker` (docs/FORM_UI_STANDARD.md §3) untuk filter tanggal.
- **Graphics report** → BELUM ada library chart terpasang. Kandidat harus
  dievaluasi dulu (ringan, tanpa lisensi berbayar, konsisten pola
  "buat sendiri ringan" spt `spreadsheetgridstandard.md`/`x-form.date-picker`
  — kemungkinan Chart.js via CDN atau native SVG, BUKAN keputusan final).

---

## 4. YANG SENGAJA BELUM DIKERJAKAN

Semuanya. Dokumen ini murni kerangka konsep + pointer supaya scoping
berikutnya tidak mulai dari nol. Jangan buat Resource/Livewire component
report apa pun berdasar dokumen ini tanpa konfirmasi scope eksplisit dari
user terlebih dahulu (CLAUDE.md §7.1).
