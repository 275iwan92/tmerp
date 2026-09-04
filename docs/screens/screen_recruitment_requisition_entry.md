---
name: screen_recruitment_requisition_entry
description: Layar detail Permohonan Tenaga Kerja (Requisition) — isi header + approval berjenjang, titik keputusan approve/reject.
---

# Permohonan Tenaga Kerja — Entry + Approval (L1)

**File**: `resources/views/pages/recruitment/requisition/entry.blade.php`
**Route**: `hrm.recruitment.requisition.entry` — `GET /hrm/recruitment/requisition/{requisition}` (param numerik, middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr` (+ `tm2mst` lintas-DB untuk lookup departemen)
**Modul**: Recruitment (Internal) — L1 (entry + approval merge-in), lihat komentar kode: `docs/recruitment_tier4.md` SECTION 6

## Tujuan
Layar isi & approval dokumen Requisition (dibuat kosong dari grid `requisition/list.blade.php`). Pola persis `pur/pr/entry.blade.php` (griduistandard.md §8: approver memutuskan di dokumen asli, bukan layar ringkasan terpisah). Approval bespoke via `RecruitmentApprovalService` (bukan `FlowEngineService` generik yang dipakai modul lain).

## Field & Kolom
- `id_track` (select, wajib) — sumber `tracks()`: `RcrTrack::where('is_active', true)`
- `department_id` (select, wajib) — sumber `departments()`: `OrgDepartment::where('activate', true)` — **lintas-DB** (`tm2mst`)
- `position_title` (text, wajib)
- `headcount` (number, min 1)
- `qualification` (textarea, wajib)
- `reason` (textarea, wajib)

Semua field disabled begitu status bukan `DRAFT`.

**Progres Approval**: bar step bulat per `approval_seq`, warna hijau (APPROVED)/merah (REJECTED)/abu (PENDING), plus tanggal keputusan & catatan penolakan kalau ada.

## Aksi / Method
- `mount(int $requisition)` — set `requisitionId`
- `requisition()` [Computed] — `RcrRequisition::with(['track', 'approvals'])`
- `tracks()`, `departments()` [Computed] — sumber dropdown
- `pendingApproval()` [Computed] — approval seq PENDING terkecil, hanya kalau `approver_id` = user login
- `updateHeader(string $field, $value)` — update 1 field header (whitelist 6 field di atas), via `wire:change`
- `submit(RecruitmentApprovalService $approval)` — validasi `position_title`/`qualification`/`reason` wajib terisi; panggil `submitRequisition()` (buat baris approval dari `RcrApprovalMatrix` sesuai `id_track`, status → `SUBMITTED`)
- `approveDoc()`/`rejectDoc(RecruitmentApprovalService $approval)` — panggil `decideRequisition()`; reject wajib isi `approvalNote`
- `delete()` — hanya kalau status `DRAFT`, redirect ke grid list

## Tabel & Model yang Disentuh
- `RcrRequisition` (`rcr_requisition`, `tm2hrr`, PK `id_requisition`) — utama, CRUD
- `RcrRequisitionApproval` (`tm2hrr`) — baris approval berjenjang, via relasi `approvals()`
- `RcrTrack` (`rcr_track`, `tm2hrr`) — sumber dropdown track
- `OrgDepartment` (`App\Models\Master`, `tm2mst`) — **lintas-DB**, sumber dropdown departemen
- `RcrApprovalMatrix`, `RcrApproverAssignment` (`tm2hrr`) — dipakai `RecruitmentApprovalService::submitRequisition()` untuk resolusi approver (`approver_role` → `sec_user_id`, dengan fallback department-specific menang atas company-wide)

## Alur / Business Logic Penting
State machine status: `DRAFT` → (submit, semua field wajib terisi) → `SUBMITTED` → (semua approval seq APPROVED berurutan) → `APPROVED` → (nanti dipakai buat Vacancy di L2) atau `REJECTED` (kalau salah satu approval seq REJECTED, langsung berhenti — skip sisa approval). Approval seq berjalan berurutan: seq berikutnya baru relevan setelah seq sebelumnya APPROVED (dicek via `pendingRequisitionApprovalFor()` di service, meski di layar ini dipakai versi Computed lokal `pendingApproval()`). `delete()` hanya boleh untuk dokumen `DRAFT` yang belum pernah diajukan.

## Catatan
- Tidak ada gap/TODO eksplisit di komentar kode untuk layar ini selain catatan arsitektur approval bespoke (disebutkan sebagai desain sengaja, bukan gap).
