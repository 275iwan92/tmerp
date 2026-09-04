---
name: screen_recruitment_offer_entry
description: Layar detail penawaran gaji/kontrak per kandidat, approval berjenjang, dan tracking respons kandidat (accept/decline).
---

# Offering — Entry + Approval (L7)

**File**: `resources/views/pages/recruitment/offer/entry.blade.php`
**Route**: `hrm.recruitment.offer.entry` — `GET /hrm/recruitment/offer/{application}` (param `application` numerik, middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr`
**Modul**: Recruitment (Internal) — L7 (entry + approval merge-in), lihat komentar kode: `docs/recruitment_tier4.md` SECTION 12

## Tujuan
Detail penawaran (gaji, jenis kontrak, tanggal mulai) untuk 1 kandidat, plus approval berjenjang dan tracking status offer sampai kandidat menerima/menolak. Pola sama seperti `requisition/entry.blade.php` (griduistandard.md §8: approver memutuskan di dokumen asli, bukan layar ringkasan terpisah). Kandidat seharusnya accept/decline lewat portal (P4), tapi portal belum punya tombol accept interaktif di iterasi ini — jadi HR input manual status respons kandidat di layar ini (keterbatasan yang dicatat eksplisit di komentar kode).

## Field & Kolom
- `proposed_salary` (number, step 0.01) — bisa diedit hanya saat status `DRAFT`
- `employment_type` (select: `TETAP`/`KONTRAK`/`HARIAN`/`BORONGAN`) — bisa diedit hanya saat status `DRAFT`
- `start_date` (date picker) — bisa diedit hanya saat status `DRAFT`

Semua field disabled (`bg-[#f8fafc]`) begitu status offer bukan `DRAFT` lagi.

**Progres Approval**: bar step bulat per `approval_seq`, warna hijau (APPROVED)/merah (REJECTED)/abu (PENDING).

## Aksi / Method
- `mount(int $application)` — set `applicationId`; kalau belum ada `AppOffer` untuk application ini, auto-create draft (`proposed_salary=0`, `employment_type=TETAP`, `start_date=+14 hari`, `status=DRAFT`)
- `application()` [Computed] — `AppApplication::with('candidate', 'vacancy')`
- `offer()` [Computed] — offer terbaru (`orderByDesc('id_offer')`) untuk application ini, dengan `approvals`
- `pendingApproval()` [Computed] — approval seq PENDING pertama, hanya kalau `approver_id` = user login
- `updateField(string $field, $value)` — update 1 field (whitelist `proposed_salary`/`employment_type`/`start_date`), dipanggil via `wire:change`
- `submit(RecruitmentApprovalService $approval)` — validasi `proposed_salary > 0`; panggil `submitOffer()` (buat baris approval dari `RcrOfferApprovalMatrix`, status jadi `PENDING_APPROVAL`)
- `approveDoc()`/`rejectDoc(RecruitmentApprovalService $approval)` — panggil `decideOffer()`
- `markSent()` — status → `SENT` (setelah `APPROVED`)
- `markAccepted()`/`markDeclined()` — input manual respons kandidat (setelah `SENT`)
- `revise()` — buat `AppOffer` baru (status `DRAFT`, `previous_offer_id` menunjuk offer lama) — dipakai kalau offer `DECLINED` dan HR ingin coba lagi dengan penawaran baru

## Tabel & Model yang Disentuh
- `AppOffer` (`app_offer`, `tm2hrr`, PK `id_offer`) — utama, CRUD (tanpa `updated_at`, hanya `created_at`)
- `AppOfferApproval` (`tm2hrr`) — baris approval berjenjang per offer, via relasi `approvals()`
- `AppApplication` (`app_application`, `tm2hrr`) — header kandidat/lowongan
- `RcrOfferApprovalMatrix`, `RcrApproverAssignment` (`tm2hrr`) — dipakai `RecruitmentApprovalService::submitOffer()` untuk resolusi approver

## Alur / Business Logic Penting
State machine status offer: `DRAFT` → (submit) → `PENDING_APPROVAL` → (semua approval APPROVED) → `APPROVED` → (markSent) → `SENT` → (respons kandidat manual) → `ACCEPTED`/`DECLINED`. Kalau `REJECTED` di salah satu approval seq, langsung `DECLINED` (skip sisa approval). Kalau `DECLINED`, HR bisa `revise()` untuk membuat offer baru dari awal (siklus ulang). `ACCEPTED` = titik masuk ke layar berikutnya, "Provisioning ke ERP" (link langsung di UI ke `hrm.recruitment.hire-provision`).

## Catatan
- Respons kandidat (`markAccepted`/`markDeclined`) input manual HR — bukan dari aksi kandidat sendiri, dicatat sbg keterbatasan sengaja di komentar kode (portal belum ada tombol accept interaktif).
- Approval memakai `RecruitmentApprovalService` bespoke (role String via `RcrApproverAssignment`), bukan `FlowEngineService` generik yang dipakai modul lain berbasis `org_position`.
