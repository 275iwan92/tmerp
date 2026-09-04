---
name: screen_recruitment_pipeline_board
description: Kanban board kandidat per tahap seleksi (stage config per track), drag-drop divalidasi penuh di server.
---

# Pipeline Board — Kanban (L4)

**File**: `resources/views/pages/recruitment/pipeline-board.blade.php`
**Route**: `hrm.recruitment.pipeline-board` — `GET /hrm/recruitment/pipeline-board` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr`
**Modul**: Recruitment (Internal) — L4, lihat komentar kode: `docs/recruitment_tier4.md` SECTION 9

## Tujuan
Kanban board kandidat per tahap seleksi untuk 1 lowongan terpilih. Kolom = `app_pipeline_stage_config` milik track lowongan itu (jumlah kolom beda per track). REVISI 2026-08-29 (instruksi user, deviasi sadar dari Tier4 §15 "TIDAK boleh drag-drop bebas"): drag-drop kartu diaktifkan, TAPI validasi tetap ditegakkan penuh di server — kartu cuma boleh maju TEPAT 1 kolom (tidak bisa lompat tahap), dan tetap wajib assessment terstruktur dulu sebelum lolos ke tahap yang mensyaratkannya. Drop yang melanggar aturan DITOLAK (kartu kembali ke posisi semula via re-render dari DB) + pesan error. Tombol "Lanjutkan"/Reject/Withdraw tetap ada sebagai alternatif non-drag.

## Field & Kolom
**Pemilih Lowongan**: dropdown `vacancyId` (`wire:model.live`), sumber `openVacancies()` (`is_published=true`).

**Kolom kanban**: 1 kolom per `AppPipelineStageConfig` (urut `stage_seq`), tiap kolom judul + counter kartu.

**Kartu kandidat**: nama, `national_id` (font mono), tombol "Lanjutkan →" (kecuali di kolom `OFFERING`), "Reject", "Withdraw" (confirm dialog).

**Modal Reject**: `rejectReason` (textarea, wajib diisi).

## Aksi / Method
- `mount()` — `vacancyId` default lowongan published terbaru
- `openVacancies()` [Computed] — daftar lowongan published
- `vacancy()` [Computed] — vacancy terpilih + `requisition.track`
- `stages()` [Computed] — `AppPipelineStageConfig` milik track vacancy, urut `stage_seq`
- `cardsByStage()` [Computed] — `AppApplication` milik vacancy dengan status `APPLIED`/`IN_PROGRESS`/`OFFERING`, dikelompokkan per `current_stage_seq`
- `requiresAssessment(string $stageCode)` [protected] — cek apakah stage code termasuk `PSIKOTES`/`INTERVIEW_HR`/`INTERVIEW_USER`
- `advance(int $applicationId)` — tombol "Lanjutkan", selalu ke stage berikutnya persis
- `moveCard(int $applicationId, int $targetStageSeq)` — handler drag-drop; validasi target harus `current+1`; kalau stage saat ini butuh assessment, cek `AppAssessmentResult` sudah ada; update `current_stage_seq` + `status` (jadi `OFFERING` kalau stage tujuan `OFFERING`, selain itu `IN_PROGRESS`)
- `openReject(int $id)` / `reject()` — buka modal + validasi alasan wajib + update status `REJECTED` + `reject_reason`
- `withdraw(int $id)` — update status `WITHDRAWN` langsung (dengan `wire:confirm`, tanpa modal alasan)

## Tabel & Model yang Disentuh
- `AppApplication` (`app_application`, `tm2hrr`) — utama, update `current_stage_seq`/`status`/`reject_reason`
- `AppAssessmentResult` (`app_assessment_result`, `tm2hrr`) — dicek keberadaannya sebagai guard sebelum lolos stage assessment
- `AppPipelineStageConfig` (`app_pipeline_stage_config`, `tm2hrr`) — sumber kolom & urutan tahap
- `VacVacancy` (`vac_vacancy`, `tm2hrr`) — sumber pemilih lowongan

## Alur / Business Logic Penting
Ini adalah state machine inti pipeline kandidat: `current_stage_seq` naik 1-demi-1, tidak bisa lompat (baik lewat drag maupun tombol "Lanjutkan" — keduanya sama-sama panggil `moveCard()` dengan `target = current+1`). Guard assessment (Tier2 §4) ditegakkan di titik transisi KELUAR dari stage yang butuh assessment, bukan titik masuk. `REJECTED`/`WITHDRAWN` adalah state akhir non-lanjut, kartu hilang dari board (di luar filter status `APPLIED`/`IN_PROGRESS`/`OFFERING`).

## Catatan
- Deviasi sadar dari dokumen Tier4 §15 (yang aslinya melarang drag-drop bebas) — didokumentasikan eksplisit di komentar kode sebagai keputusan user 2026-08-29, bukan bug.
- Tidak ada pengecekan apakah `vacancyId` masih `is_published` saat halaman sudah lama dibuka (kondisi race minor, tidak terverifikasi apakah ini masalah nyata).
