# INDEX — STANDAR UI/UX (tmerp)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-30. Pintu masuk TUNGGAL untuk semua dokumen standar
> tampilan/interaksi — dipecah per POLA (bukan digabung 1 file besar) karena
> tiap pola dibaca di konteks kerja yang beda-beda (bikin grid ≠ bikin nota
> cetak ≠ bikin Kanban), dan beberapa sudah lebih dari 500 baris sendiri-
> sendiri. Baca baris tabel di bawah untuk tahu file mana yang relevan
> dengan layar yang sedang dikerjakan — **jangan baca semuanya sekaligus
> tiap sesi**, cukup yang sesuai jenis layar.

---

## Kenapa dipecah, bukan 1 file gabungan

- **Trigger baca beda-beda.** Setiap standar punya baris "wajib dibaca
  sebelum bikin/ubah layar X" sendiri di CLAUDE.md §6 — kalau digabung,
  tiap sesi yang cuma mau bikin 1 grid akan ikut membaca (dan makan token
  konteks untuk) aturan Gantt/Kanban/Nota yang tidak relevan.
- **Ukurannya sudah besar per topik.** `griduistandard.md` sendiri 595
  baris — gabungan 7 topik akan jadi 1 file >2000 baris yang sulit
  di-`grep`/navigasi, dan git diff-nya jadi berisik (1 baris berubah di
  bagian Nota bikin file raksasa "berubah" di riwayat commit).
- **Pola penamaan sudah ada, jangan dipatahkan.** File-file ini sudah
  terdaftar satu-satu di `tm2sys.sys_doc_reference` (kategori `STANDARD`)
  sejak sebelum dokumen ini dibuat — mengubah jadi 1 file butuh migrasi DB
  + revisi semua cross-reference existing (CLAUDE.md, komentar kode). Tidak
  sepadan manfaatnya.

Dokumen INDEX ini yang baru — bukan gabungan isi, cuma peta + kapan pakai
yang mana, supaya sesi baru tidak perlu menebak dari nama file saja.

---

## Peta lengkap

| Dokumen | Untuk layar jenis apa | Baca SEBELUM |
|---|---|---|
| [`griduistandard.md`](griduistandard.md) | Tabel data — Master (CRUD) & Worklist/transaksi (grid + toolbar + kolom Aksi) | Bikin/ubah grid apa pun, Master maupun non-Master |
| [`FORM_UI_STANDARD.md`](FORM_UI_STANDARD.md) | Layar form/entry dengan header navy + tombol Back (CO Entry, PR/PO/PV, Master Warehouse, dst) | Bikin/ubah header layar form/entry |
| [`sidebarmenustandard.md`](sidebarmenustandard.md) | Struktur menu sidebar per modul (grup input + grup "Laporan", collapsible) | Menata `sys_app_menu`/sidebar modul apa pun |
| [`ganttboardstandard.md`](ganttboardstandard.md) | Jadwal resource×waktu dengan drag geser & resize (Scheduling Board, calon Schedule Maintenance) | Bikin/ubah layar Gantt apa pun |
| [`kanbanboardstandard.md`](kanbanboardstandard.md) | Kolom kategori/status diskrit dengan kartu drag-drop antar kolom (Pipeline Board, Monitoring Dashboard) | Bikin/ubah layar Kanban apa pun |
| [`printnotastandard.md`](printnotastandard.md) | Dokumen cetak transaksi ke pihak luar — kop+parties+tanda tangan (Nota, Quotation, calon DO/Surat Jalan) | Bikin/ubah layar cetak dokumen transaksi (BUKAN laporan tabel — itu tetap `FORM_UI_STANDARD.md`) |
| [`admindashboardstandard.md`](admindashboardstandard.md) | Navigasi/chrome panel admin Filament (`/admin`) — pemetaan Module/Group/Item, auth bridge, cara bikin Resource baru | Migrasikan/bikin modul baru di `/admin` (Filament) |
| [`spreadsheetgridstandard.md`](spreadsheetgridstandard.md) | Grid spreadsheet-editable (klik sel edit, drag-fill AutoFill, "+ Baris") — HANYA master kecil/scalar (Currency, UOM) | Bikin/ubah grid spreadsheet-editable apa pun |
| [`themestandard.md`](themestandard.md) | Tema aplikasi — matrix 4 mode (Pastel/Cerah/Dark/Hitam) x 3 corak (Biru/Hijau/Merah), data di `tm2sys.sys_theme` | Menambah/mengubah pilihan tema, atau token warna baru yang harus ikut ganti per mode |
| [`configurationstandard.md`](configurationstandard.md) | Layar Configuration (shell lama, di atas grup Security) — konstanta sistem per kategori, identitas aplikasi (nama/logo/font), bahasa & terjemahan | Menambah konstanta/kategori/bahasa baru, atau mengubah identitas aplikasi |
| [`financeaccounting_delta_process_engine.md`](financeaccounting_delta_process_engine.md) §1 | Dialek UI khusus modul Finance & Accounting ("LedgerOne": `resources/css/ledger.css` kelas `lg-*` + komponen `x-ledger.*`) — kartu metrik, panel alur proses, baris laporan, modal | Bikin/ubah layar di modul FINACC (jangan campur dialek `lg-*` ke modul lain tanpa keputusan baru) |
| [`reportstandard.md`](reportstandard.md) | **Kerangka saja, belum diimplementasi** — konsep report 4 level (Data Entry/Supervisor/Manager/Top Management) + graphics report | Sebelum mulai scoping/bikin layar report apa pun |

