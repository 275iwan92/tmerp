# Indeks aturan user (bernomor), ERP CV. Telasih Makmur v2

> Ringkasan `docs/globaluserrules.md` yang **memakai nomor asli** (A.n .. E.n), supaya rujukan dari CLAUDE.md, memori, skill, dan kode tetap berlaku. Satu baris per aturan; detail, kutipan user, dan angka lengkap ada di berkas asli pada nomor yang sama.
> Dibuat ulang 2026-10-14. **[L]** = LOCKED, tidak diubah tanpa perintah eksplisit Jo. Aturan baru ditambahkan ke berkas asli (butir + Changelog), lalu indeks ini diperbarui.
> Nomor ganda di asli: **A.15** (qty) dan **A.15b** (scope modul); **A.21** (invoice) dan **A.21b** (Sales satu grup).

## A. Alur bisnis

1. **[L]** Quotation WAJIB integrated: tiap baris dari inquiry yang costing-nya PUBLISHED; tidak ada quotation direct, termasuk seeder.
2. **[L]** Customer Order BOLEH direct tanpa Quotation (CO INSTANT, dari Master Product); jalur ini tidak dihapus atau dipersulit.
3. **[L]** Alur: Inquiry (Baru/Revisi/Rekalkulasi) → ProDev → ProCost → Quotation → WIN → CO → CPDEL → POST ke Produksi → DO/Surat Jalan. Pekerjaan ±30 menit: DO/DN boleh dibuat setelah POST; hasil produksi tetap WAJIB di-input (acuan KPI).
4. **[L]** 1 Quotation = 1 CO; 1 inquiry = 1 product; WIN = Master Product lahir; Revisi = product turunan; Rekalkulasi = versi harga bertanggal.
5. **[L]** Inquiry punya 3 jenis: BARU, REVISI (spec berubah), REKALKULASI (spec sama, harga bahan berubah).
6. **[L]** ProDev dan ProCost punya 2 mode (integrasi, manual); keduanya SAH; HPP HARUS sama untuk geometri setara.
7. Engine menyarankan, petugas memutuskan.
8. Costing rahasia: HPP/margin hanya Costing + Direksi; publish = harga terlihat Sales; rumus ProCost hanya login `johiwan`.
9. Harga jual hanya Sales, Marketing, Product Costing, Operational Manager ke atas; disaring di server.
10. Due date CO = 14 hari kerja dari approval proof FISIK; CO tidak bisa Confirm bila ada baris PRODUCT tanpa due date.
11. CO Confirm = diajukan approval (F&A SPV → Operational Manager); CPDEL dibuat setelah approval terakhir. PR = Op Manager; PO = Product Costing → Op Manager.
12. Tiap modul wajib jelas siklus Confirmed / Cancelled / Closed.
13. Snapshot: transaksi membekukan nilai master; master berubah, transaksi lama tetap.
14. ACC Digital artwork wajib sebelum produksi.
15. Customer memilih SATU qty per product di CO (tombol Choose); CO tidak bisa Confirm bila product masih punya >1 baris qty.
16. SEMUA jenis CO boleh Post to Production asal Confirmed + approved; costing published tidak disyaratkan. Tanpa Inquiry = WO sederhana (tanpa routing/bahan). Baris CO wajib punya Master Product.
17. ProDev boleh mengubah dimensi, Flat Size, dan artwork part lalu Save (dua arah: `iqp_part` dan `prod_part`), dengan peringatan bila layout Confirmed / costing published / product aktif. Nama part, tipe part, qty per set, tipe produk tetap milik Inquiry.
18. Batas canvas ProDev = pilihan officer (Custom mengikuti dieline / Press bed / Die-cut bed / Press + Die-cut), tersimpan di `layout_detail.canvas_limit`.
19. Satuan: `flat_w/flat_h` = **mm**; kolom teknis ProDev (`die_w_cm`, `printsize_*`, canvas, plano) = **cm**; konversi wajib pada pemakaian silang.
15b. Satu modul = satu menu besar dengan saklar `activate`; lengkap sendiri (pintasan bila layar milik modul lain); menu = data (`sys_app_menu`), bukan kode.
20. **[L]** Tiga tanda di `iqp_inquiry`: `stat_confirm_sales/_prodev/_procost`; grid = saringan jenis AND tanda; Confirm Sales otomatis muncul di Pro-Dev; Recalc langsung ke Pro-Cost; Pro-Dev Confirm part demi part. Belum diputuskan: "back process" CO percepatan Direksi.
21. Customer Invoice + AR satu modul: dua seri nomor (Non PPN / PPN), nomor terbit saat Confirm; DP tidak menjurnal sampai uang diterima; dasar tagih = qty diterima (butuh Surat Jalan + POD); Qty Minim = harga borongan; uang muka terlacak per CO. Rinci: `customerinvoice_accounting.md`.
21b. Sales = satu grup besar: Inquiry & Quotation, Order, Delivery, CRM (grup kecil bisa dilepas-pasang); `crm_activity` per customer; komplain: informasi ke Sales, mutu ke QA; Customer 360 hanya membaca.
22. Gudang = gedung, area, bin (bentuk bebas, 2-3 lantai, bisa ditambah user); pindah stok = Stock Transfer dengan label kapasitas + QR/barcode; dead stock MERAH; label palet dan label item berbeda; denah awal perkiraan agen, bisa diubah.
23. Laporan stok lengkap (Stock Card, Summary, Recapitulation, Inspection, by Location, Loss & Gain); selisih lewat stock opname; acuan ERP populer.
24. Kartu stok: urutan = tanggal dokumen, baris sesudahnya dihitung ulang; saldo minus ditolak; periode tertutup ditolak (koreksi = Stock Adjustment); Confirm tidak diedit; dummy salah dihapus dan dibangun ulang.
25. Payroll dibatasi peran: Direksi/GM semua; HR Officer hanya Non-staff; Op Manager hanya slip bawahan; developer/admin biasa tanpa akses (`PayrollAccess`).
26. Inventory & Warehouse v2.01: INVENTORY (apa, berapa, nilai) vs WAREHOUSE (di mana); QC sampling, URGENT dock-to-line; Average per item; booking sejak costing; Stock Card rumus bersambung (BB = EB sebelumnya); koreksi lewat dokumen baru; semua perubahan PREVIEW dulu; Form WO: **Release = Available, Purchase = Required − Release**.
27. Assign Crew: satu crew = satu mesin Offset + satu shift (nama = nickname + nama shift); maks 10 anggota, orang yang sama tidak boleh bentrok pada shift + periode yang sama; bentuk form tidak menambah tabel atau kolom; jumlah kotak crew = jumlah shift produksi aktif di Master Shift (tanpa batas tetap, tanpa Delete); hanya mesin dulu, stasiun manual dan borongan ditunda.
28. Master Card = satu wujud: perpaduan Master Product dan layout Inquiry Product + ProDev + ProCost (penyatuan kode menunggu izin Jo, zona beku).

