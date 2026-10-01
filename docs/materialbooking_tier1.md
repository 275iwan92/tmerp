# Material Booking (dari Customer Order) — Desain Tier 1

> **Status: DESAIN, keputusan B1–B9 dijawab user 2026-10-01 (lihat §6); B9 = bangun MRP/MPS baru, desainnya menunggu (§7).** Belum ada kode.
> Preview interaktif: artifact "Tata Letak Gudang TMERP" tab *Material Booking*
> (salinan repo: `docs/mockups/inventory_booking_preview_v2.html`).
> Nama file mengikuti menu baru yang diusulkan **"Material Booking"**
> (`doc_slug = materialbooking`). Terkait: `warehouselayout_tier1.md` (stok per bin, Available).
> Implementasi nanti mengikuti aturan multi-bahasa 2026-10-01: nilai DB/status English,
> label layar English + kamus `sys_dictionary`.

## 1. Keadaan sekarang (cek ddt* 2026-10-01)

| Hal | Fakta |
|---|---|
| `ddtinv.rm_booking` / `rm_booking_d` | **ada, 0 baris, tidak dipakai kode**. Kolom detail sudah kaya: `co_id`, `co_detail_id`, `material_id`, `location_id`, `qty_need`, `qty_book`, `qty_purchase`, `qty_replacement`, `qty_issued`, `material_replace_id`, `replace_ratio`, `pr_detail_id`, `wo_id` (header) |
| `prd_wo_material` | ada, 0 baris (`qty_required`, `material_needed_by`, substitusi) |
| `pr_line` | sudah punya `i_co_line_id`, `i_need_date` |
| BOM produk | `prod_part_material` 6 produk master card baru: **material_id NULL**, hanya gramatur + ukuran |
| Layout | `prod_layout_group` (CONFIRMED) punya `up_count`, `waste_pct`, `plano_w_mm/h_mm` |
| Lead time bahan | `mat_raw.lead_time_days` **kosong semua** |
| MRP/MPS lama | diarsipkan (CLAUDE.md §6B); booking ini BUKAN menghidupkannya — hanya berbasis CO, tanpa forecast |

## 2. Alur

1. **Pemicu**: CO Confirmed; ulang otomatis saat CO/CPDEL berubah, CO Cancel, PV diterima,
   adjustment di-post, dan tiap malam. Hanya baris yang belum di-Confirm PPIC yang dihitung ulang.
2. **Langkah 0 — FG**: bila repeat order, tampilkan stok FG produk yang sama (booking FG = Tahap 2).
3. **Langkah 1 — Kebutuhan** per part: lembar plano = ⌈qty ÷ `up_count`⌉ × (1 + `waste_pct`).
   Material dari `prod_part_material.material_id`; bila kosong → baris `NEED_ITEM`, engine
   mengusulkan `mat_raw` yang cocok (kategori + gramatur + ukuran ≥ plano, dengan rasio potong).
   Tahap 2: tinta/varnish/lem dari standar pemakaian (per 1000 lembar / per m²) yang harus
   punya layar input (CLAUDE.md §7.12).
4. **Langkah 2 — Tanggal butuh** = tanggal kirim `co_order_delivery` − lead produksi
   (`sys_constant` BOOKING_LEAD_DAYS default 7 hari kerja, override per produk).
5. **Langkah 3 — Netting** per bahan, urut tanggal butuh, hot job didahulukan:
   Available (FIFO lot; zona STORAGE/STAGING; pemilik kosong atau customer CO sama; dikurangi
   hard booking lain) → PO terbuka tiba ≤ tanggal butuh (`PEGGED_PO`) → PO terlambat (`LATE_PO`)
   → PR terbuka (`PEGGED_PR`) → sisa `SHORT`.
6. **Langkah 4 — Tindakan PPIC**: Confirm (alokasi stok jadi *hard*, mengurangi Available),
   Create PR (dikelompokkan per supplier usulan, dibulatkan ke kemasan beli, `i_co_line_id` +
   `i_need_date` terisi, tanggal pesan = tanggal butuh − lead time), Substitute (item pengganti +
   rasio, persetujuan), Reschedule.
