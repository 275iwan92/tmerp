# PEDOMAN ATURAN USER, ringkas (ERP CV. Telasih Makmur v2)

> Ringkasan `docs/globaluserrules.md` (RELEASE 1 Okt 2026, 843 baris) dibuat 2026-10-13, disetujui Jo.
> Satu aturan = satu butir. Bila bertentangan dengan dokumen lain, dokumen ini menang, kecuali Jo memberi keputusan lebih baru.
> **[LOCKED]** = tidak diubah tanpa perintah eksplisit Jo. Rincian implementasi (kelas, konstanta, path) ada di REMARK file dan `docs/SCREEN-INDEX.md`, bukan di sini.
> Berkas asli tetap ada sebagai arsip sampai Jo menyetujui penggantian.

## 0. Prinsip tertinggi
- **Semua zona beku**: flow data dan flow dokumen tidak diubah kecuali Jo meminta. Jangan pernah mengubahnya tanpa seizin Jo; ragu = tanya dulu (file, elemen, sebelum -> sesudah).
- Semua data dummy berujung pada **Master Card + artwork + dieline dengan ukuran jelas (P x L x T, datar, mm)**, dimulai dari Inquiry. Tanpa itu data tidak dibuat.
- Hulu menentukan segalanya: Inquiry -> ProDev -> ProCost -> CO -> Planning. Planning hanya menjadwalkan; Master Card terbit otomatis di ProDev.
- Fokus PPIC = scheduling; layar PPIC minim interaksi, kaya progres.

## A. Alur bisnis
1. **[LOCKED]** Inquiry -> Quotation wajib integrated (costing PUBLISHED lewat ProDev + ProCost), termasuk dummy. Tidak ada "quotation direct".
2. **[LOCKED]** Customer Order boleh direct tanpa Quotation (INSTANT, dari Master Product); jalur ini tidak dihapus atau dipersulit.
3. **[LOCKED]** Alur: Inquiry (Baru/Revisi/Rekalkulasi) -> ProDev -> ProCost -> Quotation -> WIN -> CO -> CPDEL -> POST ke Produksi -> DO / Surat Jalan.
   - Pekerjaan ±30 menit: setelah POST, DO dan Surat Jalan boleh dibuat tanpa menunggu produksi; batas 30 menit = nilai yang bisa diubah, bukan hardcode.
   - Hasil produksi tetap wajib di-input (acuan KPI semua departemen).
4. **[LOCKED]** 1 Quotation = 1 CO. Quotation WIN = Master Product lahir; Revisi = product turunan; Rekalkulasi = versi harga bertanggal.
5. **[LOCKED]** Inquiry 3 jenis: BARU, REVISI (spec berubah), REKALKULASI (spec sama, harga bahan berubah).
6. **[LOCKED]** ProDev dan ProCost punya 2 mode (terintegrasi, manual); keduanya sah dan **HPP harus sama** untuk geometri setara.
7. Engine menyarankan, petugas memutuskan (scheduling, imposition, routing, target).
8. Costing rahasia: HPP/margin hanya Costing + Direksi; publish = harga boleh dilihat Sales. Rumus ProCost hanya terlihat untuk login `johiwan`.
9. Harga jual hanya untuk Sales, Marketing, Product Costing, Operational Manager ke atas; harga disaring di server.
10. Due date CO = 14 hari kerja dari approval proof FISIK; CO tidak bisa Confirm bila ada baris PRODUCT tanpa due date.
11. CO Confirm = diajukan approval (F&A SPV -> Operational Manager); CPDEL lahir setelah approval terakhir. PR = Ops Manager; PO = Product Costing -> Ops Manager.
12. Setiap modul wajib jelas siklusnya: Confirmed, Cancelled, Closed. Tidak ada dokumen mengambang.
13. Snapshot: transaksi membekukan nilai master saat momen penting.
14. ACC Digital artwork wajib sebelum produksi.
15. Customer memilih SATU qty per product di CO (tombol Choose); CO tidak bisa Confirm selama satu product punya >1 baris qty.
16. Semua jenis CO boleh di-Post to Production asal Confirmed + approved; costing published tidak disyaratkan. Product tanpa Inquiry = WO sederhana (tanpa routing/bahan).
17. ProDev boleh mengubah dieline, dimensi, flat size, artwork part (tab Part & Dieline), simpan dua arah (`iqp_part` + `prod_part`) dengan peringatan dampak; nama part, tipe part, qty per set, tipe produk tetap milik Inquiry.
18. Canvas limit ProDev = pilihan officer (Custom ikut dieline = bawaan, Press bed, Die-cut bed, Press + Die-cut).
19. Satuan: `flat_w/flat_h` = **mm**; kolom teknis ProDev = **cm**; setiap pemakaian silang wajib dikonversi.
20. Inquiry -> ProDev -> ProCost lewat tiga tanda di satu tabel `iqp_inquiry` (`stat_confirm_sales/_prodev/_procost`); menu dipisah per jenis. New/Revise: Inquiry -> ProDev -> ProCost; **Recalc: Inquiry -> ProCost langsung**. Confirm Sales otomatis muncul di ProDev. ProDev Confirm part demi part. Repeat order tanpa perubahan = CO direct.
    - Belum diputuskan: "back process" untuk CO direct percepatan Direksi.