## B. Database dan data

1. **[L]** Database-per-modul (`tm2mst`, `sdi`, `pur`, `inv`, `prd`, `acc`, `hrm`, `hrr`, `pyr`, `mnt`, `sys`); tanpa FK lintas-DB.
2. **[L]** Semua tabel PK tunggal auto number BIGINT `id_<entitas>`.
3. **[L]** Database dan formula ENGLISH; bahasa lain hanya layar/cetak lewat `sys_dictionary`; nilai data dibungkus `raw()`; bahasa ikut akun; tombol/badge/status selalu English.
4. Kolom: `activate`, `stat_confirm/stat_cancel/stat_close` + `cancel_reason`.
5. **[L]** mockup.test = `ddt*`; tmerp.test = `tm2*`; jangan menulis ke `tm2*` saat UAT.
6. Semua yang tampil dibaca dari DB, semua input disimpan ke DB; tanpa data palsu atau array hardcode (laporkan di baris paling atas jawaban).
7. Setiap data punya layar input, termasuk konstanta.
8. Master REAL (Mesin, Proses, Proses×Mesin, Pegawai) tidak disentuh; spek tidak dikarang.
9. Tanya dulu sebelum memasukkan DATA.
10. Data yang sudah baku tidak ditanyakan lagi; cek `docs/LOG_JOB_DONE.md` dulu.
11. Setiap mesin wajib punya minimal satu proses.
12. Dummy: yang salah/usang DIHAPUS dan diganti; simpan ulang baseline `ddt` setelah migration.
13. Payroll terenkripsi at-rest.
14. Master hanya diubah user; sistem hanya mengubah transaksi (default boleh diperbarui, bisa di-override).
15. Dummy per STAGE (`ddt:stage`): set dump `.sql` per langkah alur, dibangun lewat aturan aplikasi yang sama dengan tombol.
16. **[L]** Material Category = PEMBENTUK KODE ITEM: rantai 5 tingkat, kode = prefix1+..+prefix5 (mis. `PCC02002`); layar = dropdown terkait; jangan tanyakan lagi.
17. **[L]** Dummy per LEVEL (stage B.15): 2 set, paling banyak 3; dua set wajib benar-benar real dan lengkap (Master Card asli + artwork + dieline dari `docs/attachments`); Test/DEMO/Contoh bukan set sah; kelebihan dirapikan per B.12 (cek rujukan tes dulu); data transaksi cukup 2 set per level; dummy wajib bisa di-reset ke baseline.

