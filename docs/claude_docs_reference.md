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
- `ui_standard_v1.md` — **standar UI layar v1 dalam satu berkas** (grid, form, komponen OOP, cetak Nota, menu sidebar, Gantt, Kanban, spreadsheet grid, report 4 level); nomor § grid asli dipertahankan untuk komentar kode yang menyebut `griduistandard.md §n`. Layar v2 = objek DevLib (`_manifest.yaml`, rulebook C). Asli: `md:library search "grid"`.
- `globalarchitecture_tier0.md` — arsitektur global, keputusan LOCKED.
- `inquiryproduct_tier1.md` — alur & proses inquiry (aktor, state, aturan bisnis).
- `inquiryproduct_tier2.md` — business logic, state machine, pseudocode HPP.
- `TIER_3A_*.sql` — **DIHAPUS 2026-08-26** (sudah dijalankan, isinya tertanam live di MySQL). Skema aktual = dump per-database (`docs/tm2*.sql`), digantikan user secara berkala — bukan lagi file DDL statis ini.
- `prefixlist.md` — konvensi prefix lengkap.
- Mockup Tier 4 (HTML/CSS) — acuan UI: `inquiry_new/recalc/revise.html`,
  `inquiry_costing_*.html`, `inquiry_proof.html`, `inquiry_quotation.html`,
  `product_costing.html`, `*_nota_*.html`.
- `docs/qc_feedback.md` — **tempat user tulis hasil QC/revisi/bug setelah cek aplikasi sendiri di browser** (Claude Code tidak bisa login browser otomatis, lihat §7). Cek file ini kalau user minta "cek qc_feedback" / "kerjakan yang belum" — proses entry di bagian "Belum Dikerjakan", pindahkan ke "Selesai" (isi Perbaikan+tanggal) atau "Ditutup" (kalau ternyata bukan bug) setelah selesai, jangan hapus entry.
- `docs/attachments/` — folder tempat menaruh **form manual (kertas/scan) & file Excel yang SAAT INI benar-benar dipakai** di lapangan, dijadikan acuan sebelum membangun/mengubah layar terkait (jangan menebak field, cek dulu ke sini kalau ada dokumen relevan). **Daftar isi lengkap: `docs/attachments_index.md`** — cek/update file itu setiap ada attachment baru ditambah/dipakai, jangan baca ulang seluruh folder dari nol tiap sesi.
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
---

