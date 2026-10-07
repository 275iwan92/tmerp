# Dokumen Acuan (dipindah dari CLAUDE.md §6, 2026-10-07; isi asli utuh)


> **Konvensi nama file (DEC-NAMING 2026-08-26):** nama file `docs/*.md`/`*.yaml` mengikuti
> `sys_app_menu.label` (menu live di `tm2sys`) — lowercase, tanpa spasi, digabung jadi satu
> kata, + suffix jenis dokumen (`_tier1..4`, `_rules`, `_state`, dst). `sys_app_menu.doc_slug`
> = sumber kebenaran basis nama per submodule; dokumen yang bukan milik 1 menu item (aturan
> global/standar/engine/referensi) terdaftar di tabel `sys_doc_reference` (bukan file .md,
> jangan ditulis manual — cek `SELECT * FROM tm2sys.sys_doc_reference`). Migration:
> `2026_08_26_000001_add_doc_slug_and_doc_reference.php`. **Saat bikin dokumen baru:** cek dulu
> `doc_slug` submodule terkait (atau tambahkan row baru di `sys_app_menu`/`sys_doc_reference`
> kalau belum ada), jangan bikin prefix sendiri.

> Konvensi nama `docs/screens/*.md` (DEC-NAMING-SCREEN) dipindah ke arsip yang sama; dokumen per-layar sudah dihapus.

> **`uistandardindex.md` — peta semua standar UI/UX** (grid/form/nota/gantt/
> kanban/admin dashboard Filament) + kapan masing-masing wajib dibaca. **Cek
> file ini dulu** kalau bingung standar mana yang relevan untuk 1 layar baru,
> daripada baca satu-satu daftar di bawah.

- `flow_product_master.md` — **PEDOMAN ALUR wajib dibaca sebelum menyentuh
  Inquiry/ProDev/ProCost/Quotation/CO/Master Product/Revisi/Rekalkulasi/ACC Digital**
  (user 2026-09-29): Quotation WIN → Master Product (multi-part, rakitan bertingkat
  Single/Double Join), Revisi = product turunan, Rekalkulasi = versi harga bertanggal,
  ACC Digital wajib, due date CO +14 hari kerja adjustable. Tampil di Menu v2 →
  Sales → Alur → Pedoman (`/v2/pedoman/product-master`) — update dokumen ini tiap
  ada keputusan alur baru.
- `matcategory_delta.md` §5 — **wajib dibaca sebelum menyentuh Material Category, Raw Material, kode item, atau dropdown
  kategori di PR / PO** (user 2026-10-03, sudah disampaikan 7 kali — JANGAN ditanyakan lagi; `docs/globaluserrules.md` B.16):
  **Material Category = PEMBENTUK KODE ITEM.** Acuan `docs/ddl/doc_rmprefix1.sql` (`doc_rmprefix1..5`, sekarang
  `mat_category` level 1–5). Kode item = prefix1+prefix2+prefix3+prefix4+prefix5 (Paper › Art carton › 210gr ›
  65 X 100 CM › GOLDEN = `PCC02002`). Layar = **dropdown yang saling terkait** Category 1 → … → Category 5 (seperti yang
  lama), bukan daftar rata Level + Parent. Arti tiap tingkat berbeda per kategori — label layar Category 1 … 5, bukan
  "gramatur / ukuran / merek". Kategori + Type 2 tingkat di `tm2old` BUKAN pembentuk kode dan bukan penggantinya.
- `warehouselayout_tier1.md` (di `docs/`) — **wajib dibaca sebelum menyentuh gudang, bin, peta gudang, Stock Transfer,
  label / QR, atau hitungan dead stock** (user 2026-10-02, `docs/globaluserrules.md` A.22): gudang = GEDUNG nyata
  (`wh_warehouse`: ukuran meter, bentuk, 1-3 lantai) → area (`wh_zone`) → bin (`wh_loc`); pindah stok hanya lewat
  `App\Services\Inventory\StockTransferService` (`inv_transfer`, kartu stok `doc_type` TF); isi bin / kapasitas / dead
  stock hanya dari `App\Services\Inventory\WarehouseStock` (transfer TIDAK dihitung sebagai pergerakan: kolom view
  `last_item_movement`). Bagian 13 = yang dibangun; review user: `md_for_systems/warehouse_review.md`.
  Bagian 14 (putaran 2): kapasitas = luas x tinggi (m3) + kg; palet berlabel = `inv_pallet` (TIDAK mengubah stok; selisih
  saldo dan label = pekerjaan label, `App\Services\Inventory\PalletService`); tiga label berbeda (lokasi / item / palet)
  hanya lewat `App\Support\V2\Data\Labels`; approval transfer = modul flow `TF`, bawaan mati. Terpasang di ddt DAN tm2
  (2026-10-03, bagian 14.8): menu lewat migration `2026_10_03_000010`, gedung nyata tm2 `WarehouseBuildingSeeder`, data
  contoh hanya ddt (`UatWarehouseSeeder`); approval TF di tm2 menunggu Setting Approval (`StockTransferApprovalSeeder`).