21. Scope modul: satu modul = satu menu besar dengan saklar `activate` (mati = menu dan route tertutup, data tetap). Menu = data (`sys_app_menu`), pintasan = baris ber-target sama. Modul lengkap sendiri: semua layar yang dipakai bisa dibuka dari menunya.
22. Sales = satu grup besar: Inquiry & Quotation, Order, Delivery, CRM (bisa dilepas-pasang). Aktivitas = `crm_activity` per customer; komplain: informasi ke Sales, mutu ke QA (Sales baru bisa Close setelah QA menjawab); Customer 360 hanya membaca.
23. Customer Invoice + AR satu modul: dua seri nomor (Non PPN, PPN) terbit saat Confirm; DP tidak menjurnal sampai uang diterima; nilai invoice penuh dengan potongan DP; dasar tagih = qty diterima customer; Qty Minim = harga borongan; **setiap tagihan terkait Surat Jalan + validasi customer**; DP terlacak per CO. Dokumen keuangan: `md_for_systems/customerinvoice_accounting.md`.
24. Inventory & Warehouse: gudang = gedung (bentuk bebas, 1-3 lantai), area dan bin bisa ditambah user; pindah stok lewat Stock Transfer dengan label kapasitas + QR/barcode; dead stock MERAH (transfer bukan pergerakan); kapasitas = luas x tinggi; approval transfer bisa diaktifkan; label palet dan label item berbeda, wajib ada QR + barcode + tanggal tiba; posisi barang = X, Y, stack (sisa ditumpuk dalam satu palet).
25. Laporan stok: Stock Card, Summary, Recapitulation, in Inspection, by Location, Loss & Gain; selisih masuk lewat stock opname. Kartu stok: dokumen bertanggal mundur disisipkan dan baris sesudahnya dihitung ulang; posting yang membuat saldo minus ditolak; periode tertutup ditolak (koreksi lewat Stock Adjustment); belum Confirm boleh dibetulkan, sudah Confirm dikoreksi lewat dokumen baru (Pembalik untuk salah input, keterangan wajib). **Stock Card bersambung**: BB = EB sebelumnya, saldo seluruh item.
26. Inventory v2.01: INVENTORY (apa, berapa, nilai, pemilik) vs WAREHOUSE (di mana, fisik); QC sampling, URGENT langsung ke produksi (pengaman: PPIC/Supervisor/Ops Manager+, alasan wajib, FSC/food grade butuh persetujuan kepala QC); kurang kirim diminta dikirim; booking dimulai sejak costing menghitung (lunak, keras saat CO Confirmed); valuasi **AVERAGE per item**, bin hanya qty; titipan customer = stok pemilik Customer.
27. Payroll dibatasi peran: Direksi/GM semua; HR Officer hanya Non-staff; Ops Manager hanya slip bawahan; admin/developer biasa tanpa akses.

28. **Rumus BOM di Form WO PPIC (user 2026-10-13):** Release = Available (nilai awal, officer boleh mengubah dengan alasan, tidak melebihi Required); Purchase = Required - Release. Available = On hand dikurangi sisa booking terbuka semua tahap (min 0); Required dari ENGINE ProDev. Release hanya menentukan gate material PPIC dan TIDAK mereservasi stok (reservasi = booking, ENGINE ProCost). Kode: `Ppic::bomSheet`, `GateService::ownLines`, `AvailableStock`.