7. **Pemakaian**: WO dari CPDEL mewarisi booking (`wo_id`) → Pick List (Warehouse Tahap 2) →
   Stock Out mengisi `qty_issued`; sisa dilepas saat WO selesai; CO Cancel melepas semua.
8. **Rebutan stok**: tanggal butuh paling awal menang; hot job boleh ambil alih (rebook) dengan
   persetujuan Ops Manager, tercatat.

## 3. Skema (pakai ulang, tm2inv)

- `rm_booking` (ada): 1 header per CO — tambah `co_id`, `run_at`, `doc_status`
  (Draft/Confirmed/Closed/Cancelled). Nomor `BK2600001`.
- `rm_booking_d` (ada): tambah `co_delivery_id`, `part_id`, `need_date`, `line_status`
  (`BOOKED`/`PEGGED_PO`/`LATE_PO`/`PEGGED_PR`/`SHORT`/`NEED_ITEM`/`ISSUED`/`RELEASED`),
  `source` (`LAYOUT`/`BOM`/`MANUAL`), `calc_up`, `calc_waste_pct`, `spec_category_id`,
  `spec_gram`, `spec_w_mm`, `spec_h_mm`, `priority`.
- `rm_booking_alloc` **baru**: `id_booking_alloc`, `booking_detail_id`, `source_type`
  (`STOCK`/`PO`/`PR`), `source_id` (layer / po line / pr line), `location_id`, `lot_no`,
  `pallet_id`, `qty`, `is_hard`, audit. Satu kebutuhan bisa ditutup beberapa sumber.
- Relasi ke `co_*` (tm2sdi), `po_*`/`pr_*` (tm2pur), `prod_*` (tm2mst) = logis.

## 4. Layar (lihat preview)

| Layar | Isi |
|---|---|
| Booking Workbench | Daftar CO + bar coverage (stok / PO / PO telat / short / pilih item), kotak status = filter, Run All |
| Booking per CO | Baris per item CO → part → bahan, rumus perhitungan terlihat, sumber penutup, Pick Item untuk baris spec; tombol Run, Undo, Save, Create PR, Confirm |
| Shortage Planner | Per bahan (horizon 30 hari): butuh, available, incoming, kurang, butuh pertama, pesan paling lambat, usulan PR; centang → Create PR |
| Material Timeline | Proyeksi saldo per tanggal satu bahan, dengan/tanpa usulan PR |
| Stock On Hand | Kolom Booked / Available / Incoming dari booking ini |

## 5. Keputusan (rekomendasi)

| # | Keputusan | Rekomendasi |
|---|---|---|
| B1 | Pemicu | Otomatis saat CO Confirmed + ulang per kejadian & tiap malam |
| B2 | Soft vs hard | Usulan (soft); terkunci (hard) setelah PPIC Confirm |
| B3 | Bahan dihitung | Tahap 1 kertas/board dari layout; Tahap 2 tinta/varnish/lem |
| B4 | Tanggal butuh | Kirim CPDEL − lead produksi (default 7 hari kerja, override per produk) |
| B5 | Rebutan stok | Tanggal butuh terawal; hot job rebook dengan persetujuan Ops Manager |
| B6 | BOM spec saja | PPIC pilih item dari usulan; disimpan jadi default Master Product setelah konfirmasi |
| B7 | Stok FG repeat order | Tampilkan dulu; booking FG Tahap 2 |
| B8 | PR | Per supplier usulan, dibulatkan ke kemasan beli; lead time per kategori wajib diinput |
| B9 | Hubungan MRP lama | Terpisah, berbasis CO saja |

## 6. Keputusan user 2026-10-01 (menggantikan rekomendasi §5 bila berbeda)

