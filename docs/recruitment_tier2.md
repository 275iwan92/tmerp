# TIER 2 — RECRUITMENT · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 2 — Recruitment · Business Logic & Rules |
| **Bergantung pada** | Tier 1 (entitas, alur, track) |
| **Prinsip desain kunci** | Approval chain & pipeline stage **HARUS data-driven** (tabel konfigurasi), bukan hardcode per track di kode — sejalan prinsip yang sama dipakai di `pyr_status_rule` (Attendance/Payroll). Alasan: track baru ("dan lain-lain") atau perubahan jenjang approval tidak boleh butuh Claude Code ubah kode. |

---

## SECTION 2 — APPROVAL MATRIX REQUISITION (data-driven: `rcr_approval_matrix`)

| track | approval_seq | approver_role |
|---|---|---|
| STAFF | 1 | Dept Head |
| STAFF | 2 | HR Manager |
| STAFF | 3 | Direksi |
| NON_STAFF | 1 | Dept Head |
| NON_STAFF | 2 | HR Manager |
| BORONGAN | 1 | Dept Head |

- `rcr_requisition` berjalan berurutan sesuai `approval_seq` — approver ke-2 hanya bisa bertindak setelah ke-1 approve.
- Reject di jenjang manapun = requisition berhenti (`status=REJECTED`), harus diajukan ulang (row baru), bukan diedit.
- Approver diselesaikan by **role**, bukan nama orang — sistem cari user aktif dengan `org_position` yang match role tsb di departemen pemohon (lihat `08_security` untuk resolusi role→user, di luar scope dokumen ini).

---

## SECTION 3 — PIPELINE SELEKSI (data-driven: `app_pipeline_stage_config`)

| track | stage_seq | stage_code | stage_name | mandatory |
|---|---|---|---|---|
| STAFF | 1 | SCREENING | Screening Administrasi | ya |
| STAFF | 2 | PSIKOTES | Psikotes | ya |
| STAFF | 3 | INTERVIEW_HR | Interview HR | ya |
| STAFF | 4 | INTERVIEW_USER | Interview User Dept | ya |
| STAFF | 5 | OFFERING | Offering | ya |
| NON_STAFF | 1 | SCREENING | Screening Administrasi | ya |
| NON_STAFF | 2 | INTERVIEW_HR | Interview HR | ya |
| NON_STAFF | 3 | INTERVIEW_USER | Interview User Dept | ya |
| NON_STAFF | 4 | OFFERING | Offering | ya |
| BORONGAN | 1 | SCREENING | Screening Administrasi | ya |
| BORONGAN | 2 | INTERVIEW_HR | Interview HR | ya |
| BORONGAN | 3 | OFFERING | Offering | ya |

- `app_application.current_stage_seq` menunjuk baris ini (via `track_id`+`stage_seq`) — **bukan** ENUM status bebas, supaya pipeline benar-benar mengikuti config, bukan asumsi kode.
- Naik ke stage berikutnya = keputusan manual HR Admin (tombol "Lanjutkan ke stage berikut"), bukan otomatis dari hasil assessment (assessment cuma data pendukung keputusan, lihat §4).
- **Reject bisa terjadi di stage manapun** — set `app_application.status=REJECTED`, `current_stage_seq` **tidak berubah** (jejak audit: reject terjadi di stage mana), `reject_reason` wajib diisi.
- **Withdraw** (kandidat mundur sendiri, misal sudah dapat kerja lain) — status terpisah `WITHDRAWN`, beda dari `REJECTED` (untuk analitik: withdraw ≠ tidak lolos kualifikasi).

---

## SECTION 4 — HASIL ASSESSMENT (psikotes & interview) — WAJIB terstruktur

