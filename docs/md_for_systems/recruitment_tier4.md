# TIER 4 — RECRUITMENT · UI/UX & INTERACTION DESIGN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## 1. PETA LAYAR

### 1.1 Portal Kandidat (Flutter — Android + iOS + Web, host terpisah — Firebase/tm2crp)

| # | Layar | Aktor | Platform | Push notification |
|---|---|---|---|---|
| P1 | Registrasi/Login | Kandidat | Flutter (Android/iOS/Web) | Minta izin notifikasi + register FCM token (Tier 3 §6.3) segera setelah login pertama |
| P2 | Daftar Lowongan Tersedia | Kandidat | Flutter (Android/iOS/Web) | — |
| P3 | Form Apply — multi-step (data diri → keluarga → pendidikan → karir → upload dokumen → review → submit) | Kandidat | Flutter (Android/iOS/Web) | Notif "Lamaran Diterima" setelah submit sukses (Tier 2 §8.1) |
| P4 | Riwayat Lamaran & Status | Kandidat | Flutter (Android/iOS/Web) | Tampilan status di-refresh otomatis saat notif diterima (bukan cuma pull-to-refresh manual) |

### 1.2 Internal HR (Livewire, `tm2hrr`)

| # | Layar | Aktor | Livewire component (usulan) |
|---|---|---|---|
| L1 | Permohonan Tenaga Kerja + Approval | Dept Head, HR Manager, Direksi | `RequisitionForm` |
| L2 | Posting Lowongan & Monitoring Channel | HR Admin/Recruiter | `VacancyGrid` |
| L3 | Talent Pool (cari kandidat existing) | HR Admin/Recruiter | `CandidatePoolSearch` |
| L4 | Pipeline Board (kanban per stage) | HR Admin/Recruiter | `ApplicationPipelineBoard` |
| L5 | Jadwal Interview | HR Admin, User Dept | `InterviewScheduler` |
| L6 | Input Hasil Psikotes/Interview | HR Admin, User Dept | `AssessmentResultForm` |
| L7 | Offering + Approval | HR Admin, HR Manager, Direksi | `OfferForm` |
| L8 | Rekap Sync Log (troubleshooting) | HR Admin/IT | `SyncLogPanel` |
| L9 | Provisioning ke ERP (hire trigger) | HR Admin | `HireProvisionPanel` |

---

## 2. P1 — Registrasi/Login (Portal)

- Registrasi via email + password (Firebase Auth / `crp_candidate_account` tergantung backend aktif).
- Opsional verifikasi OTP HP.
- Setelah login: dashboard sederhana → "Lihat Lowongan" / "Lamaran Saya".
- **Persisten** — kandidat yang sudah pernah apply login pakai akun sama, tidak perlu isi ulang data diri (Tier 2 §6).

## 3. P2 — Daftar Lowongan

- List `vacancy_public` (mirror read-only dari `vac_vacancy` published) — judul, departemen, ringkasan kualifikasi.
- Klik lowongan → detail + tombol "Lamar".

## 4. P3 — Form Apply (multi-step, mobile-first)

Step disimpan progresif (autosave draft per step — bonus dari sifat realtime Firestore, atau autosave manual tiap "Lanjut" untuk Plan B):

1. **Data Diri** — nama, KTP, tanggal lahir, alamat, kontak.
2. **Data Keluarga** — tambah baris per anggota (ayah/ibu/mertua ayah/mertua ibu/pasangan/anak): nama + pekerjaan saat ini + tempat kerja saat ini (**bukan histori** — sesuai keputusan Telma).
3. **Pendidikan** — tambah baris per jenjang: level, institusi, jurusan, tahun lulus.
4. **Pengalaman Kerja** — tambah baris per perusahaan: nama perusahaan, posisi, periode, deskripsi singkat.
5. **Upload Dokumen** — CV (wajib), ijazah (wajib), sertifikat (opsional, multi-file), foto.
6. **Review & Submit** — ringkasan semua step, tombol "Kirim Lamaran" → tersimpan `submissions`/`crp_submission` (`sync_status=pending`).

- Validasi client-side per step sebelum "Lanjut" (bukan baru divalidasi di step terakhir — mengurangi frustrasi kandidat isi form panjang).
- File upload: preview nama file + ukuran, indikator progress upload (penting untuk mobile/koneksi tidak stabil).

## 5. P4 — Riwayat Lamaran

- List lamaran kandidat (lintas vacancy, lintas waktu) + status terkini (badge warna, mengikuti `app_application.status` yang di-push internal via `pushStatusUpdate`).
- Kandidat **tidak melihat** detail skor assessment (Sisi A vs Sisi B — hanya status kasar: Diproses/Interview/Offering/Diterima/Tidak Lolos).
- Tap notifikasi (FCM) langsung deep-link ke baris lamaran terkait di layar ini (bukan cuma buka halaman utama app).

## 5a. Perilaku Push Notification (lintas P1-P4)

- Foreground (app sedang dibuka) → tampil sebagai in-app banner singkat, bukan notifikasi sistem penuh (menghindari dobel dengan tampilan status yang sudah live di P4).
- Background/app tertutup → notifikasi sistem native Android/iOS standar, tap → buka app langsung ke P4 (deep link).
- Isi notifikasi **tidak** memuat detail sensitif di lock-screen (Tier 2 §8.1) — hanya judul umum + nama posisi, detail lengkap setelah buka app & login.

---

## 6. L1 — Permohonan Tenaga Kerja

- Form: track (dropdown `rcr_track`), departemen, posisi, headcount, kualifikasi, alasan.
- Submit → sistem otomatis buat baris `rcr_requisition_approval` sejumlah `rcr_approval_matrix` untuk track terpilih, `approval_seq=1` berstatus `PENDING`.
- Approver login lihat queue "Perlu Approval Saya" (filter by role + `approval_seq` aktif) → Approve/Reject (reject wajib alasan, requisition berhenti).
- Progress approval ditampilkan sebagai stepper visual (seq 1/2/3, siapa sudah approve).

