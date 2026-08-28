# TIER 1 — RECRUITMENT · SYSTEM FLOW & PROCESS MATRIX
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 1 — Recruitment · System Flow & Process |
| **Versi** | 1.0 |
| **Status scope** | 🟡 DIBUKA (DEC-SCOPE-OPEN 2026-08-26) — boleh dikerjakan sekarang |
| **Pembaca** | HR Admin/Recruiter · Dept Head · HR Manager · Direksi · Sistem Analis |
| **Aktor** | Kandidat (eksternal, via portal) · Dept Head (pemohon+approval) · HR Manager · Direksi · HR Admin/Recruiter · User Dept (interviewer teknis) |
| **Goal** | Sistem rekrutmen end-to-end: permohonan tenaga kerja → posting lowongan → kandidat apply via portal terpisah → seleksi berjenjang (screening/psikotes/interview) → offering → PKWT/PKWTT → **auto-provisioning login ERP** saat diterima |
| **DB terlibat** | `tm2hrr` (internal, transaksi rekrutmen) · **portal eksternal terpisah** (Firebase — Plan A, atau `tm2crp` MariaDB — Plan B, lihat Tier 3 §1) · `tm2hrm` (tujuan akhir: emp_employee/emp_contract) · `tm2sys` (tujuan akhir: sec_user) |
| **Stack portal mobile** | **Flutter** — 1 codebase untuk Android + iOS + Web, dipilih supaya web+mobile dibangun dari 1 basis kode (selaras alasan pemilihan Firebase, Tier 3 §8). **Push notification WAJIB rilis pertama** (Firebase Cloud Messaging), lihat Tier 3 §8 & Tier 2 §8 |
| **Prasyarat** | Master Employee (`tm2hrm.emp_employee`, `org_position`, `org_department`) sudah live — dipakai sebagai tujuan hire dan referensi approval role |
| **Output** | Kandidat baru jadi `emp_employee` + `sec_user` aktif tanpa input ulang manual oleh HR |

---

## SECTION 2 — SCOPE & RUANG LINGKUP

### 2.1 Prinsip pemisahan entitas (wajib, jangan disederhanakan)

Kesalahan umum di ERP rekrutmen yang buruk adalah menyamakan 4 hal berikut. TMERP memisahkannya tegas:

| Entitas | Arti | Sifat |
|---|---|---|
| **Requisition** (`rcr_requisition`) | Permohonan kebutuhan tenaga kerja | 1 requisition = 1 kebutuhan, punya approval chain |
| **Vacancy** (`vac_vacancy`) | Posting/iklan lowongan | 1 requisition bisa punya **banyak** vacancy (beda channel/gelombang) |
| **Candidate** (`cnd_candidate`) | Data orang pelamar | **Persisten** lintas waktu — talent pool, bukan sekali pakai |
| **Application** (`app_application`) | Lamaran = candidate × vacancy | State machine (pipeline stage) nempel **di sini**, bukan di candidate |

Konsekuensi: 1 kandidat bisa punya banyak `app_application` (lamaran ke lowongan berbeda, di waktu berbeda), tanpa isi ulang data diri.

### 2.2 Fitur di dalam scope

| # | Fitur | Ringkas |
|---|---|---|
| F1 | Permohonan tenaga kerja + approval berjenjang | Per **track** (STAFF/NON_STAFF/BORONGAN/dst — data-driven, lihat §2.4) |
| F2 | Posting lowongan + monitoring channel iklan | 1 requisition → N vacancy/channel; hitung perolehan pelamar per channel |
| F3 | Portal kandidat (Flutter — web + Android + iOS, host terpisah) | Registrasi, isi data diri+keluarga+pendidikan+karir, upload dokumen, apply ke lowongan, cek status |
| F3a | Push notification (wajib rilis pertama) | Firebase Cloud Messaging — notifikasi realtime saat status lamaran berubah (Tier 2 §8), tidak menunggu kandidat buka app manual |
| F4 | Sinkronisasi portal → internal | Pull-based, idempotent (lihat Tier 3) |
| F5 | Pipeline seleksi berjenjang, data-driven per track | Screening → (Psikotes) → Interview HR → (Interview User Dept) → Offering → Hired |
| F6 | Input hasil psikotes & interview (terstruktur) | Skor + catatan + rekomendasi, bukan sekadar lampiran PDF scan |
| F7 | Rejection/withdraw handling | Dengan alasan; kandidat reject **tidak dihapus**, masuk talent pool |
| F8 | Talent pool reuse | Kandidat reject bisa diundang lagi ke vacancy lain tanpa isi ulang form |
| F9 | Offering letter + approval | Approval matrix sendiri (bisa beda dari approval requisition) |
| F10 | Penerbitan PKWT/PKWTT | Menghasilkan `emp_contract` (tm2hrm) |
| F11 | Auto-provisioning login ERP | Trigger otomatis saat `app_application.status = HIRED` |

### 2.3 Di luar scope (modul lain)
- Master data employee/kontrak existing → **Master Employee (tm2hrm)**, hanya dikonsumsi (insert) saat hire.
- Payroll/gaji → **tm2pyr**, di luar dokumen ini.
- Struktur approval role/permission RBAC detail → **08_security**, dokumen ini hanya menyebut nama role.
- Desain koleksi Firebase/DDL MariaDB detail → **Tier 3**, bukan di sini.

### 2.4 Recruitment Track (pengganti hardcode "staff/non-staff/borongan")

