# TIER 1 — PRESENSI & PAYROLL · SYSTEM FLOW & PROCESS MATRIX
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 1 — Presensi & Payroll · System Flow & Process |
| **Versi** | 1.0 |
| **Sumber** | `PRESENSI___PAYROLL_-_RULES___POLICY.pptx` (SOP manual berbasis Google Sheets, existing) |
| **Status scope** | ⚪ **DI LUAR jalur kerja aktif 10 minggu** (lihat GLOBAL_START_HERE.md / TIER_0 §2, §6) — dokumen ini disiapkan sebagai arsip keputusan arsitektur, BUKAN sinyal HR module dibuka sekarang |
| **Pembaca** | HR Admin · Payroll Officer · Kepala Produksi/SPV (approval lembur & izin) · Sistem Analis |
| **Aktor** | Karyawan · Atasan Langsung (approval) · HR Admin · Payroll Officer |
| **Goal** | Menggantikan SOP manual Excel/Google Sheets (ambil flashdisk → convert manual → cek manual → crosscheck manual → rekap manual) dengan sistem yang **mengeliminasi seluruh langkah manual yang sifatnya workaround keterbatasan spreadsheet**, dan menyisakan hanya approval oleh manusia yang memang butuh judgment (izin, cuti, lembur) |
| **DB terlibat** | `tm2hrm` (presensi — menempel ke `emp_employee`/`emp_shift` yang sudah dirancang), `tm2pyr` (payroll run & payslip) |
| **Prasyarat** | Modul HRM/Master Employee (emp_employee, emp_shift per-hari, emp_contract) — lihat memori arsitektur, harus selesai duluan karena presensi bergantung pada shift aktual per pegawai |
| **Output** | Payslip per periode + rekap potongan/tunjangan per status kehadiran |

---

## SECTION 2 — SCOPE & RUANG LINGKUP

### 2.1 Modul yang dicakup
Dua sub-modul menyatu: **Presensi** (kalkulasi status kehadiran harian dari data fingerprint + shift) dan **Payroll** (kalkulasi komponen gaji berdasar status presensi + rule matrix).

### 2.2 Fitur di dalam scope

| # | Fitur | Sumber (slide) | Ringkas |
|---|---|---|---|
| F1 | Import data finger | 21 | Upload file mesin fingerprint (format TBD — lihat §7 Open Item) |
| F2 | Kalkulasi status presensi otomatis | 2–5, 10–16 | T-5/T+5/>15mnt, PA, PAT-5/+5, Keluar-jam-kerja, Lembur Awal/Akhir — dihitung dari selisih jam finger vs `emp_shift` aktif, TANPA convert manual |
| F3 | Rule matrix status → komponen gaji | 7–8 | Data-driven, bukan hardcode (lihat Tier 2 §4) |
| F4 | Pengajuan Izin/Izin Disetujui/Cuti/Cuti Melahirkan/Sakit | 6, 17 | Form + approval atasan langsung, lead-time berbeda per jenis |
| F5 | Pengajuan & approval Lembur | 5 | Form lembur, di-crosscheck otomatis ke data finger saat kalkulasi (bukan visual manual) |
| F6 | Toleransi Tidak Finger (F/FF) | 6 | Counter 1x/2x per periode, potongan Rp 10.000/event |
| F7 | Surat Peringatan (SP1/SP2/SP3) & Skorsing (SKR) | 18 | Status administratif, memicu potongan gaji harian berturut-turut |
| F8 | Dirumahkan (DRM) | 6 | Status kepegawaian non-aktif sementara, tetap dapat gaji pokok |
| F9 | Payroll run (batch) | 19–21 | Job terjadwal/manual trigger per periode, hasilkan payslip |
| F10 | Uang Roti (lembur produksi) | 5 | Rp 5.000 untuk Staff & SPV Produksi jika lembur >2 jam, tidak berlaku kelipatan — role-specific rule |

### 2.3 Di luar scope (dokumen lain / modul lain)
- Master data karyawan, shift, kontrak kerja → modul **HRM/Master Employee** (sudah dirancang terpisah)
- Perhitungan pajak PPh 21 → **tidak disebut di sumber**, jadi di luar scope dokumen ini, ditandai Open Item
- BPJS/asuransi → tidak disebut di sumber, Open Item
- Slip gaji cetak/PDF → Tier 4, bukan Tier 1

### 2.4 Klasifikasi Status Presensi (kanonik — dipakai lintas Tier 2/3/4)

| Kode | Nama | Jenis | Definisi |
|---|---|---|---|
| H | Hadir | Normal | Tidak telat/pulang awal, durasi kerja >3 jam |
| T-5 | Terlambat <5 menit | Time-variant | Finger-in > jam masuk shift, selisih <5 menit |
| T+5 | Terlambat >5 menit | Time-variant | Selisih 6–15 menit |
| T+15 | Terlambat >15 menit | Time-variant | Selisih >15 menit |
| PA | Pulang Awal | Time-variant | Finger-out < jam pulang shift |
| PAT-5 | Pulang Awal & Terlambat <5mnt | Time-variant | Gabungan PA + T-5 hari yang sama |
| PAT+5 | Pulang Awal & Terlambat >5mnt | Time-variant | Gabungan PA + T+5 hari yang sama |
| KJK | Keluar pada Jam Kerja | Time-variant | Keluar-masuk di tengah shift (bukan awal/akhir) |
| LAw | Lembur Awal | Time-variant, additive | Kerja sebelum jam masuk shift, disetujui |
| LAk | Lembur Akhir | Time-variant, additive | Kerja setelah jam pulang shift, disetujui |
| I | Izin | Non-hadir | Pengajuan mendadak (<H-3) |
| IS | Izin Disetujui | Non-hadir | Pengajuan min. H-3 |
| C | Cuti | Non-hadir | Approved atasan, wajib diajukan H-7 |
| CL | Cuti Melahirkan | Non-hadir | Maks 2 bulan dibayar penuh, ATAU 3 bulan (2 dibayar + 1 tidak dibayar) — pilihan karyawan, lihat Tier 2 §5 |
| S | Sakit | Non-hadir | Wajib surat dokter + kwitansi/surat obat |
| A | Alpha | Non-hadir | Tanpa izin |
| F | Tidak Finger 1x | Administratif | Per event dalam periode |
| FF | Tidak Finger 2x | Administratif | Akumulasi 2x dalam periode |
| SKR | Skorsing | Administratif | Penalty, semua komponen gaji hilang |
| DRM | Dirumahkan | Kepegawaian | Non-aktif sementara, gaji pokok tetap jalan |
| SP1 | Surat Peringatan 1 | Administratif | Potongan 50% gaji harian × 3 hari berturut |
| SP2 | Surat Peringatan 2 | Administratif | Potongan 50% gaji harian × 7 hari berturut |
| SP3 | Surat Peringatan 3 | Administratif | Skorsing 7 hari / PHK |