- Tabel `app_assessment_result`: 1 baris per (application × stage_code × assessor).
- Field wajib: `score` (numeric, skala ditentukan per jenis assessment — lihat catatan di Tier 3 DDL), `recommendation` ENUM('LANJUT','TIDAK_LANJUT','PERTIMBANGAN'), `notes` (teks bebas).
- **Print-out** hasil psikotes/interview (untuk arsip fisik) adalah **output**, di-generate dari data ini (skill `pdf`/`docx` saat implementasi) — bukan sumber data. Upload scan hasil psikotes vendor eksternal (kalau ada) disimpan terpisah sebagai lampiran (`app_document`), tidak menggantikan kolom `score`/`recommendation` yang harus tetap diinput manual terstruktur.
- Interview User Dept & Interview HR memakai tabel yang sama, dibedakan `stage_code`.

---

## SECTION 5 — OFFERING & APPROVAL OFFER (data-driven: `rcr_offer_approval_matrix`)

Terpisah dari approval requisition (§2) karena titik keputusannya beda (komitmen gaji real, bukan sekadar kebutuhan headcount):

| track | approval_seq | approver_role |
|---|---|---|
| STAFF | 1 | HR Manager |
| STAFF | 2 | Direksi |
| NON_STAFF | 1 | HR Manager |
| BORONGAN | 1 | HR Manager |

- `app_offer.status`: DRAFT → PENDING_APPROVAL → APPROVED → SENT → ACCEPTED/DECLINED/EXPIRED.
- Kandidat bisa **counter/negosiasi** — dicatat sebagai `app_offer` baru (revisi, bukan edit in-place, konsisten dengan pola REVISE/copy-record global) yang mereferensi `app_offer` sebelumnya via `previous_offer_id`.
- `ACCEPTED` = syarat mutlak sebelum `emp_contract` (PKWT/PKWTT) diterbitkan.

---

## SECTION 6 — DEDUPLIKASI KANDIDAT (talent pool)