- `componentstandard.md` — **wajib dibaca sebelum menulis markup layar apa
  pun** (keputusan user 2026-09-23): komponen UI berlapis & object oriented —
  token `@theme` → primitive `app/View/Components/Ui/*` (`x-ui.button`,
  `badge`, `card`, `tabs`, `table`, `modal`, ...; turunan `UiComponent`,
  palet `Tone`) → komposit `x-form/x-grid` → layar. Ubah induk = semua ikut.
  Dilarang `<button class>`/hex/`fixed inset-0`/`<table class>` mentah di
  halaman baru; `UiRatchetTest` menjaga angkanya tidak naik. Migrasi layar lama
  (termasuk zona beku §6B) = kulit saja, esensi wajib dibuktikan sama (§6 dok).
- `globalarchitecture_tier0.md` — arsitektur global, keputusan LOCKED.
- `inquiryproduct_tier1.md` — alur & proses inquiry (aktor, state, aturan bisnis).
- `inquiryproduct_tier2.md` — business logic, state machine, pseudocode HPP.
- `TIER_3A_*.sql` — **DIHAPUS 2026-08-26** (sudah dijalankan, isinya tertanam live di MySQL). Skema aktual = dump per-database (`docs/tm2*.sql`), digantikan user secara berkala — bukan lagi file DDL statis ini.
- `prefixlist.md` — konvensi prefix lengkap.
- Mockup Tier 4 (HTML/CSS) — acuan UI: `inquiry_new/recalc/revise.html`,
  `inquiry_costing_*.html`, `inquiry_proof.html`, `inquiry_quotation.html`,
  `product_costing.html`, `*_nota_*.html`.
- `griduistandard.md` — **wajib dibaca sebelum bikin/ubah grid apa pun**
  (Master maupun worklist/transaksi): tanpa header halaman, kotak statistik
  pastel, kolom identitas avatar+nama+kode, klik 2x buka detail, toolbar
  minimal. Kolom Aksi: grid Master = Edit+Toggle-aktif; grid non-Master =
  Buka + Confirm kondisional (tidak pernah toggle aktif/nonaktif).
- `sidebarmenustandard.md` — **wajib dibaca sebelum menata menu
  `sys_app_menu`/sidebar modul apa pun**: selalu ada grup input & grup
  "Laporan" (label ringkas, tanpa kata "Laporan" berulang di item-nya),
  nama item laporan ikut `#[Title]` layar aslinya, grup sidebar otomatis
  collapsible (tak perlu kerjaan tambahan).
- `ganttboardstandard.md` — **wajib dibaca sebelum bikin/ubah layar Gantt
  apa pun** (jadwal resource×waktu dengan drag/resize, mis. Scheduling Board):
  komponen reusable `resources/views/components/gantt/{board,row,task-block}
  .blade.php`, kontrak Row/Block VM, Alpine store `gantt` untuk highlight
  lintas-boundary. Gotcha wajib: jangan tulis nama tag komponen berkurung
  sudut (`<x-...>`) di dalam komentar mana pun — Blade tag compiler ikut
  mengompilasinya dan merusak file.
- `kanbanboardstandard.md` — **wajib dibaca sebelum bikin/ubah layar Kanban
  apa pun** (kolom kategori/status diskrit, kartu drag-drop antar kolom,
  mis. Pipeline Board, Monitoring Dashboard): pola `x-data="{draggedId}"` +
  `dragover.prevent`/`drop`, validasi bisnis WAJIB di server (DB = satu-
  satunya sumber kebenaran posisi kartu, drag di layar cuma usulan sampai
  re-render), tombol aksi non-drag selalu tersedia sbg fallback.