> **Catatan desain:** status Time-variant (T-5, PA, dst.) dan status Non-hadir/Administratif **tidak eksklusif satu sama lain di level record** — misal SP1 bisa aktif bersamaan dengan H di hari yang sama (SP1 = status administratif berjalan yang memengaruhi %, sedangkan H tetap status presensi harian). Ini beda axis: `attendance_daily_status` (harian, dari jam finger) vs `emp_disciplinary_status` (periode, dari SP/skorsing). Lihat Tier 3.

---

## SECTION 3 — ALUR PROSES & ALUR INFORMASI

### 3.1 Alur data (menggantikan Slide 20, versi tersistem)

```
emp_employee ──┬──► emp_shift (per-hari, sudah ada di HRM)
               │
att_finger_import (upload harian/periodik)
               │
               ▼
      [JOB: kalkulasi presensi harian]
      match finger vs shift aktif pegawai
      → hitung status time-variant (T-5/T+5/PA/dst)
               │
               ├──► att_daily_status (hasil per pegawai per tanggal)
               │
               ├──◄── emp_leave_request (Izin/IS/Cuti/CL/Sakit, approved)
               ├──◄── emp_overtime_request (Lembur, approved)
               │
               ▼
      [JOB: payroll run per periode]
      baca att_daily_status × pyr_status_rule (matrix)
      + emp_disciplinary_status (SP/Skorsing aktif)
               │
               ▼
      pyr_payslip (hasil per pegawai per periode)
```

### 3.2 Perbandingan proses lama vs baru

| Step manual (Slide 21) | Sistem baru | Alasan |
|---|---|---|
| Ambil data finger via flashdisk | Upload file di `att_finger_import` | Tetap manual ambil dari mesin (hardware constraint), tapi input ke sistem via upload bukan retype |
| Convert manual jam ke 07:30 | **Dihapus** — lookup otomatis ke `emp_shift` per pegawai per hari | Ini murni workaround Sheets, tidak relevan lagi |
| Cek manual keterlambatan | Job otomatis baca `pyr_status_rule` | Konsisten, tidak bergantung ketelitian staff |
| Crosscheck form lembur vs data finger | Approval lembur terstruktur duluan (`emp_overtime_request`), job tinggal validasi jam vs pengajuan | Data lembur sudah terstruktur sejak pengajuan, bukan direkonsiliasi belakangan |
| Crosscheck izin/alpha/sakit manual | `emp_leave_request` terstruktur sejak pengajuan | idem |
| Rekap manual pendapatan & potongan | `pyr_payslip` hasil job payroll run | Batch job, bukan rekap manual di akhir |

### 3.3 Siapa approve apa

| Aksi | Diajukan oleh | Disetujui oleh |
|---|---|---|
| Izin (I) | Karyawan | Atasan langsung (setelah kejadian/mendadak) |
| Izin Disetujui (IS) | Karyawan, min H-3 | Atasan langsung, sebelum tanggal |
| Cuti (C) | Karyawan, min H-7 | Atasan langsung |
| Cuti Melahirkan (CL) | Karyawan | HR Admin (verifikasi + pilih skema 2 bulan/3 bulan) |
| Sakit (S) | Karyawan + lampiran surat dokter | HR Admin (verifikasi dokumen) |
| Lembur (LAw/LAk) | Karyawan/SPV | Atasan langsung |
| SP1/SP2/SP3 | HR Admin (input administratif) | — (keputusan sudah final di luar sistem) |
| Payroll run | Payroll Officer (trigger) | — (job otomatis, hasil bisa direview sebelum finalize) |

---

## SECTION 4 — OPEN ITEMS (wajib konfirmasi sebelum Tier 2/3 dikunci)

1. **[DELTA-CONFIRM]** Kontradiksi aturan denda T+5 (lihat catatan pembuka chat) — pakai versi "toleransi 3x/periode" atau "denda dari pelanggaran pertama"? Tier 2 saya tulis dengan asumsi 3x/periode.
2. **Periode toleransi 3x** itu per apa — per bulan (siklus payroll) atau per minggu? Diasumsikan **per periode payroll (bulanan)**.
3. Format file export mesin fingerprint (brand/model?) — menentukan parser import.
4. PPh 21 & BPJS tidak disebut di sumber — exclude total dari scope payroll ini, atau nanti nyusul di modul Finance?
5. Slide 8 baris "Terlambat +5mnt" dan "Terlambat -5mnt" punya kolom identik (bonus hadir ✔ keduanya) — kemungkinan salah ketik di slide asli (mestinya salah satu ✘). Perlu konfirmasi manual sebelum jadi rule permanen.