---

## Cara memilih cepat (kalau bingung mulai dari mana)

```
Layar baru itu...
├─ tabel data biasa (list + Master/CRUD)?           → griduistandard.md
├─ form input dengan header navy + Back?             → FORM_UI_STANDARD.md
├─ hasil cetak PDF/print ke pihak luar?               → printnotastandard.md
├─ jadwal geser waktu per resource (mesin/orang)?     → ganttboardstandard.md
├─ kolom status dengan kartu digeser antar kolom?     → kanbanboardstandard.md
├─ menata sidebar/grup menu 1 modul?                  → sidebarmenustandard.md
├─ tabel spreadsheet-editable (klik sel edit, drag-fill)? → spreadsheetgridstandard.md
└─ dibangun di panel /admin (Filament, bukan shell lama)? → admindashboardstandard.md
   (baca DUA-DUANYA kalau relevan: admindashboardstandard.md untuk chrome
   Filament-nya, + salah satu di atas untuk ISI layarnya — mis. Resource
   Filament yang table()-nya meniru grid lama tetap ambil konvensi warna/
   kolom dari griduistandard.md sejauh relevan)
```

---

## Yang TIDAK masuk index ini

- Standar non-UI (penamaan tabel/kolom, arsitektur DB, dsb.) — itu di
  CLAUDE.md §2-§3 langsung, bukan dokumen `docs/*standard.md` terpisah.
  `prefixlist.md` juga di luar index ini (soal penamaan tabel, bukan UI).
- Dokumen per-modul (`<slug>_tier1..4.md`) — itu spesifikasi FITUR
  spesifik, bukan pola UI lintas-modul. Index ini cuma untuk dokumen yang
  dipakai LEBIH DARI 1 modul.
- **[`BACKLOG.md`](BACKLOG.md)** — bukan standar pola UI, tapi tracking
  pekerjaan tertunda lintas-modul (Fase 2 Report + semua item "belum
  dikerjakan" dari tiap dokumen di atas + Tier docs per-modul). Cek di
  sana kalau mau tahu apa yang MASIH HARUS dikerjakan di layar/dokumen
  jenis apa pun — index ini cuma peta "standar sudah ada", bukan "sudah
  100% diterapkan di mana-mana".

---

## Menambah dokumen baru ke index ini

1. Pastikan polanya benar-benar dipakai **lebih dari 1 layar** dulu (lihat
   catatan kanban §1 di `kanbanboardstandard.md` — jangan ekstrak
   komponen/standar dari 1 contoh saja, tunggu pola kedua muncul).
2. Ikuti gaya penulisan yang sudah ada (§ bernomor, "KAPAN PAKAI INI" di
   awal, tabel kontrak data kalau ada komponen Blade, referensi ke file
   implementasi NYATA, bagian "yang sengaja tidak ditangani" di akhir).
3. Nama file: lowercase, tanpa spasi/underscore, digabung + akhiran
   `standard.md` (pola existing: `griduistandard`, `ganttboardstandard`,
   `printnotastandard`, `kanbanboardstandard`, `admindashboardstandard`).
4. Daftarkan ke `tm2sys.sys_doc_reference` (kategori `STANDARD`) lewat
   MIGRATION — CLAUDE.md §6: tabel ini **jangan ditulis manual**, sumber
   kebenarannya migration.
5. Tambah baris di tabel "Peta lengkap" atas + baris di CLAUDE.md §6.