- Key unik: **nomor KTP** (`cnd_candidate.national_id`, `UNIQUE`). Kandidat WNA pakai `passport_no` sebagai fallback key (lihat Tier 1 §4 Open Item #5 — belum final).
- Saat sync worker menerima submission baru dari portal: cari `cnd_candidate` existing by KTP.
  - Ketemu → **update** data (kandidat mengisi ulang profil di portal, data terbaru menang) + **insert** `app_application` baru untuk vacancy yang dilamar (tidak menyentuh application lama).
  - Tidak ketemu → **insert** `cnd_candidate` baru + `app_application` baru.
- Kandidat yang pernah `REJECTED`/`WITHDRAWN` **tidak dihapus dan tidak diberi flag "blacklist"** otomatis — tetap tampil di pencarian talent pool HR, bisa diundang manual ke vacancy lain kapan saja.
- Keluarga (`cnd_family_member`) & pendidikan/karir kandidat **hanya menyimpan current job/status**, bukan histori (keputusan Telma) — tiap sync, baris existing di-**overwrite**, bukan accumulate versi.

---

## SECTION 7 — TRIGGER AUTO-PROVISIONING (saat HIRED)

Dijalankan sebagai **job terpisah** (bukan inline di transaksi update status), supaya gagal-partial tidak mengunci status:

1. Prasyarat: `app_offer.status=ACCEPTED` DAN `emp_contract` (PKWT/PKWTT) sudah diterbitkan HR di layar offering (Tier 4 L9).
2. HR klik tombol eksplisit "Provision ke ERP" (bukan otomatis tanpa aksi manusia — supaya HR sempat cek ulang sebelum akun dibuat) → set `app_application.status=HIRED`.
3. Job baca `cnd_candidate` + `app_application` + `vac_vacancy` (posisi/departemen tujuan) → insert `tm2hrm.emp_employee` baru (mapping field: nama, tanggal lahir, alamat, dari `cnd_candidate`; posisi/departemen dari `vac_vacancy`; `employment_type` dari `emp_contract` yang baru dibuat).
4. Insert `tm2hrm.emp_contract` (kalau belum ada dari langkah manual sebelumnya) dengan field dari `app_offer` (gaji, tanggal mulai, jenis kontrak).
5. Insert `tm2sys.sec_user`: `default_password` = generate random readable (pola sama dengan `security_defaultpassword_spec.md`), `must_change_password=1`, `employee_id` FK ke baris #3.
6. `app_application.provisioned_at`, `provisioned_employee_id` diisi — idempotency guard: kalau job dipanggil ulang untuk application yang sudah `provisioned_employee_id` NOT NULL, **skip** (tidak dobel insert employee).
7. Dokumen kandidat (`cnd_document`: CV, ijazah, sertifikat) **disalin referensinya** ke `tm2hrm.emp_document` (bukan dipindah/dihapus dari sisi recruitment — arsip proses rekrutmen tetap utuh untuk audit).

---

## SECTION 8 — PUSH NOTIFICATION (wajib rilis pertama, keputusan Telma)

**Prinsip:** push notification **independen dari pilihan backend data** (Firebase vs MariaDB, Tier 3 §1). Firebase Cloud Messaging (FCM) dipakai untuk push terlepas Plan A/B mana yang jalan untuk data — FCM hanya butuh device token, bukan seluruh data project di Firebase.

### 8.1 Titik trigger (event → notifikasi)

| Event (`app_application.status`/aksi) | Judul notifikasi | Isi ringkas |
|---|---|---|
| Application masuk (`APPLIED`) — konfirmasi sync sukses | Lamaran Diterima | "Lamaran Anda untuk {posisi} sudah kami terima." |
| Naik stage (`INTERVIEW_HR`/`INTERVIEW_USER` dijadwalkan) | Jadwal Interview | "Anda dijadwalkan interview {stage_name} pada {tanggal/jam}." |
| `REJECTED` (stage manapun) | Update Lamaran | "Terima kasih atas partisipasi Anda. Saat ini kami belum dapat melanjutkan ke tahap berikutnya." (nada netral, tidak menyebut alasan detail — alasan internal tidak diekspos ke kandidat) |
| `OFFERING` (offer APPROVED, siap dikirim) | Penawaran Kerja | "Anda menerima penawaran untuk posisi {posisi}. Silakan buka aplikasi untuk detail." |
| `HIRED` (provisioning selesai) | Selamat Bergabung | "Selamat! Proses administrasi Anda sudah selesai. Info akun ERP akan diinfokan HR." |

- Isi lengkap (gaji, jadwal detail, dsb) **tidak** ditaruh di body notifikasi (privasi lock-screen) — notifikasi hanya trigger buka app, detail dilihat setelah login (biometric/PIN).
- Notifikasi **ditulis Bahasa Indonesia**, nada profesional-hangat, konsisten dengan gaya komunikasi HR perusahaan.

### 8.2 Mekanisme pengiriman

1. Kandidat login pertama kali di app Flutter → app minta izin notifikasi (Android 13+/iOS wajib eksplisit) → device token FCM didaftarkan, disimpan di `cnd_device_token` (Tier 3 §8, DDL baru).
2. Saat `pushStatusUpdate()` dipanggil oleh sync worker internal (Tier 3 §5.3) → selain update status di portal (Firestore/`crp_submission`), job **terpisah** kirim FCM message ke seluruh `cnd_device_token` aktif milik kandidat tsb (bisa multi-device: HP + web).
3. **Gagal kirim FCM tidak boleh memblokir update status** — status tetap ter-update di portal, kandidat tetap bisa lihat manual di app kalau notifikasi gagal terkirim (token expired/uninstall). Retry 1x otomatis, setelah itu diamkan (token invalid ditandai `is_active=0`).
4. Token yang gagal terus-menerus (>5x error `NotRegistered`/`InvalidRegistration` dari FCM) → `cnd_device_token.is_active=0`, tidak dicoba lagi sampai kandidat login ulang (re-register token baru).

### 8.3 Yang TIDAK dicakup rilis pertama (boleh menyusul)

- Notifikasi in-app history/inbox (list semua notifikasi pernah dikirim) — cukup native push dulu.
- Rich notification (gambar/tombol aksi langsung dari notifikasi) — cukup teks polos + buka app.
