# Alur modul: aturan yang tahan lama (satu berkas)

> Disarikan 2026-10-14 dari 11 berkas `flows/flow_*.md` (73 KB) yang menggambarkan layar **v1**; berkas aslinya diarsipkan di `tmerp_unused/flows_v1_2026-10-14/`. Nama layar dan route v1 sengaja tidak dibawa: layar kini v2, daftarnya ada di `docs/SCREEN-INDEX.md`. Yang dimuat di sini hanya urutan bisnis dan aturan yang mengikat. Aturan user bernomor ada di `docs/globaluserrules.md`.

## Sales: Inquiry → Quotation → CO → CPDEL → DO (A.1-A.6, A.20)
- Inquiry punya 3 jenis: **BARU**, **REKALKULASI** (spec sama persis; tak ada field spec yang bisa diubah, hanya qty dan harga), **REVISI** (spec berubah; wajib pilih scope MATERIAL / DIMENSION / ARTWORK / COLOR / PROCESS, sistem tidak menebak). REKALKULASI dan REVISI = copy-record + nomor baru, bukan edit inquiry lama.
- Sales Confirm mengunci form; ProDev (imposition, opsional untuk part non die-cut) lalu ProCost (wajib sebelum Quotation). Publish costing = deklasifikasi harga dari Sisi A (Costing/Direksi) ke Sisi B (Sales). Mode #1 (integrasi) dan Mode #2 (manual) menghasilkan HPP sama lewat `CostingEngine` yang sama.
- Nota Inquiry: harga disembunyikan selama costing belum published.
- Snapshot di tiap transisi: Inquiry → Quotation (`QuotationBuilder`), Quotation → CO (`CoOrderBuilder::findOrCreateFromQuotation`, salinan bukan tautan hidup; 1 CO = 1 Quotation), CO → CPDEL, CPDEL → baris DO.
- Confirm CO menjalankan engine CPDEL: otomatis membuat `co_order_delivery` (qty penuh @ due_date) hanya untuk item PRODUCT yang belum punya jadwal manual; Sales boleh Split / Gabung sesudahnya tanpa mengubah qty atau harga sumber.
- DO menarik baris dari view `v_co_delivery_outstanding`, 1 baris CPDEL = 1 baris DO; harga baris DO boleh di-override (nilai asli disimpan sekali). Surat Jalan (Sisi B): kolom harga dikeluarkan dari query, bukan disembunyikan CSS.
- **Titik cek:** Confirm CO dari grid tidak boleh melewati engine CPDEL; kedua jalur Confirm harus memanggil satu method (pola `DispatchService::confirm()`). Verifikasi di layar v2.

## Production: ProDev dan Scheduling (dua tujuan berbeda)
- ProDev = layout produksi paling hemat bahan dari geometri part; Scheduling/PPIC = jadwal dan eksekusi WO. WO lahir dari baris CPDEL (CO Confirmed + approved, A.16); **1 `iqp_part` = 1 `prd_wo`**; konvergensi antar-part belum dimodelkan.
- Bill of Process hanya bisa diedit selagi WO `DRAFT` / `SIMULATED`; setelah masuk jadwal nyata = beku. Target rate default per Produk + Part + Proses + Mesin/Pegawai (`WoGenerationService::refillTargetDefault()`), selalu boleh di-override per WO.
- Aturan scheduling terkini: `docs/ENGINE-ATTENTION.md`, `docs/md_for_systems/production_scheduling_wo_generation_delta.md`.

## Procurement: PR → PQ → PO → Receive → PV
- **Approval merged-into-entry:** layar approval hanya antrian; keputusan approve / reject / hold dibuat di dokumen aslinya. Approval lewat `FlowEngineService` (matriks per jenis dokumen).
- PR dokumen internal (identitas = Departemen, bukan Supplier); lahir `MANUAL` atau `MRP AUTO` (MRP ditinggalkan, tag `arsip/mrp-mps-2026-08-30`).
- Receive baru mencakup "dari PO"; penerimaan dari Customer (titipan) dan dari Subkon (SFG kembali) belum dibangun.

## Inventory (v1; v2.01 mengikuti A.22-A.26)
- Tiga kelas stok RM / SFG / FG dengan satu layar per fungsi (parameter kelas). **Satu-satunya jalur tulis stok** = `StockDocumentService` (draft) dan `StockPostingService` (posting, saldo `BB / In / Out / Adj / EB`, `avg_unitprice` bergerak); tidak ada layar yang insert langsung ke tabel stok.
- Stock In dan Stock Out RM/SFG: DRAFT → POSTED tanpa approval. Stock Out RM/SFG menjurnal pemakaian (Dr HPP / Cr Persediaan, `USG`); baris tanpa akun lengkap dilewati dengan peringatan. **FG keluar hanya lewat Delivery Order**, bukan Stock Out.
- Stock Return wajib merujuk Stock Out POSTED dan menambah `qty_returned` di baris sumber (retur ganda ditolak); kondisi GOOD / SCRAP.
- Adjustment / Opname = satu-satunya dokumen berapproval berjenjang, ditentukan nilai absolut selisih (ambang v1: ≤5 jt kepala gudang, 5-25 jt manajer operasional, >25 jt Direksi); reject wajib alasan ≥10 karakter.
- Laporan Master Stock memakai `v_inv_movement` (rentang tanggal); Slow Moving memakai view snapshot `v_*_onhand` (kondisi sekarang).

