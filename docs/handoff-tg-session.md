# SERAH TERIMA: sesi Technical Guide (TG). Baca ini dulu.

Aturan: jawab Jo dalam bahasa Indonesia, pendek, jujur. Aturan resmi: rulebook D.24 dan CLAUDE.md butir 7.10. TG ditulis English penuh; User's Guide (UG) tetap Indonesia. Commit hanya setelah Jo agree; `git add` path eksplisit (satu folder, satu index, banyak sesi).

## Tujuan
TG = ingatan jangka panjang agen: per modul/sub-modul, kondisi sistem SEKARANG (tabel, kolom, alur, engine, relasi), supaya agen yang memperingatkan Jo bila usulan duplikat atau bertabrakan. Tampil di Help (F1 > Technical Guide; juga Setup > Help and Support > Technical Guide). Sumber: `docs/tg/<modul>/<sub-modul>/`.

## Sudah jadi (semua dipush)
- Format: kartu konsep, kartu ENGINE, halaman sub-modul, peta besar, peta engine, daftar relasi (`docs/tg/00_big-map.md`, `01_engine-map.md`, `02_relations.md`).
- Alat: `php artisan docs:check [--staged]`, `concept:find "<kata>"`, `docs:rw` (membangun gambar dari tabel), hook pre-commit `.githooks` (aktif lewat `core.hooksPath`; kode yang dikutip kartu berubah = kartu ikut; berkas `docs/` dan CLAUDE.md dikecualikan), rute gambar `v2.tg-img`.
- Tiap halaman sub-modul wajib `## Read/Write map` dan `## Table relations` (format: contoh `docs/tg/sales/inquiry-product/00_submodule_inquiry-product.md`; brief agen: ringkasan di bawah). Gambar `rw-<sub>.svg` dan `rel-<sub>.svg` dibangun otomatis, jangan digambar tangan.
- Kartu ada untuk: Sales (semua sub-modul), Inventory, Delivery (sesi lain), Manufacture (planning, ppic), Procurement, Setup/dummy-data. Read/Write map + Table relations SUDAH untuk: inquiry-product, prodev, product-costing, order, crm, quotation, master-data.

## Sisa pekerjaan (urutan)
1. Read/Write map + Table relations: inventory (stock, warehouse, batch-quality), procurement (order, receive, requisition, return), setup/dummy-data, manufacture (planning, ppic) SETELAH sesi penghapusan engine lama selesai, delivery SETELAH sesi Delivery selesai. Satu agen per sub-modul; hasil diperiksa sampel dan gambarnya dibuka di Help sebelum commit.
2. Rapikan peringatan `docs:check` (relasi sepihak antarkartu, sekitar 230 WARN) dan buat `DocsCheckTest` hijau lagi (merah karena kartu Manufacture mengutip engine yang dihapus sesi lain).
3. Modul tanpa kartu sama sekali: Production, Maintenance, QA, HR, Finance, Management Reports, Master Data, sebagian besar Setup.
4. Master Card satu wujud (A.28): arah sudah diusulkan (`prod_master_card` satu-satunya, view `vw_master_card` dipensiunkan), MENUNGGU izin Jo; zona beku, jangan mulai tanpa preview.

## Pondasi yang terbukti lemah atau salah: periksa ulang sebelum dipercaya
1. **Kartu ditulis dari membaca kode dan DB, bukan dari menjalankan layar.** FACT = kode atau DB yang dilihat; perilaku di browser TIDAK diuji. Agen penulis hanya diperiksa sampel (sekitar 10 klaim dicocokkan sendiri). Klaim "not checked" di kartu itu jujur; sisanya masih bisa meleset.
2. **Kartu menulis engine yang ternyata kode mati.** Kartu Manufacture mendokumentasikan `PdrEngine`, `SchedulingRuleEngine`, `InterruptionOptionGenerator`, `CompositePriorityScorer` dan lain-lain; sesi lain kemudian menghapusnya. Pelajaran: sebelum menulis kartu engine, buktikan dulu ada layar, rute, jadwal, atau engine lain yang memanggilnya.
3. **Peta engine (`01_engine-map.md`)**: kolom pemicu "layar/rute" hanya hasil grep nama kelas, bukan pemetaan tombol. Hanya pemicu terjadwal (`routes/console.php`) yang pasti.
4. **Dokumen lama dan CLAUDE.md memuat klaim yang tidak benar di kode**: contoh `doc_state_log` tidak ada di DB mana pun; "link Inquiry ke ProDev belum ada" di `claude_vital_flows.md` sudah usang; A.10 menyebut proof FISIK tetapi kode memakai ACC Digital.
5. **Pembangkit relasi/matriks**: jenis tulisan `W` = jenis tidak dibaca per baris; tautan logis lintas-database diambil dari kartu, bukan dibaca ulang tiap lookup; matriks tabel yang hanya dibaca satu proses dilipat jadi satu kolom.
6. **Tes menyentuh DB bernama `tm2*`** (live-named). Sebuah tes meninggalkan sisa baris (diperbaiki, tetapi cek DB setelah menjalankan tes WO/CPDEL).
7. **Temuan "sesi lain bahwa pondasi salah": [JO ISI]** — saya tidak melihat temuan itu secara langsung. Tempel temuannya di sini sebelum sesi baru melanjutkan, dan tandai kartu yang terkena.

## Cara kerja yang berlaku
- Sebelum usul apa pun: `php artisan concept:find "<kata>"` dan baca kartu modul + hulunya. Usul baru wajib blok "Already exists? Searched where, result".
- Kartu: Canonical name, Facts (Item | Content | Evidence), Relations (source / used_by / flows / frozen_zone), Findings, Already exists. Angka/rumus harga hanya di bawah `<!-- johiwan-only -->`.
- Setelah mengubah `docs/tg`: `php artisan docs:rw` lalu `php artisan guides:import` di mockup (`--env=mockup`) dan tmerp; `docs:check` 0 ERROR.
- Dua sesi menyunting berkas yang sama (rulebook, SCREEN-INDEX)? Commit hanya bagian sendiri: tulis HEAD+bagian sendiri ke berkas, commit dengan pathspec, lalu kembalikan salinan kerja.
- Bash di Windows tidak punya `php`; pakai PowerShell atau `C:\Users\iwanp\.config\herd\bin\php.bat`.

## Prompt singkat untuk sesi baru
"Baca docs/handoff-tg-session.md dan memori project_tg_technical_guide. Lanjutkan sisa pekerjaan nomor 1 (Read/Write map + Table relations) mulai dari Inventory, satu agen per sub-modul, tunjukkan gambar di Help sebelum commit. Periksa dulu 'Pondasi yang lemah' nomor 7 (saya tempel di sana)."