## B. Database & data
1. **[LOCKED]** Database per modul (`tm2*`); FK fisik tidak boleh lintas-DB, integritas dijaga aplikasi.
2. **[LOCKED]** Semua tabel: PK tunggal auto number BIGINT `id_<entitas>`.
3. **[LOCKED]** Database dan formula **English**; bahasa lain hanya layar dan cetak lewat kamus `sys_dictionary`. Kamus hanya label: nilai data dibungkus `raw()`. Bahasa ikut akun user; pesan diterjemahkan di tempat dibuat (`t()`, `tf()`); tombol, badge, status selalu English.
4. Kolom: `activate`; status dokumen `stat_confirm/stat_cancel/stat_close` + `cancel_reason`.
5. **[LOCKED]** mockup.test = `ddt*`, tmerp.test = `tm2*`; jangan menulis ke `tm2*` saat UAT.
6. Semua yang tampil dibaca dari database, semua input disimpan ke database; tidak ada data palsu. Menemukan jalur palsu: lapor di baris paling atas.
7. Setiap data punya layar input, termasuk konstanta.
8. Master REAL tidak disentuh: Mesin, Proses, Proses x Mesin, Pegawai. Jangan mengarang spek.
9. Tanya dulu sebelum memasukkan DATA; data baku jangan dipertanyakan (cek `LOG_JOB_DONE.md`).
10. Setiap mesin wajib punya minimal satu proses.
11. Dummy: selalu sediakan untuk layar baru; dummy salah atau usang dihapus dan diganti (per baris, hanya `ddt*`), lalu simpan ulang baseline. Dummy disusun per STAGE (`ddt:stage`) lewat aturan aplikasi yang sama dengan tombol di layar.
12. Payroll terenkripsi at-rest (`PayrollEncrypted`).
13. Master hanya diubah user; sistem hanya mengubah transaksi (default boleh diperbarui, tetap bisa di-override).
14. **[LOCKED]** Material Category = pembentuk KODE ITEM: rantai 5 tingkat, kode = prefix1..5 (`PCC02002`), layar = dropdown saling terkait. Jangan ditanyakan lagi.

## C. Tampilan
1. **DevLib dulu, layar mewarisi**: semua layar v2 = objek `x-dl::*` / `x-mp::*`, tanpa HTML mentah; tanggal dan angka lewat objek DevLib.
2. **[LOCKED]** Grid mengikuti keluarga DevLib (Master / Pre-Entry Transactional / Report); semua input diawali GRID; Pre-Entry tidak diubah tanpa perintah.
3. Form: tombol aksi SELALU di atas (panel sticky), Save paling kanan di grup aksi, Back di ujung kanan; tab Riwayat selalu ada.
4. Tombol dokumen English: New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back. Status: Draft, Confirmed, Cancelled, Closed. Sebelum Confirm: Save + Delete (+ Confirm); sesudah: Cancel + Print (alasan wajib).
5. Setiap form punya Print (PDF, JPEG, HTML + QR) dan Email; Draft boleh dicetak dengan tanda air DRAFT.
6. Back kembali ke layar sebelumnya; form berubah wajib dialog Save / Don't Save / Cancel.
7. Approval: read-only; Approve/Reject final; Hold sementara; setiap keputusan dicatat.
8. Input teks pakai komponen dengan badge aa/Aa/AA; nilai yang ada di master dipilih lewat lookup; warna lewat token tema, bukan hex.
9. Tanggal `dd-Mmm-yyyy`; angka mengikuti bahasa (id 1.234,56 / en 1,234.56); mata uang pakai kode (IDR).
10. Ikuti mockup persis; penyimpangan atau perubahan visual ditunjukkan dulu sebagai JPEG, tunggu persetujuan. Preview layar yang sudah ada = tangkapan layar asli, angka bersambung.
11. Menu v2 dari `sys_app_menu` adalah menu utama; status `LOCKED` hanya diset user; tiap item punya ikon sendiri.
12. Layar selebar panel kanan (tepi 6px); tanpa bar alur (alur ditulis di Help).
13. Grid tanpa kotak statistik: satu baris filter teks + jumlah; kolom pertama grid transaksi = persegi dokumen, baris induk 5-8 kolom, Expand = tabel anak maks 7 kolom memuat nama item; kerapatan per grid (tombol Density); baris berwarna menurut jatuh tempo (hari kerja, Minggu libur).
14. Panel dieline: ukuran standar lewat dropdown, jendela 3D di samping gambar (bisa diputar), Dimensions 2 baris berkelompok.
15. Cetakan dokumen: A5 landscape (1-5 baris) / A4 portrait (>5); satu generator OO (`Devlib\PrintDoc\Document`), posisi elemen sama; kaki "Dicetak pada ... oleh <nama>"; dokumen tanpa nilai uang tanpa total; urutan tanda tangan kiri ke kanan: pihak luar, Menyetujui, Mengetahui, Pembuat (tetap, jangan dibalik); penanda tangan lain = konstanta. Format yang sudah A (Approved) tidak diubah tanpa permintaan.
16. Logo aplikasi (ERP TM) tidak berubah; logo perusahaan di cetakan (hitam-putih untuk cetak H/P, warna berlatar putih untuk cetak warna).
17. Layer Menu: nama aplikasi (bukan perusahaan), mode mini, klik logo = tutup semua tab + opening screen, klik avatar = Profile (satu pintu: Chat, Edit profile, Setup, Help & support). Edit profile dan Help & support = Side Screen. Logout menutup semua tab.
18. "General" = sifat akses (`required_object = GENERAL`), bukan menu.
19. Setup = sepuluh grup tetap (Security, Support & Ticket, Help and Support, Language and Localization, Dummy Data & Migration, Constants, Dashboard and Widgets, Menu, Identification, Others).
20. Opening screen = papan widget milik user (Customize, Add Widget, Reset); top management dulu, jabatan lain template; target KPI dan template = data.
21. Chat antar user: modular (`packages/chat`), online = sedang login, lampiran, realtime via Reverb dengan cadangan polling.