| # | Jawaban user | Tafsir kerja (menunggu konfirmasi bila ditandai ?) |
|---|---|---|
| B1 | "sejak CO masuk" | Booking dihitung sejak CO dibuat (termasuk DRAFT), bukan menunggu Confirmed. ? Apakah CO DRAFT sudah mengurangi Available (soft) atau hanya tampil sebagai kebutuhan |
| B2 | "usulan dan harus kunci oleh user" | Engine = usulan (soft); user berwenang (PPIC) klik Confirm = kunci (hard). Sesuai rekomendasi |
| B3 | "semua bahan" | Tahap 1 mencakup semua bahan (kertas/board, tinta, varnish/coating, lem, film, dst), bukan hanya kertas. Temuan: `prod_part_material` hanya berisi SUBSTRATE (18 baris, semua material_id NULL); `prod_part_ink` 40 baris punya `coverage_pct` tapi `ink_name` teks tanpa material_id; varnish/lem/film belum ada BOM — butuh standar pemakaian + layar input (CLAUDE.md §7.12) |
| B4 | "sesuai tanggal duedate, atau H+3 maksimal dari tanggal CO dibuat" | Tanggal butuh = yang lebih awal dari (due date) dan (tanggal CO dibuat + 3). ? hari kerja atau kalender; ? due date item CO atau tanggal kirim CPDEL |
| B5 | Kekurangan bahan = inti booking; notif ke Direksi, Ops Manager, PPIC, Warehouse, Finance | Status SHORT memicu notifikasi ke 5 pihak. ? kanal (notifikasi di aplikasi / email), ? aturan prioritas bila 2 CO berebut |
| B6 | "belum dirancang; saat CO di-approve semua ENGINE hitung bahan mulai kerja, terutama saat WO di-create" | Engine hitung bahan jalan penuh saat CO approve/Confirmed dan dihitung ulang presisi saat WO dibuat (POST to Production). Master Product TIDAK diubah oleh booking. ? hubungan dengan B1 (sejak CO masuk) |
| Dummy | "data dummy salah di remove dan ganti baru" (aturan main tetap) | Stok dummy kacau (`rm_stockcard` saldo tak konsisten, minus di UNKNOWN-LEGACY) dihapus & diganti baru saat membangun; tambah CO dummy CONFIRMED lengkap layout |
| B1/B6 | "benar" (putaran 2) | CO Draft: kebutuhan & kekurangan tampil, tanpa memesan stok → CO Confirmed: usulan booking terbentuk → WO dibuat (POST to Production): dihitung ulang presisi |
| B4 | "3 hari kerja normal" | Tanggal butuh = yang lebih awal dari (due date) dan (tanggal CO dibuat + 3 hari kerja, ikut Holiday Calendar / Office Hours) |
| B5 | Notif = popup saat login / di opening screen dengan warna khusus mencolok; email TIDAK; WhatsApp nanti bila perlu | Popup + kartu highlight di home v2 untuk Direksi, Ops Manager, PPIC, Warehouse, Finance. ? prioritas rebutan stok belum dijawab |
| B7 | "langsung ikut booking" | Stok FG produk yang sama dibooking dulu (Langkah 0) sebelum hitung bahan; bahan hanya untuk sisa qty |
| B8 | setuju | PR per supplier, dibulatkan kemasan beli; lead time per kategori + override per item, layar input baru |
| B9 | "buat MRP dan MPS baru yang lebih baik; berbasis CO saja kurang baik" | **Keputusan membalik rekomendasi**: booking menjadi bagian dari MRP/MPS BARU (engine lama tetap arsip, tidak dihidupkan). Desain MRP/MPS baru menyusul (§7) |

## 7. Status build (2026-10-01) — SUDAH DIBANGUN di ddt* (mockup.test), menunggu review user

Aturan user: master tidak diubah sistem; hanya transaksi. Nilai default boleh diperbarui analisa sistem, tetap bisa di-override user.

