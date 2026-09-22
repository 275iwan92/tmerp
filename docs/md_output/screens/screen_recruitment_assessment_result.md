---
name: screen_recruitment_assessment_result
description: Input hasil terstruktur Psikotes/Interview per kandidat, guard wajib sebelum kandidat bisa maju tahap di Pipeline Board.
---

# Hasil Psikotes/Interview — Grid + Modal Input (L6)

**File**: `resources/views/pages/recruitment/assessment-result.blade.php`
**Route**: `hrm.recruitment.assessment-result` — `GET /hrm/recruitment/assessment-result` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr` (nama koneksi Eloquent `tm2hrr`)
**Modul**: Recruitment (Internal) — L6, lihat komentar kode: `docs/recruitment_tier4.md` SECTION 11

## Tujuan
Tempat assessor (HR/user department) mengisi hasil terstruktur (skor, rekomendasi, catatan) untuk kandidat yang sedang di tahap `PSIKOTES`/`INTERVIEW_HR`/`INTERVIEW_USER`. Wajib diisi terstruktur sebelum kandidat bisa "Lanjutkan ke Stage Berikutnya" di Pipeline Board — guard-nya ditegakkan di `pipeline-board.blade.php::moveCard()`/`advance()`, layar ini tempat mengisinya. Tidak ada aksi hapus/edit hasil yang sudah tersimpan — riwayat bersifat append-only di grid bawah.

## Field & Kolom
**Filter/search**: `search` (live, debounce 300ms) — cari nama kandidat via relasi `application.candidate`.

**Kotak statistik**: Menunggu Diisi (kandidat di stage assessment yang belum punya hasil), Total Hasil, Rekomendasi Lanjut.

**Panel "Menunggu Diisi"**: daftar chip kandidat (nama + nama stage) yang belum punya `AppAssessmentResult` untuk stage berjalannya — klik chip membuka modal `openNew()`.

**Grid hasil** (kolom): Kandidat, Stage (`stage_code`), Skor (rata kanan, font mono), Rekomendasi (badge warna: `LANJUT` hijau, `TIDAK_LANJUT` merah, `PERTIMBANGAN` kuning), Catatan (`Str::limit` 60 char).

**Modal Input Hasil**:
- `score` (float, opsional, step 0.01)
- `recommendation` (select, default `LANJUT`; opsi `LANJUT`/`TIDAK_LANJUT`/`PERTIMBANGAN`)
- `notes` (textarea, **wajib diisi** — validasi manual `trim($this->notes) === ''`)

## Aksi / Method
- `updatedSearch()` — reset pagination
- `candidatesAwaiting()` [Computed] — cocokkan `AppApplication::status=IN_PROGRESS` ke `AppPipelineStageConfig` (match `id_track` + `current_stage_seq`) utk 3 stage assessment, filter yang belum punya `AppAssessmentResult`
- `stats()` [Computed] — hitung 3 angka kotak statistik
- `rows()` [Computed] — grid hasil, `AppAssessmentResult::with('application.candidate')`, paginate 20
- `openNew(int $applicationId, string $stageCode)` — buka modal, reset field form
- `save()` — validasi `notes` wajib, `AppAssessmentResult::create()` dengan `assessor_id = Auth::id()`, `assessed_at = now()`

## Tabel & Model yang Disentuh
- `AppAssessmentResult` (`app_assessment_result`, `tm2hrr`, PK `id_result`) — utama, create-only (tak ada update/delete di layar ini)
- `AppApplication` (`app_application`, `tm2hrr`) — sumber daftar kandidat menunggu, relasi `candidate`, `vacancy.requisition`
- `AppPipelineStageConfig` (`app_pipeline_stage_config`, `tm2hrr`) — dicocokkan manual di PHP (`firstWhere`) terhadap `id_track` + `stage_seq`, bukan lewat relasi Eloquent formal

## Alur / Business Logic Penting
Assessment hanya relevan untuk 3 stage code: `PSIKOTES`, `INTERVIEW_HR`, `INTERVIEW_USER` (hardcode di `candidatesAwaiting()` dan `requiresAssessment()` milik `pipeline-board.blade.php`). Satu kandidat bisa punya lebih dari satu baris hasil kalau assessor mengisi ulang untuk stage yang sama (tidak ada guard unique/upsert — `save()` selalu `create()` baris baru). Cek keberadaan hasil dicocokkan via `AppAssessmentResult::where('id_application', ...)->where('stage_code', ...)->exists()`.

## Catatan
- Tidak ada validasi rentang untuk `score` (bisa negatif/di luar skala umum) — TBD, tidak ada komentar kode soal skala penilaian resmi.
- `save()` selalu `create()`, tidak ada pengecekan duplikat — assessor bisa tak sengaja mengisi hasil dua kali untuk kandidat+stage yang sama tanpa error, hanya menambah baris riwayat.