## C. Tampilan (UI/UX)

1. **[L]** Zona beku: Inquiry Product, ProDev, ProCost. Esensi dan alur tidak berubah tanpa izin; tanya dulu (file, elemen, sebelum → sesudah).
2. DevLib dulu, mockup mewarisi; tanggal dan angka lewat objek DevLib.
3. **[L]** Grid mengikuti keluarga DevLib (Master / Pre-Entry Transactional / Report); semua input diawali GRID.
4. Form: tombol aksi di atas (panel sticky), Save paling kanan di grup aksi, Back di ujung kanan; tab Riwayat selalu ada.
5. Tombol selalu English: New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back; status Draft, Confirmed, Cancelled, Closed.
6. Siklus tombol: sebelum Confirm = Save + Delete (+ Confirm); sesudah = Cancel + Print (alasan wajib).
7. Tiap form: Print (PDF/JPEG/online + QR) dan Email; Draft boleh dicetak dengan tanda air.
8. Back kembali ke layar sebelumnya; form berubah → dialog Save / Don't Save / Cancel.
9. Approval read-only; Approve/Reject final; Hold sementara; semua keputusan dicatat.
10. Input teks = komponen dengan badge aa/Aa/AA; nilai master lewat lookup; warna lewat token, bukan hex.
11. Tanggal `dd-Mmm-yyyy`; angka mengikuti bahasa.
12. Ikuti mockup persis; penyimpangan ditunjukkan JPEG dulu.
13. Keputusan desain langsung masuk mockup; jangan tampilkan mockup lama.
14. Grup keamanan = level akses, bukan jabatan.
15. Menu v2 = menu utama; status `LOCKED` menu hanya diset user.
16. Nama file/folder tanpa emoji atau non-ASCII.
17. F1 = Bantuan & dukungan di setiap layar.
18. Layer Menu (panel kiri): logo + nama aplikasi, ikon tukar/cari/kecilkan; Profile satu pintu (Chat, Edit profile, Setup, Help & support); "General" = sifat akses; Side Screen; laci dua mode.
19. Isi layar v2 selebar panel kanan (tepi 6px).
20. Tiap item menu punya ikon sendiri.
21. Tidak ada bar alur 1-8 di layar; penjelasan alur di Help.
22. Grid tanpa kotak statistik: satu baris filter teks (status kiri, jenis kanan) dengan garis bawah bergeser.
23. Panel dieline: dropdown ukuran standar, gambar + 3D bersisian, Dimensions 2 baris, Hole (front panel).
24. Cetakan: A5 landscape (1-5 baris) / A4 portrait (>5); satu generator OO (`Devlib\PrintDoc\Document`); urutan tanda tangan tetap (pihak luar → Menyetujui → Mengetahui → Pembuat); CO dengan/tanpa harga; CPDEL tanpa harga; Jadwal Kirim ETA/ETD.
25. Form ProDev berlatar putih (token tema).
26. Ink Configuration ProDev: 4 slot warna khusus yang diisi officer.
27. Panel atas ProDev = panel form standar; Print/Email satu tombol berpilihan (Master Card, ACC Digital).
28. Grid Transactional: kolom pertama persegi dokumen (nomor + pihak, Palet 1), 5-8 kolom induk, tanpa jumlah item, Expand = tabel anak ≤7 kolom memuat nama item.
29. Kerapatan grid per grid (tombol Density, di browser).
30. Logo aplikasi ≠ logo perusahaan; logo perusahaan di cetakan (hitam-putih / warna / inverse lewat constants).
31. Chat antar user: modular (`packages/chat`), online = login, lampiran, realtime Reverb opsional.
32. Baris grid berwarna menurut jatuh tempo (merah/oranye/kuning), kolom "Due" teks, hari kerja Sabtu kerja, Minggu libur.
33. Opening screen = papan widget milik user (top management dulu), widget = satu kelas + satu baris `sys_widget`, periode harian..tahunan, Overall + dashboard bidang.
34. Teks HITAM (latar terang) atau TERANG (latar gelap), tanpa abu-abu kecuali `--dl-disabled`; ditulis di token/objek DevLib; layar review = blog `x-dl::review-feed`.
35. Menu Reports: baris `menu_type='heading'` (label `Reports`, tanpa target) + jarak, laporan read-only (`object_type=report`, grid `mode="report"`) di bawahnya; ditulis lewat migration `sys_app_menu`, dirender `AppMenu::nodes` -> prop `group`.

