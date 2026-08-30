<?php

namespace App\Services\Recruitment;

use App\Contracts\Recruitment\PortalBackendAdapterInterface;
use App\Support\Firestore\FirestoreRestClient;
use Illuminate\Support\Str;

// Plan A -- docs/recruitment_tier3.md §2. Lokal: diarahkan ke Firebase Local
// Emulator Suite (firebase/firebase.json, jalankan `firebase emulators:start
// --only firestore,auth` dari folder firebase/).
//
// REVISI vs rencana awal (docs/recruitment_delta_plan_ab_local.md §4): TIDAK
// pakai package google/cloud-firestore (butuh ext-grpc, tak tersedia di PHP
// Windows) -- panggil REST API Firestore langsung lewat FirestoreRestClient
// (diekstrak 2026-08-30 saat portal profesional butuh akses Firestore juga
// utk akun kandidat, lihat App\Services\Portal\CandidateAuthService).
class FirebaseSyncAdapter implements PortalBackendAdapterInterface
{
    public function __construct(protected FirestoreRestClient $client) {}

    public function findLatestByNationalId(string $nationalId): ?array
    {
        return collect($this->client->listDocuments('submissions'))
            ->filter(fn ($doc) => ($doc['national_id'] ?? null) === $nationalId)
            ->sortByDesc('submitted_at')
            ->first();
    }

    public function findAllByCandidateUid(string $candidateUid): array
    {
        return collect($this->client->listDocuments('submissions'))
            ->filter(fn ($doc) => ($doc['candidate_uid'] ?? null) === $candidateUid)
            ->sortByDesc('submitted_at')
            ->values()
            ->all();
    }

    public function saveSubmission(array $payload, array $files = []): string
    {
        $uuid = (string) Str::uuid();

        $documents = [];
        foreach ($files as $docType => $file) {
            if (! $file) {
                continue;
            }
            $documents[] = [
                'doc_type' => strtoupper($docType),
                'file_path' => $file->store('recruitment/portal-documents', ['disk' => 'public']),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        $this->client->setDocument('submissions', $uuid, [
            'submission_uuid' => $uuid,
            'candidate_uid' => $payload['candidate_uid'] ?? $payload['national_id'],
            ...$payload,
            'documents' => $documents,
            'submitted_at' => now()->toDateTimeString(),
            'sync_status' => 'pending',
            'synced_at' => null,
        ]);

        return $uuid;
    }

    public function fetchPendingSubmissions(int $limit): array
    {
        return collect($this->client->listDocuments('submissions'))
            ->filter(fn ($doc) => ($doc['sync_status'] ?? null) === 'pending')
            ->take($limit)
            ->map(fn ($doc) => [...$doc, 'backend_used' => 'FIREBASE'])
            ->values()
            ->all();
    }

    public function markSynced(string $submissionUuid): void
    {
        $this->client->updateDocument('submissions', $submissionUuid, [
            'sync_status' => 'synced',
            'synced_at' => now()->toDateTimeString(),
        ]);
    }

    public function pushStatusUpdate(string $submissionUuid, string $status): void
    {
        $this->client->updateDocument('submissions', $submissionUuid, [
            'app_status' => $status,
            'status_pushed_at' => now()->toDateTimeString(),
        ]);
    }
}
