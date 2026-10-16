# ENGINE Process: BOP dan BOM dari Inquiry Product, resep per tipe produk (diskusi Jo 2026-10-16)

> Status: HASIL DISKUSI, belum ada kode. ProDev zona beku: narasi (dokumen ini), lalu PREVIEW (JPEG), lalu izin Jo, baru dibangun.
> REVISI Jo 2026-10-16 (lihat §6): layar yang dipakai = Pro-Dev New yang sudah ada (bukan layar baru); engine selalu jalan di background.
> Pelengkap: `docs/handoff-prodev-session.md`, `docs/ENGINE-ATTENTION.md`, `docs/mastercard-okt-analysis.md`.

## 1. Keputusan Jo
1. **Tombol Process** di ProDev menghitung semuanya sekaligus, urut: imposisi (layout, up, lembar) -> data Inquiry Product (ukuran, bahan, finishing, qty) -> routing BOP -> BOM. Hasil = draf; officer memeriksa lalu memutuskan.
2. Untuk **produk baru**, Process boleh jalan sendiri saat layar ProDev dibuka (hanya bila belum ada hasil tersimpan). Hasil officer (OVERRIDE/MANUAL) tidak pernah ditimpa. Draf tidak menulis ke database sebelum officer menyetujui.
3. **Setiap tipe produk punya BOP dan BOM sendiri** (Kalender berbeda dari Packaging, mis. Kalender tidak melem paper-sheet, Packaging melem). Satu tipe beda ukuran = resep sama, angka dihitung ulang (tafsir agen, Jo tidak menyanggah).
4. **Tipe diketahui dari Inquiry** (`prod_type.type_code`, dipilih di Inquiry).
5. **Resep per tipe diisi agen** berdasarkan data tipe, **officer yang Confirm**; Jo tidak mengisi.
6. **Tools untuk officer**: layar Template Review (satu tipe per kartu).

## 2. Joint type BUKAN "perlu lem" (koreksi Jo)
- Joint type = cara menyambung sisi box untuk produk besar: **single join** (lem satu sisi, kiri dan kanan satu sisi saja) atau **double join** (lem kiri dan kanan di kedua sisi). Master `mat_join_type` (mis. kode `GLUE_DOUBLE`).
- "Perlu lem" antara paper dan sheet (laminasi) hal berbeda.
- **Celah (FAKTA kode):** `SpecEngine` menurunkan `NEEDS_GLUE` dari teks `joint_type` dengan pola `GLUE_JOINT` (`glue|lem|lock bottom|crash|auto bottom`), jadi dua hal tercampur. Belum dibuktikan dengan data.

## 3. Peta kode (FAKTA)
- `app/Services/ProDev/SpecEngine.php`: `generate` (BOP dari `prod_bop_rule`, disaring `part_type` saja; BOM dari bahan Inquiry + aturan material; tinta; imposisi dari `ctx`).
- Sudah ada tetapi **tidak dipakai SpecEngine**: `prod_routing_template` (+ `prod_routing_step`), `ProdRoutingTemplate`, `RoutingTemplateService` (validasi `prod_process_precedence`, usulan mesin), layar `RoutingTemplateGrid`. Dirujuk hanya oleh `EngineRegistry`.
- BOM per tipe belum punya tempat (template baru mengatur langkah, bukan bahan).
- Celah hasil analisis 14 Okt: `docs/mastercard-okt-analysis.md` §4 (urutan kartu hilang, proses tanpa master = NO_MACHINE, urutan set Buku, rakitan minta kertas, tinta 0, dieline vs pisau).

## 4. Usulan (menunggu preview dan izin Jo)
- **Sambungkan template tipe ke Process**: tipe dari Inquiry -> template tipe jadi kerangka BOP -> mesin/SUBCON, waste, qty dari imposisi; `prod_bop_rule` hanya untuk finishing tambahan.
- **Bahan per tipe**: tempat bahan standar dan syarat (lem laminasi, plastik, tanpa sheet, joint single/double). Pilihan: kolom bahan di langkah template atau tabel baru (perubahan skema, butuh izin).
- **Pisahkan** penanda joint (single/double) dari penanda lem laminasi.
- **Draf template diisi agen** dari data tipe, Master Card asli (`docs/attachments/`), `prod_bop_rule`, produk serupa; tiap baris punya alasan.
- **Master**: template = master, hanya diubah user. Draf disimpan terpisah; tulis ke master hanya setelah officer Confirm. Tipe tanpa template yang di-Confirm: fallback ke perilaku sekarang.
- **Layar Template Review (officer)**: objek DevLib; Confirm, Edit, Back to Draft, tab Riwayat; isi = langkah BOP, bahan BOM, aturan joint, syarat laminasi.
- **Pemeriksa kecocokan** (ukuran, gramatur, proses vs Inquiry): temuan berupa daftar, tidak memblokir.
- Hilir: ProCost membaca BOP/BOM yang sudah diputuskan; MRP membaca BOM yang sama; aturan substitusi tidak berubah.

