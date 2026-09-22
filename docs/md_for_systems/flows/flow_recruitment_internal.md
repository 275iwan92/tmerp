---
name: flow_recruitment_internal
description: Alur rekrutmen internal (HR) di tm2hrr -- Requisition sampai Provisioning ke ERP, dengan Pipeline Board/Interview/Assessment sebagai inti seleksi kandidat.
---

# Alur Recruitment Internal (HR, `tm2hrr`)

**Modul**: Recruitment (Internal) — 9 layar L1-L9 (minus L8 dihitung tersendiri sbg sync-log), lihat `docs/recruitment_tier4.md`
**Koneksi DB utama**: `tm2hrr` (+ `tm2mst`/`tm2sys` lintas-DB di beberapa titik)
**Kanal masuk kandidat**: Portal Rekrutmen Profesional publik (lihat `docs/flows/flow_recruitment_portal_public.md`) via sync worker, ATAU "Undang" manual dari Talent Pool (L3)

## Ringkasan
Alur internal dimulai dari HR mengajukan kebutuhan tenaga kerja (Requisition), berlanjut ke publish lowongan, lalu kandidat masuk pipeline (dari sync portal publik ATAU diundang manual dari talent pool existing), diseleksi lewat Pipeline Board dengan gate assessment terstruktur (psikotes/interview), ditawari (Offering dengan approval berjenjang), dan akhirnya diprovision jadi karyawan resmi di ERP (`tm2mst.emp_employee` + akun login). Approval berjenjang (Requisition & Offer) memakai `RecruitmentApprovalService` bespoke — role String via `rcr_approver_assignment`, BUKAN `org_position`/`FlowEngineService` yang dipakai modul lain.

## Tahap -> Layar -> Aktor

| # | Tahap | Layar | Route | Aktor |
|---|---|---|---|---|
| 1 | Ajukan kebutuhan tenaga kerja | `requisition/list.blade.php` (grid, buat baru) + `requisition/entry.blade.php` (isi & submit) | `hrm.recruitment.requisition.list`, `hrm.recruitment.requisition.entry` | HR staff (buat & isi), Approver (approve/reject di Entry) |
| 2 | Publish lowongan dari Requisition APPROVED | `vacancy.blade.php` | `hrm.recruitment.vacancy` | HR staff |
| 3a | Kandidat masuk via Portal Publik | (di luar internal, lihat `flow_recruitment_portal_public.md`) → `PortalSyncService` pull tiap 2 menit | — | Kandidat (portal) + Sistem (sync worker) |
| 3b | Kandidat masuk via undang manual dari Talent Pool | `talent-pool.blade.php` | `hrm.recruitment.talent-pool` | HR staff |
| 3c | Troubleshooting hasil sync | `sync-log.blade.php` (read-only) | `hrm.recruitment.sync-log` | HR staff |
| 4 | Seleksi bertahap (kanban per stage) | `pipeline-board.blade.php` | `hrm.recruitment.pipeline-board` | HR staff / hiring manager |
| 5 | Jadwalkan interview | `interview-schedule.blade.php` (kalender drag-drop) | `hrm.recruitment.interview-schedule` | HR staff |
| 6 | Input hasil psikotes/interview | `assessment-result.blade.php` | `hrm.recruitment.assessment-result` | Assessor (HR/user department/interviewer) |
| 7 | Buat & approve penawaran | `offer/list.blade.php` (grid) + `offer/entry.blade.php` (isi, approval, respons kandidat) | `hrm.recruitment.offer.list`, `hrm.recruitment.offer.entry` | HR staff (isi & catat respons), Approver (approve/reject) |
| 8 | Provisioning ke ERP | `hire-provision.blade.php` | `hrm.recruitment.hire-provision` | HR staff |

## Alur Detail

1. **Requisition (L1)**: HR klik "+ New Record" di grid `requisition/list.blade.php` — dokumen `DRAFT` langsung lahir dengan `requisition_no` auto-generate (`RCR{YY}{seq}`), lalu redirect ke `requisition/entry.blade.php` untuk diisi (track, departemen, posisi, headcount, kualifikasi, alasan). Submit memicu `RecruitmentApprovalService::submitRequisition()` — baris approval dibuat dari `rcr_approval_matrix` sesuai track, status → `SUBMITTED`. Approver (resolve via `rcr_approver_assignment`, department-specific menang atas company-wide) memutuskan approve/reject langsung di layar Entry yang sama (bukan layar terpisah). Semua approval seq APPROVED berurutan → status `APPROVED`; sekali `REJECTED` di seq manapun → langsung `REJECTED`, skip sisa seq.

2. **Publish Vacancy (L2)**: HR pilih requisition `APPROVED` di modal `vacancy.blade.php`, isi channel + judul + deskripsi, klik Publish — 1 aksi langsung membuat `VacVacancy` dengan `is_published=true` (tidak ada state draft-lalu-publish terpisah lewat UI ini). `vacancy_code` auto-generate (`VAC{YY}{seq}`).

