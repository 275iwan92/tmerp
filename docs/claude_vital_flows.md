# Alur VITAL (dipindah dari CLAUDE.md §4, 2026-10-07; isi asli utuh)

- **[VITAL] ProDev & ProCost — 2 mode independen, JANGAN dicampur** (dikunci user
  2026-09-03, jangan tanya ulang ke user soal ini):
  - **Inquiry Product punya 3 doc-type** (`inquiryproduct_flow.md` §6, locked Jo):
    **BARU** (dari nol, Master Product belum ada), **REVISI** (reorder, spec
    BERUBAH), **REKALKULASI** (reorder, spec SAMA, cuma harga bahan naik/turun).
    `iqp_costing.calc_type` di kode SAAT INI hardcode `'NEW'` (kolom Costing
    ini spesifik belum ikut bercabang). **Koreksi 2026-09-04** (klaim lama di
    sini sempat bilang "3 mode cuma konsep, belum ada percabangan behavior
    nyata" — SALAH, sudah diverifikasi ke kode): REVISI & REKALKULASI adalah
    **layar Livewire terpisah** (`inquiry/revise.blade.php` route
    `inquiry.revise`, `inquiry/recalc.blade.php` route `inquiry.recalc`),
    BUKAN mode di `form.blade.php`. REVISI punya scope selector nyata
    (MATERIAL/DIMENSION/ARTWORK/COLOR/PROCESS, `inquiryproduct_delta_revise.md`)
    yang mengunci/membuka blok edit; REKALKULASI 100% read-only reuse kecuali
    Qty Order. Detail UI/UX & status implementasi lengkap:
    `docs/inquiry_product_costing/inquiryproduct_rules.md`.
  - **ProDev Mode #1 (integrasi)** — utk Inquiry BARU: ProDev **HARUS** ambil/
    berawal dari `iqp_part` (seed `prod_part` baru dari spec kasar Inquiry).
    **STATUS 2026-09-03: LINK INI BELUM ADA** — 0 jalur kode bikin `prod_part`
    baru, 0 link dari layar Inquiry mana pun ke ProDev. Ini gap nyata yang
    perlu diperbaiki, bukan sudah selesai.
  - **ProDev Mode #2 (manual/standalone)** — ProDev bekerja SENDIRI, **TIDAK
    melihat Inquiry Product sama sekali** — mencerminkan proses manual riil
    yang SEKARANG masih pakai Excel + Adobe Illustrator (didigitalkan apa
    adanya). Ini BUKAN gap, BUKAN sesuatu yang perlu "diperbaiki" jadi kayak
    Mode #1 — 2 pintu masuk yang SENGAJA terpisah & sah keduanya.
  - **ProCost Mode #1 (Ambil Data)** — tarik dari `prod_layout_group` yang
    CONFIRMED di ProDev. **SUDAH ADA & jalan** (`costing.blade.php::
    pickLayoutGroup()`), bukan sesuatu yang baru dibangun.
  - **ProCost Mode #2 (manual/standalone)** — officer input SEMUA parameter
    sendiri (panjang, lebar, running meter, dst), dibantu **tabel NILAI
    DEFAULT** (referensi nilai awal — perlu diklarifikasi apakah maksudnya
    `sys_constant` category=PRODUCTION/group=COSTING yang sudah ada, atau
    tabel lain yang belum dibangun — JANGAN asumsi, cek/tanya dulu kalau
    relevan). Ini toggle `data_source=MANUAL` yang sudah ada di Costing Hub.
  - **Syarat mutlak**: HPP hasil Mode #1 dan Mode #2 **HARUS SAMA** utk
    geometri yang setara — keduanya cuma 2 JALUR INPUT DATA ke `CostingEngine`
    yang SAMA, bukan 2 logika harga berbeda.
  - Detail lengkap & riwayat investigasi: memory
    `project_prodev_procost_2mode_architecture.md` +
    `feedback_prodev_must_pull_from_inquiry_new.md`.
- **[VITAL] 3 ENGINE inti — InqProd, ProDev, ProCost — masing-masing GOAL-nya**
  (dikunci user 2026-09-03, pelengkap poin di atas):
  - **InqProd Engine** (`inquiry/form.blade.php`) — GOAL: (1) picker visual
    tipe produk + live dieline preview + dimensi gampang di-adjust + preset
    "Ukuran Pasaran" — **SUDAH ADA & DIVERIFIKASI BAGUS, JANGAN DIUBAH**
    (verdict eksplisit user), improvisasi di atasnya SANGAT boleh. **Koreksi
    2026-09-04**: yang dikunci itu KEMAMPUAN RENDER dieline (TMDieline DSL,
    `resources/js/tmdieline.js` — sungguhan tidak disentuh), BUKAN lokasi UI
    pemilihan Tipe Produk. Tipe Produk sudah dipindah dari per-Part ke level
    Inquiry (1 tipe berlaku semua Part, dipilih di Header dekat "Kategori
    Produk" — sales-facing shortcut baru), dan tiap Part sekarang gerbang
    dulu di "Peran Part" (Body/Lid/Sleeve/dst — istilah dari riset Esko
    ArtiosCAD/glosarium packaging) sebelum Dimensi & Preview Dieline live
    aktif; tipe non-multi-part auto-role "MonoForm" (istilah dari "Mono
    Carton"), dieline langsung aktif. Galeri visual popup (gambar dieline ala
    Pacdora) sementara dilepas dari scope saat pemindahan ini, belum
    dibangun ulang di lokasi baru. Detail lengkap: memory
    `project_prodtype_multipart_category_system.md`. (2)
    **saran-saja** (bukan blokir/auto-koreksi, pola sama "engine suggests,
    officer confirms") kalau dimensi terlalu sempit/tidak simetris — BELUM
    ADA (0 logic ditemukan, target scope baru); (3) **mockup generator 3D**
    ala **Pacdora** (dikonfirmasi user via screenshot pacdora.com/mockups,
    "ENGINE nya adalah mockup generator") — BUKAN simulasi fold-fisika dari
    nol: pilih template box per Tipe Produk (sudah ada dropdown-nya) → upload
    desain 2D/JPEG → di-composite ke template 3D siap-pakai → render foto
    produk realistis. BELUM ADA (0 upload capability di layar ini), butuh
    keputusan desain terpisah (bikin sendiri library template shell vs pakai
    API mockup pihak ke-3) sebelum mulai bangun.
  - **ProDev Engine** (`ImpositionEngine.php`) — GOAL: dari geometri 1 part,
    cari layout produksi FISIK paling hemat bahan+plate+tooling (plano,
    UP/orientasi, interlock, gabungan antar-part, meter-lari-pisau). Output
    `prod_layout_group`. SUDAH LENGKAP (Tingkat 1/2 + Tool #2/#4/#10,
    2026-09-03).
  - **ProCost Engine** (`CostingEngine.php`) — GOAL: dari parameter produksi
    (sumber Mode #1 ATAU #2, HARUS hasil sama) hitung HPP akurat + ladder
    harga per qty-tier (toleransi/margin/pembulatan/PPN). SUDAH LENGKAP,
    4 tipe part (DC/SHEET_DIGITAL/KRAFT/DC+SHEET).
  - Detail lengkap: memory `project_inqprod_engine_goal.md`.
- **[VITAL] Alur Quotation & Customer Order — dari InqProd berharga sampai CO**
  (dikunci user 2026-09-03, jangan tanya ulang ke user soal ini):
  - **Prasyarat:** InqProd sudah melewati ProDev+ProCost (sudah ada harga/HPP)
    dan **disetujui Sales** (bukan Direksi lagi — publish costing = beda
    tahap, lihat §4 "Costing rahasia" di bawah).
  - **Langkah 1 — SALES QUOTATION**: Sales pilih-pilih InqProd yang mana saja
    masuk quotation (via `availableInquiries` — inquiry customer sama, costing
    `published_flag=true`, belum ditarik ke quotation ini); sisanya (header,
    harga, qty, diskon per baris) **AUTO** dari snapshot InqProd tapi **BOLEH
    diedit** sebelum publish. **SUDAH ADA & jalan**: `QuotationEntryService`
    + `App\Services\Sales\QuotationBuilder`.
  - **Langkah 2 — Customer OK → CUSTOMER ORDER Mode #1 (auto dari Quotation)**:
    1 CO = 1 Quotation (locked 2026-08-20, FK `co_order.quotation_id` NOT
    NULL + UNIQUE ke `quo_quotation`). Item CO = **snapshot** item Quotation
    saat CO lahir (bukan live-linked — prinsip snapshot §4). **SUDAH ADA &
    jalan**: `App\Services\Sales\CoOrderBuilder::findOrCreateFromQuotation()`
    dipanggil begitu Quotation berstatus `PUBLISHED`.
  - **Customer Order Mode #2 (manual/standalone)** — pembuatan CO **tanpa**
    lewat Quotation, meniru alur manual versi sebelumnya (ScriptCase V1), TAPI
    dengan syarat **Master Product (`prod_product`) harus dibuat dulu**
    sebelum baris CO bisa diisi. **SUDAH ADA** (2026-09-21, commit 59fab26):
    `co_order.entry_mode` INTEGRATED / INSTANT, `quotation_id` nullable,
    `CoOrderBuilder::createInstant()` + picker Master Product per baris.
  - **ATURAN LOCKED user 2026-10-01 — Quotation WAJIB integrated, CO boleh direct:**
    Inquiry → Quotation **harus selalu integrated** (baris quotation dari
    inquiry yang costing-nya PUBLISHED; tidak ada quotation yang melompati
    ProDev/ProCost, termasuk data dummy/seeder). Customer Order **boleh direct
    input tanpa Quotation** (INSTANT) karena (1) kompatibel dengan sistem lama
    (ScriptCase V1) dan (2) realita pabrik: Direksi bisa minta percepatan yang
    langsung dikerjakan Produksi — jalur tercepatnya input langsung di CO.
    Jangan hapus/persulit jalur INSTANT, dan jangan buat jalur Quotation direct.
  - **Pola sama seperti ProDev/ProCost**: 2 mode = 2 JALUR INPUT, bukan 2
    alur bisnis berbeda — tujuan akhir (dokumen CO valid, siap CPDEL) harus
    sama, cuma titik masuknya beda (dari Quotation vs manual+Master Product).
  - **Langkah 3 — CO CONFIRMED → CUSTOMER PLAN DELIVERY (CPDEL)**: nama baru
    utk "Plan Delivery" (rename 2026-09-03, & dipindah dari grup menu
    "Delivery" ke grup **"Sales Order"**, satu grup dgn Customer Order —
    lihat `sidebarmenustandard.md`/migration `rename_cpdel_and_regroup_
    delivery_menu`). **ENGINE** (baru 2026-09-03): begitu CO `confirmCo()`,
    tiap `co_order_item` PRODUCT yang belum punya jadwal manual di-auto-buat
    1 baris `co_order_delivery` (qty penuh @ `due_date` item) — item yang
    sudah diisi manual via tab Delivery CO Entry TIDAK ditimpa. **`due_date`
    WAJIB terisi di semua baris PRODUCT sebelum CO bisa di-Confirm** (guard
    tambahan 2026-09-03, `itemsMissingDueDate` — tombol Confirm CO disabled +
    warning kalau ada yang kosong) — jadi **TIDAK ADA lagi CO CONFIRMED
    dengan due_date kosong**. **SUDAH ADA & jalan**:
    `CoOrderBuilder::confirm()`. Grid CPDEL
    (`co/delivery-plan.blade.php`) sendiri **sudah live sejak 2026-08-20**
    dan punya 3 aksi per baris: **Split** (pecah qty+tanggal), **Gabung/Join**
    (baris item+CO sama → baris tanggal-paling-awal), atau **biarkan** hasil
    default engine — cocok mockup `co_delivery_plan_mockup.html`.
  - **Menu grup "Delivery"** (setelah CPDEL pindah ke "Sales Order") WAJIB
    berisi **Delivery Order** + **Delivery Notes** (2 item terpisah, keputusan
    user 2026-09-03) — meng-override keputusan lama 2026-08-28 yang sengaja
    TIDAK memberi Surat Jalan menu sendiri. Delivery Notes utk sekarang reuse
    grid `dispatch.do.index` yang sama (belum ada layar terpisah).
  - Detail & riwayat: memory `project_quotation_co_flow.md`,
    `project_cpdel_engine.md`; dokumen gabungan lengkap
    `docs/SALES_QUO_ORDER, CPDEL.md`.