## 5. Belum diperiksa
Jumlah tipe di `prod_type` dan kelengkapan template/Master Card per tipe; isi `prod_bop_rule`; apakah up/imposisi di BOM benar. (php tidak ada di PATH bash sesi diskusi.)

## 6. Revisi Jo 2026-10-16: layar Pro-Dev New, engine background
- **Layar = Pro-Dev New** ("Imposition Plano Optimizer": accordion Imposition & Nesting, Plano Configuration, Bill of Material, Ink Configuration, Bill of Process; tombol Confirm/Save/Edit). Berkas: `packages/mockup-parts/resources/views/prodev/workbench.blade.php` + `.../js/components/prodev/workbench.js`; mesin hitung `SpecEngine`. Tidak ada layar baru.
- **Tidak ada tombol Process wajib**: engine jalan otomatis di background, hasil tampil sendiri. Hasil officer (OVERRIDE/MANUAL) tidak pernah ditimpa; draf tidak menulis DB sebelum Save/Confirm.
- **Kasus BOM kosong (BOX BTP OMEGAMOTO SPOTLIGHT, IQ2690068, 100 pcs)**: DB mockup: flat 740 x 650 mm ada, pack 100, tipe part DC+SHEET, tetapi `iqp_layout` belum ada dan `join_type_id` kosong. Kode: lembar cetak = qty_in langkah BOP pertama berunit LBR; tanpa up/canvas nilainya kosong sehingga "Print sheets needed: -" dan "cannot be calculated yet" (FAKTA kode; penyebab pasti di layar Jo belum dibuktikan). Jalur WO (`forWorkOrder`) sudah punya cadangan 1 up + peringatan, jalur layar belum.
- **Usulan (preview `docs/mockups/prodev_engine_per_accordion.html/.jpg`)**: (1) badge tipe + satu baris status engine di panel atas; (2) imposisi draf otomatis dari flat Inquiry saat belum ada layout tersimpan, alasan tertulis bila tetap gagal; (3) kolom "Asal" di BOM/BOP (template tipe / aturan umum / override); (4) joint single/double dan lem laminasi paper-sheet sebagai dua penanda terpisah; (5) panel "Template tipe" di Bill of Process dengan tautan ke Template Review.
- **Template Review (Confirm officer)**: usulan opsi 1 = tab "Template per tipe" di Setting Aturan BOP (master, jangan Confirm master dari layar satu part); opsi 2 = panel di Pro-Dev New. Menunggu pilihan Jo.
- **Fakta tambahan DB mockup**: `prod_type` 63 (28 aktif); `prod_routing_template/step` 0 baris; `prod_bop_rule` 26 (part_type semua kosong); `mat_join_type` 4 (STITCHING/GLUE x SINGLE/DOUBLE); Inquiry (`iqp_inquiry`, `iqp_part`) tidak punya kolom tipe produk (`iqp_type` = NEW/RECALC/REVISE); Master Card dummy 15 kartu, hanya 5 tipe.
- **Keputusan menunggu Jo**: tipe di Inquiry (kolom baru `iqp_part.type_code`); bahan per tipe opsi A (kolom di `prod_routing_step`) atau B (tabel `prod_routing_step_material`, rekomendasi); tempat Template Review; imposisi otomatis hanya untuk part tanpa layout tersimpan.
- **Tambahan Jo: tombol "Check / Process" di accordion Bill of Material**: menjalankan `MaterialPlanningEngine` (ketersediaan) saat diklik, untuk perubahan manual officer; engine dasar tetap otomatis. Dari situ muncul kandidat substitusi per baris bahan, ditampilkan bertingkat di bawah baris terpilih. Aturan disetujui: ML hanya mengusulkan dan meranking, officer memutuskan; kandidat = kategori sama, gramatur TIDAK berubah, ukuran lembar boleh lebih besar; BOM/BOP awal ProDev tidak berubah oleh substitusi (`prod_material_alt` = bahan cadangan, `app/Services/Production/SpecChange/*`).
- **FAKTA kode**: `MaterialPlanningEngine` menghitung ketersediaan (`explodePre` baca, `persistPre` tulis booking) tetapi belum punya pencari kandidat substitusi; tombol harus memanggil jalur baca saja. Pencari + ranking = logika baru.
- **PERTANYAAN untuk Jo**: kandidat yang dipilih officer di layar Pro-Dev hanya disimpan sebagai bahan cadangan (`prod_material_alt`, BOM ProDev tetap; rekomendasi) atau mengganti baris BOM ProDev (bertentangan dengan aturan substitusi di atas)?
