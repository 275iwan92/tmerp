<?php

namespace App\Services\Recruitment;

use App\Contracts\Recruitment\PortalBackendAdapterInterface;
use App\Models\Crp\CrpCandidateAccount;
use App\Models\Crp\CrpDocument;
use App\Models\Crp\CrpSubmission;
use Illuminate\Support\Str;

// Plan B -- docs/recruitment_tier3.md §3. Di desain final ini jalan lewat
// REST API read-only+token+IP-allowlist ke tm2crp (VPS terpisah), TAPI untuk
// simulasi lokal (keputusan user 2026-08-30: "install lokal") kita akses
// langsung via koneksi Eloquent 'tm2crp' -- API layer menyusul kalau tm2crp
// beneran dipindah ke VPS sendiri, kontrak interface ini tidak berubah.
class MariaDbPortalSyncAdapter implements PortalBackendAdapterInterface
{
    public function findLatestByNationalId(string $nationalId): ?array
    {
        $submission = CrpSubmission::where('national_id', $nationalId)
            ->orderByDesc('submitted_at')
            ->first();

        return $submission?->toArray();
    }

    /**
     * tm2crp tidak punya konsep 'candidate_uid' Firestore -- akun MariaDB
     * diidentifikasi via national_id (lihat saveSubmission). Method ini
     * realistically hanya dipanggil dari jalur Firebase (portal profesional
     * careers/* HARDCODE ke FirebaseSyncAdapter, bukan lewat interface ini --
     * lihat komentar di file itu), diimplementasi best-effort: perlakukan
     * $candidateUid sbg national_id supaya kontrak interface tetap terpenuhi.
     */
    public function findAllByCandidateUid(string $candidateUid): array
    {
        return CrpSubmission::where('national_id', $candidateUid)
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (CrpSubmission $s) => $s->toArray())
            ->all();
    }

    public function saveSubmission(array $payload, array $files = []): string
    {
        $account = CrpCandidateAccount::firstOrCreate(
            ['national_id' => $payload['national_id']],
            ['email' => $payload['email'] ?: null, 'phone' => $payload['phone'] ?: null]
        );

        $uuid = (string) Str::uuid();

        $submission = CrpSubmission::create([
            'submission_uuid' => $uuid,
            'id_account' => $account->id_account,
            ...$payload,
            'submitted_at' => now(),
            'sync_status' => 'pending',
        ]);

        foreach ($files as $docType => $file) {
            if (! $file) {
                continue;
            }
            CrpDocument::create([
                'submission_uuid' => $uuid,
                'doc_type' => strtoupper($docType),
                'file_path' => $file->store('recruitment/portal-documents', ['disk' => 'public']),
                'uploaded_at' => now(),
            ]);
        }

        return $submission->submission_uuid;
    }

    public function fetchPendingSubmissions(int $limit): array
    {
        return CrpSubmission::with('documents')
            ->where('sync_status', 'pending')
            ->orderBy('submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn (CrpSubmission $s) => [
                ...$s->toArray(),
                'documents' => $s->documents->map(fn (CrpDocument $d) => $d->only(['doc_type', 'file_path', 'uploaded_at']))->all(),
                'backend_used' => 'MARIADB',
            ])
            ->all();
    }

    public function markSynced(string $submissionUuid): void
    {
        CrpSubmission::where('submission_uuid', $submissionUuid)
            ->update(['sync_status' => 'synced', 'synced_at' => now()]);
    }

    public function pushStatusUpdate(string $submissionUuid, string $status): void
    {
        CrpSubmission::where('submission_uuid', $submissionUuid)
            ->update(['app_status' => $status, 'status_pushed_at' => now()]);
    }
}