**Keputusan kunci:** track **BUKAN** ENUM hardcode, melainkan tabel master `rcr_track` (code, name, is_active). Alasan: user menyebut "staff, non-staff, borongan **dan lain-lain**" — kebutuhan baru di masa depan (misal "magang", "expat") tidak boleh butuh ubah skema/kode, cukup tambah baris.

Seed awal:
| code | name |
|---|---|
| STAFF | Staff/Karyawan Tetap-Kontrak kantoran |
| NON_STAFF | Non-Staff (operator, teknisi, dll non-kantoran) |
| BORONGAN | Tenaga Borongan/Harian |

`rcr_track` menentukan **approval chain requisition** (Tier 2 §1) dan **kedalaman pipeline seleksi** (Tier 2 §2) — keduanya data-driven, tidak hardcode di kode aplikasi.

> **Catatan penting:** `rcr_track` (klasifikasi rekrutmen) **berbeda axis** dari `emp_contract.employment_type` (TETAP/KONTRAK/HARIAN/BORONGAN, sudah ada di HRM). Track menentukan proses seleksi; employment_type menentukan jenis kontrak saat hire. Satu requisition BORONGAN kemungkinan besar berujung `employment_type=BORONGAN`, tapi field ini tetap independen dan bisa diubah HR di layar offering (Tier 4 L9).

---

## SECTION 3 — ALUR PROSES & ALUR INFORMASI

### 3.1 Alur end-to-end

```
[Dept Head] ajukan kebutuhan
      │
      ▼
rcr_requisition (track_id, jumlah, kualifikasi, alasan)
      │
      ▼
[APPROVAL BERJENJANG per track — Tier 2 §1]
      │ (approved)
      ▼
vac_vacancy (1..N per requisition, per channel: portal sendiri/Jobstreet/referral/dst)
      │
      ▼
========== HOST TERPISAH: PORTAL KANDIDAT (web+mobile) ==========
Kandidat registrasi/login (persisten, dedup by KTP saat sync)
      │
      ▼
Isi form: data diri, keluarga (current job saja), pendidikan, karir, upload dokumen
      │
      ▼
Submit lamaran ke 1+ vacancy → tersimpan di portal (status: pending_sync)
==================================================================
      │
      ▼  [SYNC WORKER — internal PULL, lihat Tier 3 §5]
      │
cnd_candidate (upsert by KTP) + cnd_family_member + cnd_education +
cnd_work_experience + cnd_document + app_application (baru, status=APPLIED)
      │
      ▼
[PIPELINE SELEKSI — data-driven per track, Tier 2 §2]
SCREENING → (PSIKOTES) → INTERVIEW_HR → (INTERVIEW_USER) → OFFERING
      │                                                         │
      ├── REJECT (kapan saja) ──► app_application.status=REJECTED,
      │                            cnd_candidate TETAP aktif (talent pool)
      │
      ▼
app_offer (approval sendiri, Tier 2 §4) → ACCEPTED
      │
      ▼
emp_contract (PKWT/PKWTT) dibuat di tm2hrm
      │
      ▼
app_application.status = HIRED
      │
      ▼ [TRIGGER OTOMATIS — Tier 2 §5]
tm2hrm.emp_employee (baru) + tm2sys.sec_user (baru, default_password + must_change_password=1)
```

### 3.2 Siapa approve apa

| Aksi | Diajukan oleh | Disetujui oleh |
|---|---|---|
| Requisition | Dept Head | Berjenjang per track (Tier 2 §1) |
| Vacancy posting | HR Admin/Recruiter | Tidak perlu approval terpisah (mengikuti requisition yang sudah approved) |
| Lamaran kandidat | Kandidat (via portal) | — (masuk otomatis ke pipeline) |
| Lolos/gagal tiap stage pipeline | HR Admin/User Dept (sesuai stage) | Rekomendasi individual per stage, keputusan lanjut/reject oleh HR Admin |
| Offering | HR Admin/Recruiter (usulkan) | Berjenjang per track (Tier 2 §4), beda dari approval requisition |
| Hire trigger | Sistem (otomatis begitu offer accepted + kontrak terbit) | — |

---

## SECTION 4 — OPEN ITEMS (asumsi diambil, boleh direvisi kapan saja)

1. **Approval chain requisition & offering** (Tier 2 §1, §4) — asumsi 3/2/1 jenjang per track (STAFF/NON_STAFF/BORONGAN). Perlu konfirmasi nama jabatan riil yang mengisi role "HR Manager"/"Direksi" di struktur `org_position` TMERP saat ini.
2. **Pipeline stage default per track** (Tier 2 §2) — asumsi psikotes hanya untuk STAFF. Boleh direvisi lewat data (`app_pipeline_stage_config`), tanpa ubah kode.
3. **Backend portal** — Plan A (Firebase) diasumsikan disetujui; Plan B (MariaDB `tm2crp`) disiapkan paralel via adapter pattern (Tier 3 §1) sehingga keputusan bisa ditunda tanpa mengubah desain internal `tm2hrr`.
4. **Retensi data kandidat non-aktif lama** (mis. tidak pernah dihubungi lagi 3+ tahun) — belum ada kebijakan purge/anonymize untuk kepatuhan UU PDP. Dicatat sebagai Open Item, bukan diputuskan sepihak di sini.
5. **Nomor KTP sebagai dedupe key** (Tier 2 §3) — asumsi setiap kandidat WNI dan punya KTP. Kandidat WNA (kalau ada) perlu key alternatif (paspor) — belum didesain, ditandai Open Item.
