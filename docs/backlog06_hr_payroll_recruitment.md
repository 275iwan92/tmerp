# BACKLOG 06 — HR, PAYROLL & RECRUITMENT

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Master karyawan & organisasi, presensi/roster, payroll, dan portal rekrutmen.

---


## 8. HRM / RECRUITMENT (tm2hrm, tm2hrr)

- **`org_position` masih ada posisi HR Manager/Dept Head yang tidak
  ter-mapping** ke pegawai asli — approval Recruitment saat ini jalan
  lewat tabel terpisah (`rcr_approver_assignment`, role→user langsung)
  sebagai workaround, bukan lewat `org_position` yang seharusnya jadi
  sumber kebenaran jangka panjang. Perlu HR data-cleanup tersendiri, di
  luar cakupan Recruitment itu sendiri.
- **Flutter mobile channel** untuk Portal Rekrutmen — dicatat sebagai
  rencana di CLAUDE.md §2, backend Firestore sudah siap dipakai bareng,
  tapi kanal HP-nya sendiri belum dibangun sama sekali.
- **Master Komponen / arah final Master Card** — pertanyaan lama (Jo,
  2026-08-18) soal di mana data product/part/material/proses seharusnya
  diinput (WO vs Costing). **DITUNDA (keputusan user 2026-09-02)**: akan
  dibahas nanti bersamaan modul **Sales — Product Costing**, bukan ditutup
  dari sisi WO Detail Master Card saja. Jangan asumsikan tab Master Card
  (2026-08-28) sudah jadi jawaban final sebelum diskusi itu terjadi.

---

---

## 15. PAYROLL & TIME ATTENDANCE — STATUS & STAGE HANDOFF (2026-09-09)

> Ditulis atas instruksi user ("buatkan stage buat saya pindah ke prompt
> lain, agar prompt lain paham") setelah checklist 11-layar Payroll
> selesai. Tujuannya supaya sesi/prompt berikutnya yang menyentuh modul
> ini tidak perlu menggali ulang dari nol.


### Checklist 11 layar — SEMUA SELESAI
01 Upload Finger/Excel · 02 Grid Presensi Harian + Override · 03 Payroll
Batch Run · 04 Cetak Slip Gaji · 05 Generator Transfer Bank · 06 Roster
Cash + Pecahan Uang · 07 Master Karyawan + Rekening Bank · 08 Laporan
Kedisiplinan Harian · 09 Mobile GPS Clock-in (backend nyata, pakai
`navigator.geolocation` browser sungguhan + geofence Haversine, BUKAN
simulasi lagi) · 10 ESS Pengajuan Cuti/Sakit · 11 HR Approval Workflow.

Termasuk: enkripsi gaji AES-256-GCM (`App\Casts\PayrollEncrypted`, key
`VERBA_SANCTA`), BPJS + PPh21 opt-in per-karyawan (flag, bukan global),
PWA installable di HP (manifest dinamis + service worker + ikon GD),
validasi engine terhadap data Excel lapangan riil (`docs/attachments/`).

### ⚠️ Pertanyaan bisnis TERBUKA — belum dijawab user
**Eligibilitas Uang Roti**: engine saat ini cuma bayar ke jabatan yang
namanya mengandung "Staff"/"SPV" (mengikuti teks literal Tier2 §4.4),
tapi data Excel HR asli menunjukkan level **Operator** di Produksi juga
menerima Uang Roti. **Belum diubah** — menyangkut uang, jangan ubah tanpa
konfirmasi eksplisit user. Rujukan: memory Claude Code
`project_payroll_real_data_validation.md`.

### Gap kecil belum dikerjakan (bukan permintaan eksplisit, dicatat "belum")
- Format file transfer bank masih CSV generik, belum format clearing
  resmi BCA/Mandiri.
- Tabel TJ_KEAHLIAN & rate lembur-per-jam masih free-form per-karyawan,
  belum berbasis lookup jabatan.
- PPh21/BPJS belum direkonsiliasi ke sistem faktor "GAJI PER JAM" di
  slip cetak.
- Logo perusahaan asli belum dipasang di ikon PWA (masih ikon generik
  hasil PHP GD, `Brand::logoPath()` sudah ada tapi belum dipakai di sini).

### Rujukan lengkap
`docs/LOG_JOB_DONE.md` (entri "2026-09-07 (lanjutan 6)"), memory Claude
Code: `project_gps_clockin_and_bank_screens.md`,
`project_payroll_flow_screens.md`, `project_payroll_foundation_built.md`,
`project_payroll_real_data_validation.md`, `project_pwa_mobile_install.md`.

---

---

## 20. PAYROLL — TARIF LEMBUR ENGINE ≠ FILE GAJI HR (temuan 2026-09-19)

Sample payroll dari file gaji mingguan (`hrm:import-hr-sample --step=payroll`) menunjukkan
lembur engine cuma ~52% dari Excel HR. HR memakai tarif **"LEMBUR PER JAM" per karyawan**
(sheet PROPOSAL, mis. Rp19.000/jam), sedangkan `PayrollEngineService::overtimePay()`
menurunkannya dari gaji pokok harian ÷ durasi shift × multiplier. Belum ada kolom tarif
lembur per karyawan di `pyr_employee_salary`. Selisih kecil lain: prorata telat
(≤ Rp2.350/minggu) dan 3 kasus izin/alpha (±Rp35-50 rb). Perbandingan per orang:
`storage/app/private/hr/payroll_compare_*.csv`. Perlu keputusan user sebelum mengubah engine.

Juga menunggu HR: jabatan 9 staf (RONY, DENI IRA, APRILIANTI, M. RAFLY, WIDITA, INDIRA,
DIDIN, SHOFIA, TEGAR), tgl lahir SHOFIA AZKIA (Excel 2026-04-10), 8 nama panggilan formasi
(ARIF x2, MUCHLAS, RENDA, ERNA, MUJI, DEA, SITI, TYAS).

---

---

## DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Konteks lengkap audit ini ada di `BACKLOG.md`.

### 12.5 HR — Attendance & Payroll, gap teknis konkret

- **DELTA-01**: `emp_shift` butuh dimensi lokasi/gedung baru untuk pola
  shift Satpam (S1-S5) — skema saat ini tidak punya konsep gedung.
- **DELTA-03**: business unit ke-3 (Indostry Packaging) belum punya kode
  dokumen huruf (setelah TLS/TDP) — perlu diputuskan.
- Koneksi real-time mesin fingerprint (SDK/ADMS) — ditunda ke v2, v1
  cuma berbasis file-export.

---

## DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Konteks lengkap audit ini ada di `BACKLOG.md`.

### 12.6 Recruitment — 4 item tambahan di luar yang sudah tercatat §8

- Kandidat WNA (bukan WNI) butuh kunci dedup alternatif (paspor, bukan
  KTP) — **belum didesain**.
- Kebijakan retensi/purge/anonimisasi data kandidat lama (kepatuhan PDP)
  — belum ada keputusan sama sekali.
- Publikasi ke Google Play ($25)/App Store ($99/th) + privacy policy —
  dicatat eksplisit sebagai tugas OPERASIONAL user, bukan sesuatu yang
  bisa dikerjakan Claude Code — jangan lupa saat rilis.

---