## D. Cara kerja developer / agen

1. Peran: system analyst + developer + database designer; audit integritas proaktif.
2. Konfirmasi perjalanan user (buka X → lihat Y → klik Z) sebelum membangun; kata ambigu: sebut kedua arti.
3. Jangan menambah lingkup yang tidak diminta.
4. Bertanya lewat narasi saat diskusi; saat user minta jalan sendiri, jangan bertanya, catat keputusan.
5. Jangan memprompt user untuk situs .test; jangan pakai browser pane untuk .test. (Catatan: CLAUDE.md terbaru mengizinkan preview lewat tangkapan layar sendiri.)
6. Uji: test yang menulis DB membersihkan sendiri dan diverifikasi ke DB. (Diganti CLAUDE.md §7.11: agen menguji sendiri, diam-diam.)
7. Selesai kerja: `npm run build` bila Tailwind berubah; `git add` path eksplisit; satu branch `main`. (Commit/push kini hanya setelah Jo agree, CLAUDE.md §7.11.)
8. Backlog selesai pindah ke `docs/LOG_JOB_DONE.md`.
9. Yang tidak dipakai lagi dipindah ke `C:\Users\iwanp\tmerp_unused\` (+ MANIFEST), bukan diedit atau dibiarkan.
10. Serah terima lewat memori hand-off; arsipkan sesi setelah selesai.
11. Jangan mengetik password ke browser; jangan pakai `employee_id=1` untuk uji.
12. MRP/MPS ditinggalkan (tag `arsip/mrp-mps-2026-08-30`); dirancang ulang meniru ProDev/Inquiry.
13. Tidak boleh diarsipkan: `docs/md_prompt_rel*`, `docs/legacy_scriptcase/`, `docs/ddl/`, `docs/database/` (bahan diskusi dan pagar sistem lama).
14. Perbaikan di zona beku: preview sebelum → sesudah dulu, kerjakan setelah user OK.
15. Semua layar = turunan objek DevLib (`x-dl::*`, `x-mp::*`), bukan HTML mentah; dijaga `DevlibOnlyRatchetTest`; layar v1 diganti satu per satu.
16. Setiap ENGINE terdaftar di `EngineRegistry`; berubah → `php artisan engine:catalog`.
17. Dasar pemrograman = OO + komponen modular + library; kode utama sederhana, mudah di-debug, traceable; satu kelas satu tanggung jawab; tanpa salin-tempel; exception bernama berkonteks.
18. DevLib membuat, tmerp mewarisi; `packages/devlib` tidak memakai `App\`; dijaga `DevlibInheritanceTest`.
19. QC cepat per layar: Approved / Need improvement; tiga lapis QC → UAT officer → UAT supervisor; awal sesi `php artisan qc:reviews`.
20. Identitas agen menurut hari: Senin-Selasa Iwan, Rabu-Sabtu Jo; trailer commit `Agent:`.
21. **Standar kode:** OO + DevLib dulu; sederhana; REMARK enam butir + `docs/SCREEN-INDEX.md`; tanpa dead code (buktikan lewat grep, hapus, bukan komentar).
22. Uji mandiri sebagai user `admin` di devlib.test dan mockup.test (login di browser uji boleh); tmerp.test boleh selama UAT belum dimulai (sekarang masih jauh), dilarang setelah UAT berjalan.
23. DevLib hanya menampilkan bentuk (data contoh statis); bila perlu data, pakai berkas XML, bukan database bisnis atau JSON baru.
24. TG = ingatan agen: baca peta besar + kartu modul dan hulunya sebelum usul; usul baru wajib blok "Already exists?" (`concept:find`); kode yang dikutip kartu berubah = kartu ikut (hook); TG English, UG Indonesia; tiap halaman sub-modul wajib Read/Write map + Table relations dengan gambar (`docs:rw`).

## E. Help dan asisten AI

1. Tiap layar input dan laporan punya Help (Purpose, cara pakai, penjelasan isian) di `sys_help_topic`/`sys_help_line`; layar baru tanpa Help = belum selesai.
2. Asisten AI: tanpa internet (Ollama), tidak menghitung dan tidak menulis SQL, jawaban sesuai level user, gaji hanya GM/Direksi, modular.
3. Asisten menjalankan perintah papan widget ("pasang widget ...", "lepas widget ...", "reset widget").