3. **Kandidat masuk pipeline** — 2 jalur:
   - **3a (utama)**: kandidat apply lewat Portal Rekrutmen Profesional publik (`careers.apply`), data tersimpan di Firebase/Firestore, ditarik ke `tm2hrr` (`AppApplication` + `CndCandidate`) oleh `PortalSyncService` (command `recruitment:sync-portal`, jadwal tiap 2 menit). Hasil tiap pull (OK/ERROR) dicatat di `rcr_sync_log`, bisa dicek di `sync-log.blade.php` (L8, murni troubleshooting read-only).
   - **3b (alternatif)**: HR cari kandidat lama di `talent-pool.blade.php` (L3, semua yang pernah melamar) dan klik "Undang ke Lowongan" — `AppApplication` baru dibuat langsung (`current_stage_seq=1`, `status=APPLIED`) tanpa kandidat isi ulang data pribadi.

4. **Pipeline Board (L4)**: HR pilih 1 lowongan published, lihat kandidat sbg kartu kanban dikelompokkan per `current_stage_seq`, kolom = `app_pipeline_stage_config` milik track lowongan itu (jumlah tahap beda per track). Kartu bisa maju via drag-drop ATAU tombol "Lanjutkan →" — keduanya sama-sama tervalidasi penuh di server: hanya boleh maju TEPAT 1 kolom, dan kalau stage saat ini butuh assessment terstruktur (`PSIKOTES`/`INTERVIEW_HR`/`INTERVIEW_USER`), wajib ada baris `AppAssessmentResult` dulu — kalau belum, drop/klik ditolak dengan pesan error. Kartu juga bisa di-"Reject" (wajib alasan) atau "Withdraw" (mundur) kapan saja, keluar dari board.

5. **Interview Schedule (L5)**: kandidat yang di stage `INTERVIEW_HR`/`INTERVIEW_USER` dan belum ada jadwal `SCHEDULED` muncul di sidebar kalender ini. HR drag kartu kandidat ke tanggal/jam (view Day/Month/3-Month/Year) untuk membuka modal pre-filled, konfirmasi interviewer + lokasi. Notifikasi (`sys_notification`, lintas-DB `tm2sys`) dikirim ke interviewer terpilih.

6. **Assessment Result (L6)**: assessor mengisi skor + rekomendasi (`LANJUT`/`TIDAK_LANJUT`/`PERTIMBANGAN`) + catatan wajib untuk kandidat yang stage-nya butuh assessment. Baris `AppAssessmentResult` inilah yang jadi guard di Pipeline Board (langkah 4) sebelum kandidat boleh maju keluar dari stage assessment itu.

7. **Offering (L7)**: begitu `current_stage_seq` mencapai stage `OFFERING`, `AppApplication.status` berubah jadi `OFFERING`. HR buka `offer/entry.blade.php` (dari chip di `offer/list.blade.php` atau langsung via URL `{application}`), yang auto-create `AppOffer` draft (`mount()`). Isi gaji/jenis kontrak/tanggal mulai, submit → `RecruitmentApprovalService::submitOffer()` (baris approval dari `rcr_offer_approval_matrix`, status `PENDING_APPROVAL`). Approver putuskan di layar sama. `APPROVED` → HR "Kirim ke Kandidat" (`SENT`) → HR catat manual respons kandidat ("Kandidat Menerima"/"Kandidat Menolak" — keterbatasan sengaja, portal belum ada tombol accept interaktif). `ACCEPTED` = siap lanjut provisioning; `DECLINED` bisa direvisi (`revise()` bikin `AppOffer` baru, `previous_offer_id` menunjuk yang lama) untuk mengulang siklus offer.

8. **Hire Provision (L9)**: kandidat dengan offer `ACCEPTED` dan belum diprovision muncul di grid "Siap Diprovision" pada `hire-provision.blade.php`. HR pilih posisi/jabatan resmi (`org_position`, lintas-DB `tm2mst` — TIDAK otomatis dari `position_title` teks bebas di requisition, harus dikonfirmasi manual). Provision membuat `EmpEmployee` + `EmpContract` (lintas-DB `tm2mst`) dari data kandidat/offer, menyalin `CndDocument` → `EmpDocument` (arsip rekrutmen tetap utuh, bukan dipindah), lalu `EmployeeLoginProvisionService` membuat akun login. `AppApplication.status` → `HIRED` (state akhir siklus hidup lamaran). Kredensial login ditampilkan sekali di modal, HR infokan manual ke karyawan baru.

## Catatan Arsitektur Lintas Layar
- **Approval bespoke, bukan FlowEngineService**: baik Requisition maupun Offer pakai `RecruitmentApprovalService` + tabel `rcr_approval_matrix`/`rcr_offer_approval_matrix` + `rcr_approver_assignment` (role String → `sec_user_id` langsung, dengan override per-departemen). Ini beda dari modul lain (mis. Procurement) yang sudah pakai `org_position`/`FlowEngineService`. Alasan: Tier3 Recruitment sengaja didesain lebih simpel (role String, tanpa tiering by amount) — lihat komentar kode `RecruitmentApprovalService.php`.
- **State kandidat (`AppApplication.status`)**: `APPLIED` → `IN_PROGRESS` (selama di pipeline, naik `current_stage_seq`) → `OFFERING` → `HIRED` (sukses) atau `REJECTED`/`WITHDRAWN`/`DECLINED` (berhenti di titik manapun).
- **L8 (sync-log)** sengaja tidak berelasi aksi ke L7 (Offer) — mount() Offer Entry punya efek samping bikin AppOffer draft, jadi tidak boleh sembarang di-link dari layar troubleshooting.
- Detail lengkap gap arsitektur (mis. `position_id` tidak auto-map dari requisition) ada di masing-masing file `docs/screens/screen_recruitment_*.md`.