## D. Cara kerja developer / agen
1. Peran: system analyst + developer + database designer; menilai desain dan audit integritas data secara proaktif.
2. Konfirmasi perjalanan user ("buka X -> lihat Y -> klik Z -> dapat W") sebelum membangun.
3. Jangan menambah lingkup yang tidak diminta; kebutuhan belum jelas = proposal tertulis.
4. Bertanya lewat narasi, bukan widget pilihan; bila user minta berjalan sendiri, catat keputusan untuk ditinjau.
5. **Uji sendiri (Jo 2026-10-13):** hasil kerja diuji agen sendiri, otomatis, tanpa Jo, senyap di latar belakang, selalu dengan Sonnet 5.5 medium; metode apa pun boleh asal tidak bertanya atau meminta prompt dari Jo. Tes yang menulis ke database wajib membersihkan sendiri dan hasilnya diverifikasi ke DB. Layar dinilai dari tangkapan layar sendiri.
6. **Perubahan, commit, push (Jo 2026-10-13):** setiap perubahan flow dan mockup UI/UX seizin Jo. Izin didapat lewat PREVIEW + penjelasan singkat: disimpan ke tabel mana, database mana, apa yang disimpan, dan cara menyimpannya (rumusnya). Jo agree -> commit dan push ke GitHub otomatis; Jo tidak agree -> rollback. Selain itu: `npm run build` bila class Tailwind berubah; `git add` hanya path jelas (satu folder, satu git index); satu branch `main`, tanpa branch/worktree baru.
7. Backlog selesai pindah ke `docs/LOG_JOB_DONE.md`; yang tak dipakai ke `C:\Users\iwanp\tmerp_unused\` (+ MANIFEST).
8. Jangan mengetik password; jangan memakai `employee_id=1` untuk uji.
9. MRP/MPS lama ditinggalkan (tag `arsip/mrp-mps-2026-08-30`); dirancang ulang meniru ProDev/Inquiry (kini: autopilot scheduling PPIC).
10. Folder acuan yang tidak diarsipkan: `docs/md_prompt_rel*`, `docs/legacy_scriptcase/`, `docs/ddl/`, `docs/database/`.
11. Dasar pemrograman = OO + komponen modular + library; kode utama sederhana, mudah di-debug, traceable; satu kelas satu tanggung jawab; tanpa salin-tempel; error = exception bernama berkonteks.
12. Arah warisan: **DevLib membuat, tmerp mewarisi**; `packages/devlib` tidak boleh memakai kode `App\`.
13. Setiap ENGINE terdaftar di `EngineRegistry` (+ `php artisan engine:catalog`); rumus ProCost tidak ditulis.
14. QC per layar tiga lapis: QC (user) -> UAT officer -> UAT supervisor = Ready to release. Awal sesi: baca `php artisan qc:reviews`.
15. Identitas agen per hari: Senin-Selasa Iwan, Rabu-Sabtu Jo; trailer commit `Agent: Iwan|Jo`.
16. Setiap file yang diubah memuat blok REMARK (Tujuan, Sumber hulu, Input, Output, Dipakai oleh, Status) dan `docs/SCREEN-INDEX.md` dirawat; hapus dead code pada file yang disentuh.
17. Serah terima: tulis hand-off di memori.

## E. Help & Asisten AI
1. Setiap layar input dan laporan wajib punya Help (untuk apa, cara pakai, penjelasan isian) di `sys_help_topic` / `sys_help_line`; layar baru tanpa Help = belum selesai.
2. Asisten AI: offline (Ollama), **tidak menghitung dan tidak menulis SQL**, angka dari kelas teruji, jawaban sesuai level user, gaji hanya GM + Direksi, modular, riwayat 30/14/7 hari menurut level.
3. Perintah papan widget lewat asisten ("pasang widget ...") memakai aturan papan yang sama dengan tombol Customize.

## Belum diputuskan (butuh Jo)
- Arti SPILL, dua jalur POST, qty Bad QC, P18-P22, arti status READY, "back process" CO direct.
