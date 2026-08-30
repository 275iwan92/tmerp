<?php

namespace App\Services\Recruitment;

use App\Contracts\Recruitment\PortalBackendAdapterInterface;
use App\Models\Hrr\AppApplication;
use App\Models\Hrr\CndCandidate;
use App\Models\Hrr\CndDocument;
use App\Models\Hrr\CndEducation;
use App\Models\Hrr\CndEducationNonformal;
use App\Models\Hrr\CndFamilyMember;
use App\Models\Hrr\CndOrganizationExperience;
use App\Models\Hrr\CndWorkExperience;
use App\Models\Hrr\RcrSyncLog;
use App\Models\Hrr\VacVacancy;
use Illuminate\Support\Facades\Log;
use Throwable;

// Business logic sync GENERIC internal (tm2hrr) -- docs/recruitment_tier3.md
// §1.1: "seluruh domain logic (dedupe KTP, insert cnd_candidate/app_application,
// validasi) berada di service layer internal yang generic, hanya method
// fetchPendingSubmissions() yang beda implementasi [Plan A/B]". Kelas ini
// TIDAK tahu apakah datanya dari Firestore atau tm2crp -- adapter yang
// menyeragamkan bentuk array sebelum sampai sini.
class PortalSyncService
{
    public function __construct(protected PortalBackendAdapterInterface $adapter) {}

    /** @return array{pulled:int, synced:int, skipped:int, failed:int} */
    public function run(?int $limit = null): array
    {
        $submissions = $this->adapter->fetchPendingSubmissions($limit ?? config('recruitment.sync.batch_limit', 20));

        $stats = ['pulled' => count($submissions), 'synced' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($submissions as $submission) {
            $uuid = $submission['submission_uuid'];

            // Idempotency (recruitment_tier3.md §1.2): submission_uuid sudah
            // pernah OK diproses -> jangan insert dobel, cukup tandai synced
            // lagi di backend (kasus retry job setelah markSynced sempat gagal).
            if (RcrSyncLog::where('submission_uuid', $uuid)->where('result', 'OK')->exists()) {
                $this->adapter->markSynced($uuid);
                $stats['skipped']++;

                continue;
            }

            try {
                $application = $this->applyOne($submission);

                RcrSyncLog::create([
                    'submission_uuid' => $uuid,
                    'backend_used' => $submission['backend_used'] ?? strtoupper(config('recruitment.backend')),
                    'fetched_at' => now(),
                    'result' => 'OK',
                    'id_application' => $application->id_application,
                ]);

                $this->adapter->markSynced($uuid);
                $stats['synced']++;
            } catch (Throwable $e) {
                Log::error('PortalSyncService: gagal proses submission', ['submission_uuid' => $uuid, 'error' => $e->getMessage()]);

                RcrSyncLog::create([
                    'submission_uuid' => $uuid,
                    'backend_used' => $submission['backend_used'] ?? strtoupper(config('recruitment.backend')),
                    'fetched_at' => now(),
                    'result' => 'ERROR',
                    'error_note' => substr($e->getMessage(), 0, 255),
                ]);

                $stats['failed']++;
            }
        }

        return $stats;
    }

    protected function applyOne(array $submission): AppApplication
    {
        $candidate = CndCandidate::updateOrCreate(
            ['national_id' => $submission['national_id']],
            [
                ...array_intersect_key($submission, array_flip((new CndCandidate)->getFillable())),
                'created_by' => 'portal_sync_'.strtolower($submission['backend_used'] ?? 'unknown'),
                'updated_by' => 'portal_sync_'.strtolower($submission['backend_used'] ?? 'unknown'),
            ]
        );

        // Seksi berulang -- overwrite semantics (sama seperti portal.blade.php
        // submitApplication(), lihat delta doc §"kenapa overwrite bukan append").
        $clean = fn (array $row) => array_map(fn ($v) => $v === '' ? null : $v, $row);

        CndFamilyMember::where('id_candidate', $candidate->id_candidate)->delete();
        foreach ($submission['family_members'] ?? [] as $f) {
            if (trim($f['name'] ?? '') !== '') {
                CndFamilyMember::create(['id_candidate' => $candidate->id_candidate, ...$clean($f)]);
            }
        }

        CndEducation::where('id_candidate', $candidate->id_candidate)->delete();
        foreach ($submission['education'] ?? [] as $e) {
            if (trim($e['institution'] ?? '') !== '') {
                CndEducation::create(['id_candidate' => $candidate->id_candidate, ...$clean($e)]);
            }
        }

        CndEducationNonformal::where('id_candidate', $candidate->id_candidate)->delete();
        foreach ($submission['education_nonformal'] ?? [] as $e) {
            if (trim($e['course_type'] ?? '') !== '') {
                CndEducationNonformal::create(['id_candidate' => $candidate->id_candidate, ...$clean($e)]);
            }
        }

        CndOrganizationExperience::where('id_candidate', $candidate->id_candidate)->delete();
        foreach ($submission['org_experience'] ?? [] as $o) {
            if (trim($o['organization_name'] ?? '') !== '') {
                CndOrganizationExperience::create(['id_candidate' => $candidate->id_candidate, ...$clean($o)]);
            }
        }

        // Riwayat kerja -- APPEND + dedupe company+position (sama seperti portal.blade.php).
        foreach ($submission['work_experience'] ?? [] as $w) {
            if (trim($w['company'] ?? '') === '') {
                continue;
            }

            $exists = CndWorkExperience::where('id_candidate', $candidate->id_candidate)
                ->where('company', $w['company'])->where('position', $w['position'] ?? '')->exists();

            if (! $exists) {
                CndWorkExperience::create(['id_candidate' => $candidate->id_candidate, ...$clean($w)]);
            }
        }

        foreach ($submission['documents'] ?? [] as $doc) {
            $exists = CndDocument::where('id_candidate', $candidate->id_candidate)
                ->where('doc_type', $doc['doc_type'])->where('file_path', $doc['file_path'])->exists();

            if (! $exists) {
                CndDocument::create([
                    'id_candidate' => $candidate->id_candidate,
                    'doc_type' => $doc['doc_type'],
                    'file_path' => $doc['file_path'],
                    'uploaded_at' => $doc['uploaded_at'] ?? now(),
                ]);
            }
        }

        $vacancy = VacVacancy::where('vacancy_code', $submission['vacancy_code'])->firstOrFail();

        // Idempotency tambahan level app_application (kolom submission_uuid
        // sudah ada di skema, lihat AppApplication -- retry job tak dobel apply).
        $application = AppApplication::firstOrCreate(
            ['submission_uuid' => $submission['submission_uuid']],
            [
                'id_candidate' => $candidate->id_candidate,
                'id_vacancy' => $vacancy->id_vacancy,
                'current_stage_seq' => 1,
                'status' => 'APPLIED',
                'applied_at' => $submission['submitted_at'] ?? now(),
            ]
        );

        return $application;
    }
}