- `docs/qc_feedback.md` — **tempat user tulis hasil QC/revisi/bug setelah cek aplikasi sendiri di browser** (Claude Code tidak bisa login browser otomatis, lihat §7). Cek file ini kalau user minta "cek qc_feedback" / "kerjakan yang belum" — proses entry di bagian "Belum Dikerjakan", pindahkan ke "Selesai" (isi Perbaikan+tanggal) atau "Ditutup" (kalau ternyata bukan bug) setelah selesai, jangan hapus entry.
- `docs/attachments/` — folder tempat menaruh **form manual (kertas/scan) & file Excel yang SAAT INI benar-benar dipakai** di lapangan, dijadikan acuan sebelum membangun/mengubah layar terkait (jangan menebak field, cek dulu ke sini kalau ada dokumen relevan). **Daftar isi lengkap: `docs/attachments_index.md`** — cek/update file itu setiap ada attachment baru ditambah/dipakai, jangan baca ulang seluruh folder dari nol tiap sesi.
- `printnotastandard.md` — **wajib dibaca sebelum bikin/ubah layar cetak
  dokumen transaksi (Nota)** — kop+parties+signature+footer, beda dari
  laporan tabel (`FORM_UI_STANDARD.md`): komponen reusable
  `resources/views/components/print/{document,sheet,header,parties,party,
  section,signatures,signature,footer}.blade.php`, tema warna per dokumen
  via prop `theme`. Gotcha sama seperti `ganttboardstandard.md` §8.
- `production_scheduling_wo_generation_delta.md` — **wajib dibaca sebelum
  menyentuh alur generate WO / layar Worklist WO-Detail-Monitoring** —
  delta atas `production_scheduling_tier1-4.md` (LOCKED, tidak direvisi):
  WO lahir dari `co_order_delivery` (Plan Delivery) bukan CO langsung, 1
  `iqp_part` = 1 `prd_wo` (konvergensi antar-part sengaja belum dimodelkan),
  target rate dikunci ke `prod_product`+`part_code` teks (bukan `prod_part`
  yang ternyata tak pernah diisi).
- ~~`admindashboardstandard.md`~~ — standar panel Filament `/admin`. **Tidak
  berlaku lagi**: user 2026-09-30 memutuskan Filament bukan arah ke depan
  (jangan bangun/pindahkan modul ke Filament; v2 DevLib yang menggantikan).
  Dokumennya diarsipkan 2026-10-02 ke `C:\Users\iwanp\tmerp_unused\`.
- `spreadsheetgridstandard.md` — **wajib dibaca sebelum bikin/ubah grid
  spreadsheet-editable apa pun** (klik sel langsung edit, drag-fill AutoFill
  copy nilai ke bawah, "+ Baris" langsung dari tabel, simpan batch lewat
  "Simpan Semua") — pola TERPISAH dari `griduistandard.md`, opt-in HANYA
  utk master kecil/kolom scalar (pilot: Master Currency, list Satuan Master
  UOM). Adopsi perilaku AutoFill DataTables.net versi ringan buatan sendiri
  (Alpine+Livewire, keputusan user 2026-08-30) — DataTables Editor asli
  (dibutuhkan utk inline-edit sungguhan) berbayar & belum ada license key,
  DataTables juga bentrok arsitektur dgn cara Livewire re-render DOM tabel.
- `themestandard.md` — **wajib dibaca sebelum menambah/ubah pilihan tema**
  atau token warna baru yang harus ikut ganti per mode: matrix 4 mode
  (Pastel/Cerah/Dark/Hitam) x 3 corak (Biru/Hijau/Merah) = 12 kombinasi,
  data 100% di `tm2sys.sys_theme` (kolom `mode`/`family`/`hue`) supaya
  gampang diedit langsung lewat DB — BUKAN hardcode di CSS/Blade. Picker
  grid ada di dropdown avatar (`main-menu.blade.php`), toggle cepat
  matahari/bulan di topbar. Cakupan baru shell lama (`layouts::shell`) —
  halaman transaksi via `<iframe>` & panel Filament `/admin` (sengaja
  dipaksa terang, `darkMode(false)`) belum ikut sistem ini.

---

