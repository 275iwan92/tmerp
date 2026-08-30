<?php

namespace App\Services\Recruitment;

use App\Contracts\Recruitment\PortalBackendAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

// Plan A -- docs/recruitment_tier3.md §2. Lokal: diarahkan ke Firebase Local
// Emulator Suite (firebase/firebase.json, jalankan `firebase emulators:start
// --only firestore,auth` dari folder firebase/).
//
// REVISI vs rencana awal (docs/recruitment_delta_plan_ab_local.md §4): TIDAK
// pakai package google/cloud-firestore (disyaratkan kreait/firebase-php utk
// modul Firestore-nya) -- package itu WAJIB ekstensi native PHP `ext-grpc`,
// yang tidak tersedia di PHP 8.4 Windows/Herd dan berisiko dipasang manual
// (butuh DLL PECL cocok versi+build, belum tentu ada). Diganti panggil REST
// API Firestore langsung (Illuminate\Http, tanpa SDK/gRPC) -- REST API
// Firestore v1 sama persis bentuknya baik ke emulator maupun project Firebase
// asli nanti, cuma beda base URL/auth (lihat properti $baseUrl + header
// 'Authorization: Bearer owner' -- token khusus yang dikenali emulator utk
// bypass firestore.rules, dipakai semua tooling resmi Google utk testing
// lokal; TIDAK valid/tidak berlaku di project Firebase asli, ganti ke OAuth
// token service-account sungguhan saat migrasi ke cloud).
class FirebaseSyncAdapter implements PortalBackendAdapterInterface
{
    protected string $baseUrl;

    public function __construct()
    {
        $host = config('recruitment.firebase.firestore_emulator_host');
        $projectId = config('recruitment.firebase.project_id');

        if (! $host || ! $projectId) {
            throw new RuntimeException('FIRESTORE_EMULATOR_HOST / FIREBASE_PROJECT_ID belum di-set di .env');
        }

        $this->baseUrl = "http://{$host}/v1/projects/{$projectId}/databases/(default)/documents";
    }

    protected function http()
    {
        return Http::withHeaders(['Authorization' => 'Bearer owner']); // lihat catatan class ini
    }

    public function findLatestByNationalId(string $nationalId): ?array
    {
        $matches = collect($this->listDocuments('submissions'))
            ->filter(fn ($doc) => ($doc['national_id'] ?? null) === $nationalId)
            ->sortByDesc('submitted_at');

        return $matches->first();
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

        $data = [
            'submission_uuid' => $uuid,
            'candidate_uid' => $payload['national_id'], // simulasi KTP-only, bukan Firebase Auth uid sungguhan
            ...$payload,
            'documents' => $documents,
            'submitted_at' => now()->toDateTimeString(),
            'sync_status' => 'pending',
            'synced_at' => null,
        ];

        $response = $this->http()->patch("{$this->baseUrl}/submissions/{$uuid}", [
            'fields' => $this->encodeFields($data),
        ]);

        $response->throw();

        return $uuid;
    }

    public function fetchPendingSubmissions(int $limit): array
    {
        return collect($this->listDocuments('submissions'))
            ->filter(fn ($doc) => ($doc['sync_status'] ?? null) === 'pending')
            ->take($limit)
            ->map(fn ($doc) => [...$doc, 'backend_used' => 'FIREBASE'])
            ->values()
            ->all();
    }

    public function markSynced(string $submissionUuid): void
    {
        $data = ['sync_status' => 'synced', 'synced_at' => now()->toDateTimeString()];

        $response = $this->http()->patch(
            "{$this->baseUrl}/submissions/{$submissionUuid}?".
            'updateMask.fieldPaths=sync_status&updateMask.fieldPaths=synced_at',
            ['fields' => $this->encodeFields($data)]
        );

        $response->throw();
    }

    public function pushStatusUpdate(string $submissionUuid, string $status): void
    {
        // Belum dipakai simulasi ini, sama seperti MariaDbPortalSyncAdapter -- no-op sengaja.
    }

    /** GET seluruh dokumen 1 collection, didekode ke array asosiatif polos + '_docId'. */
    protected function listDocuments(string $collection): array
    {
        $response = $this->http()->get("{$this->baseUrl}/{$collection}");
        $response->throw();

        return collect($response->json('documents', []))
            ->map(function (array $doc) {
                $decoded = $this->decodeFields($doc['fields'] ?? []);
                $decoded['_docId'] = basename($doc['name']);

                return $decoded;
            })
            ->all();
    }

    protected function encodeFields(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[$key] = $this->encodeValue($value);
        }

        return $fields;
    }

    protected function encodeValue(mixed $value): array
    {
        return match (true) {
            $value === null => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_array($value) && array_is_list($value) => ['arrayValue' => ['values' => array_map($this->encodeValue(...), $value)]],
            is_array($value) => ['mapValue' => ['fields' => $this->encodeFields($value)]],
            default => ['stringValue' => (string) $value],
        };
    }

    protected function decodeFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $out[$key] = $this->decodeValue($value);
        }

        return $out;
    }

    protected function decodeValue(array $value): mixed
    {
        return match (true) {
            array_key_exists('nullValue', $value) => null,
            array_key_exists('booleanValue', $value) => $value['booleanValue'],
            array_key_exists('integerValue', $value) => (int) $value['integerValue'],
            array_key_exists('doubleValue', $value) => (float) $value['doubleValue'],
            array_key_exists('stringValue', $value) => $value['stringValue'],
            array_key_exists('timestampValue', $value) => $value['timestampValue'],
            array_key_exists('arrayValue', $value) => array_map($this->decodeValue(...), $value['arrayValue']['values'] ?? []),
            array_key_exists('mapValue', $value) => $this->decodeFields($value['mapValue']['fields'] ?? []),
            default => null,
        };
    }
}