## Finance dan Accounting
- COA harus terisi lebih dulu; kode akun dibaca lewat `account_code` (mis. AR, Uang Muka, PPN Keluaran, Bank).
- Jurnal Kas & Bank manual: DRAFT → Post (`JournalPostingService::postDraft()`) → terkunci.
- **Uang muka mengikuti A.21**, bukan layar DP lama: tagihan DP tanpa jurnal, jurnal lahir saat uang diterima, potongan di invoice saat Confirm. Rinci: `customerinvoice_accounting.md`.

## HRM
- Model `OrgPosition`, `EmpShiftRoster`, `EmpLeaveRequest`, `EmpCorrectionRequest` memakai koneksi **`tm2mst`** (prefix `org_` / `emp_`), bukan `tm2hrm`, walau folder dan route-nya `hrm/*`.
- `org_position.reports_to_position_id` = sumber tunggal hierarki; validasi anti-siklus (`wouldCreateCycle()`).
- Shift Roster punya dua mode: papan PPIC (mesin × shift × tanggal, Draft → Confirm, bentrok ditolak) dan grid HR (klik per sel, langsung tersimpan).
- Approval cuti: CUTI biasa mengurangi `EmpLeaveBalance.used_days`; Sakit / Izin / Cuti Khusus tidak. Approval koreksi data: 6 field (tgl lahir, NIK KTP, telepon, alamat KTP / domisili, kontak darurat) langsung mengubah `emp_employee`; BPJS dan rekening hanya menutup request.
- Perubahan data pegawai lewat self-service selalu berupa request PENDING → approval HR; hanya tab Security (akun login `sec_user`) yang langsung diubah.

## Recruitment
- Internal (`tm2hrr`): Requisition (APPROVED) → Vacancy (publish) → kandidat masuk (sync portal atau undang dari Talent Pool) → Pipeline kanban per stage → Interview → Assessment (guard: kandidat tak boleh keluar stage tanpa hasil) → Offer (approval) → Hire Provision (posisi resmi `org_position` dipilih manual). Approval memakai `RecruitmentApprovalService` + `rcr_*_matrix` (bukan `FlowEngineService`).
- Status kandidat: `APPLIED` → `IN_PROGRESS` → `OFFERING` → `HIRED`, atau `REJECTED` / `WITHDRAWN` / `DECLINED`.
- Portal publik (`careers.*`): akun real, verifikasi email, form lamaran; backend Firebase hardcode di `FirebaseSyncAdapter`. Arah data **selalu PULL** (`recruitment:sync-portal` tiap 2 menit, `withoutOverlapping`); idempoten dua lapis (`rcr_sync_log` per `submission_uuid` + `AppApplication.submission_uuid` unik). Status balik ke portal lewat `AppApplicationObserver` (event `updated` kolom `status`), bukan portal yang polling. Kanal Flutter belum ada.

## Sistem dan Setup
- Registrasi menghasilkan `sec_user` NONAKTIF + notifikasi ke semua admin; admin mengaktifkan dan menentukan grup. Akun dengan `must_change_password` dipaksa ganti password (gate `force.password`) sebelum masuk menu. Lupa password = 4 pertanyaan keamanan.
- Hak akses = matriks grup × modul (Akses / Input / Approve / Export) → filter menu. Konstanta, identitas, bahasa: Setup (semua `sys_constant` punya layar input, B.7).
- Tiket bantuan dua arah: user dan staf membaca-menulis tabel yang sama (`sys_support_ticket` / `sys_support_reply`); balasan staf memajukan OPEN → IN_PROGRESS. Notifikasi lintas modul lewat `SysNotification`.
- Validasi hari libur nasional pada engine cuti belum ada tabel referensinya (baru mengecualikan hari Minggu).

## Master Data
- Tidak punya urutan wajib; tiap layar independen di `tm2mst`, sumber tunggal lintas modul. Master **tidak** menyimpan nilai transaksional; transaksi membekukan nilainya (A.13). Perubahan master tidak mengubah transaksi lama.
- Pemakai utama: Customer → Inquiry, Quotation, CO, DO; Supplier → Procurement; Produk/Part → Inquiry, ProDev, ProCost; Material + rate versioned → ProCost; Mesin + Holiday → Scheduling; Warehouse → Inventory, Delivery; Currency → Finance; UOM → hampir semua.