**Keputusan agen (jalan mandiri atas izin user — silakan dikoreksi):**
- Rebutan stok: CO CONFIRMED -> DRAFT/SUBMITTED -> FORECAST, lalu tanggal butuh terawal. Pindah stok antar CO oleh Ops Manager BELUM dibangun.
- MPS = forecast per customer x product x bulan; CO bulan yang sama memakan forecast; horizon `MRP_HORIZON_DAYS` 90 hari. Cek kapasitas mesin = tahap 2.
- Standar pemakaian `mat_usage_standard` (nilai awal = angka umum offset, BUKAN data pabrik). Tinta: per warna dari `prod_part_ink` (coverage) atau `cfg_color` + `BOOKING_INK_COVERAGE_PCT`; item tinta dicocokkan dari nama (mis. "BRITE INK CYAN"). Varnish/coating/film/lem: berlaku bila kata pemicu ada di nama proses part / finishing.
- Dasar lembar kertas: layout ProDev CONFIRMED -> costing inquiry CO (cut per plano x isi) -> ukuran BOM vs bentangan part; tak ada = `NEED_LAYOUT`.
- Item belum ada di BOM = `NEED_ITEM` -> **Pick Item** di booking (disimpan `rm_booking_d.note_detail = PICK`, bertahan saat hitung ulang; Master Product tidak diubah).
- Hard booking = header `stat_confirm = 1` (view `v_rm_available` sudah mengurangi `qty_book - qty_issued`), stok dikunci per lokasi. CO berubah sesudah Confirm -> lencana "CO changed" -> Back to Draft.
- Create PR: satu PR Draft per supplier usulan (`i_source = MRP`), qty dibulatkan ke satuan beli, `i_need_date` + `i_co_line_id` terisi.
- Popup: `x-dl::modal tone="danger"` (objek DevLib baru: prop `tone`) di layar pembuka, sekali per login / saat jumlah berubah + kartu merah tetap; untuk user yang punya akses PROD / INV / FINACC / MGMT / PROC. WhatsApp belum.
- Pemicu: `PlanningServiceProvider` (CO jadi CONFIRMED = usulan dibuat; batal / kembali Draft = dilepas; WO lahir = hitung ulang + catat WO pertama), `planning:run` tiap malam 01:30. Pick List per WO = Warehouse tahap 2.

**User journey:**
1. Production > Planning > **Material Booking**: daftar semua CO aktif + forecast, kolom Coverage (Short / Need item / Late / Waiting PO/PR / Covered / Booked), kotak statistik = filter. Tombol Recalculate All.
2. Klik 2x CO: baris bahan (produk, part, bahan, tanggal butuh, qty, ditutup oleh, kurang, rumus). CO belum Confirmed = pratinjau saja. CO Confirmed: **Recalculate** -> **Pick Item** (baris tanpa item) -> **Confirm** (kunci stok) -> **Back to Draft** bila perlu.
3. Production > Planning > **Material Planning (MRP)**: satu baris per bahan (butuh, dari stok, dari PO/PR, kurang, butuh pertama, pesan paling lambat, supplier). Centang bahan kurang -> **Create PR** -> terbuka di Purchase Requisition (Draft). Klik 2x bahan = proyeksi saldo per tanggal.
4. Production > Planning > **Demand Forecast (MPS)**: isi langsung di sel (customer, product, bulan, qty) -> Save.
5. Master Data > **Material Usage Standard** dan **Purchase Lead Time**: isi langsung di sel -> Save.
6. Layar pembuka: popup merah "Material Shortage" + kartu merah dengan tombol Open Material Planning.

**File:** migration `2026_10_01_000070` (skema) + `000071` (menu aktif, `material_id` nullable, perbaikan dummy stok); `app/Services/Planning/MaterialPlanningEngine.php`; `app/Support/V2/Data/MaterialPlanning.php`; `app/Support/V2/Grid/Planning/*`; `resources/views/v2/screens/material-booking|material-planning.blade.php`; `resources/views/v2/partials/material-alert.blade.php`; `routes/v2_planning.php`; `app/Console/Commands/PlanningRun.php` (`--demo` = contoh forecast + pick, ddt saja); `app/Providers/PlanningServiceProvider.php`.

**Dummy (aturan B.12):** Stock Out tanpa stok masuk (AC 210 GR, -10) dihapus; saldo berjalan `rm_stockcard` dihitung ulang (FREON -1000 -> 0, ANIMAL GLUE 50 -> 1030). Contoh forecast + pick dibuat `planning:run --demo` (dinamis; CO dummy dibuat ulang sesi lain saat pekerjaan ini berjalan).

**Belum / terbuka:** tm2* belum dimigrasi (menunggu OK); pindah stok antar CO + persetujuan Ops Manager; Substitute & Reschedule; Pick List per WO; kapasitas mesin di MPS; notifikasi WhatsApp; terjemahan Indonesia label baru (Setup > Translation Dictionary); baseline `ddt` belum disimpan ulang; preview artifact lama (tab di artifact gudang) belum diperbarui — layar asli di mockup.test menggantikannya.