## 7. L2 — Posting Lowongan & Monitoring Channel

- Dari requisition APPROVED → tombol "Buat Lowongan" → pilih channel(s), isi judul+deskripsi publik → publish.
- Grid monitoring: per vacancy, jumlah `app_application` masuk (badge count), breakdown per channel (analitik sumber pelamar — jawaban kebutuhan "monitoring perolehan kandidat" di flow awal).

## 8. L3 — Talent Pool

- Search kandidat existing (`cnd_candidate`) by nama/KTP/skill/pendidikan.
- Hasil search bisa langsung "Undang ke Lowongan" (buat `app_application` baru ke vacancy pilihan) — tanpa kandidat isi ulang form (Tier 2 §6, F8 Tier 1).
- Riwayat lamaran sebelumnya (semua `app_application` kandidat ini) ditampilkan di panel samping — transparansi kenapa kandidat ini pernah reject.

## 9. L4 — Pipeline Board

- Kanban per `stage_code` (kolom = stage aktif sesuai `app_pipeline_stage_config` milik track vacancy tsb — jumlah kolom **beda** antar track, bukan satu papan generik untuk semua).
- Kartu = 1 `app_application`, klik → detail kandidat + tombol "Lanjutkan ke Stage Berikutnya" / "Reject" (wajib alasan) / "Withdraw" (kandidat mundur).
- Drag-drop antar kolom **tidak diizinkan** sembarangan — hanya maju 1 stage per klik tombol eksplisit (mencegah salah drag melompati validasi).

## 10. L5 — Jadwal Interview

- Dari kartu pipeline stage `INTERVIEW_HR`/`INTERVIEW_USER` → jadwalkan (tanggal, interviewer, lokasi).
- Notifikasi ke interviewer (modul Notification `tm2sys`, pola sama dengan Attendance/Payroll).

## 11. L6 — Input Hasil Psikotes/Interview

- Form per stage: skor (numerik), rekomendasi (Lanjut/Tidak Lanjut/Pertimbangan), catatan.
- **Wajib diisi terstruktur sebelum bisa "Lanjutkan ke Stage Berikutnya"** di L4 — bukan opsional dokumentasi belakangan.
- Tombol cetak (skill `pdf`/`docx` saat implementasi) — output presentasi dari data ini, bukan input.

## 12. L7 — Offering + Approval

- Form: gaji diusulkan, jenis kontrak (TETAP/KONTRAK/HARIAN/BORONGAN — independen dari track), tanggal mulai.
- Submit → approval berjenjang sesuai `rcr_offer_approval_matrix` (mirip stepper L1).
- Setelah APPROVED → kirim ke kandidat (push status "Offering" ke portal).
- Kandidat accept/decline dari portal (P4) → status balik ke internal via sync berikutnya, atau HR input manual kalau kandidat konfirmasi lewat telepon/WA.
- Counter-offer → tombol "Revisi Offer" (buat `app_offer` baru, `previous_offer_id` terisi, riwayat lengkap terlihat).

## 13. L8 — Sync Log Panel

- List `rcr_sync_log` — filter by `result` (khususnya `FAILED`) untuk troubleshooting kalau ada submission macet.
- Tombol "Retry Manual" untuk submission `FAILED` tertentu (memicu ulang job untuk 1 submission spesifik).

## 14. L9 — Provisioning ke ERP

- Muncul setelah `app_offer.status=ACCEPTED` dan `emp_contract` sudah diinput HR.
- Tombol "Provision ke ERP" — modal konfirmasi tegas ("Akan membuat akun login baru untuk kandidat ini, tidak bisa dibatalkan") sebelum trigger job Tier 2 §7.
- Setelah sukses: tampilkan `default_password` yang di-generate (mengikuti `security_defaultpassword_spec.md`) untuk diinfokan HR ke karyawan baru secara manual.
- Jika gagal di tengah (idempotency guard aktif) — tombol berubah jadi "Retry Provisioning", aman dipanggil ulang tanpa dobel insert.

---

## 15. PRINSIP DESAIN LINTAS LAYAR

- Portal (P1–P4) **tidak pernah** menampilkan data internal sensitif (skor assessment, approval matrix, gaji) — hanya status kasar. Sisi A/B tetap berlaku walau host terpisah.
- Semua badge status pipeline pakai warna konsisten (mengikuti pola global: abu=belum mulai, kuning=proses, hijau=lolos/hired, merah=reject) selaras `module_status_system`/konvensi badge modul lain.
- L4 Pipeline Board TIDAK boleh drag-drop bebas antar kolom — hanya tombol aksi eksplisit, mencegah lompat validasi stage.
- L9 SELALU minta konfirmasi eksplisit sebelum provisioning — ini titik tanpa-balik (создание akun login), tidak boleh 1-klik tanpa modal.

---

## 16. HANDOFF KE DEVELOPER

- Prasyarat: `tm2hrm` (Master Employee) & `tm2sys` (`sec_user`, `security_defaultpassword_spec.md`) sudah live.
- **Jangan seed `rcr_approval_matrix`/`rcr_offer_approval_matrix`** sebelum nama role/jabatan riil dikonfirmasi Telma (Tier 1 Open Item #1).
- Pilih Plan A (Firebase) atau Plan B (`tm2crp`) sebelum mulai L1 portal — internal (`tm2hrr` + L1–L9) bisa dibangun paralel tanpa menunggu keputusan ini, karena adapter pattern mengisolasi keduanya (Tier 3 §1.1).
