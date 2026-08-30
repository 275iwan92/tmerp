<?php

namespace App\Support\Firestore;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Client REST Firestore generik (bukan collection-spesifik) -- diekstrak dari
// App\Services\Recruitment\FirebaseSyncAdapter 2026-08-30 saat portal
// rekrutmen profesional butuh akses Firestore juga utk akun kandidat
// (candidates/{uid}), bukan cuma submissions/{uuid}. Dipakai KEDUANYA supaya
// logic encode/decode typed-value Firestore & auth emulator tidak dobel.
//
// Kenapa REST langsung (bukan google/cloud-firestore SDK): lihat komentar
// FirebaseSyncAdapter -- SDK itu wajib ext-grpc, tidak tersedia/aman
// dipasang di PHP Windows/Herd. REST API v1 Firestore identik bentuknya baik
// ke emulator maupun project cloud asli, cuma beda base URL/auth.
class FirestoreRestClient
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
        // Header 'Bearer owner' -- token khusus dikenali emulator Firestore
        // utk bypass firestore.rules (perilaku standar tooling resmi Google
        // buat testing lokal). TIDAK valid ke project Firebase asli -- ganti
        // ke OAuth token service-account sungguhan saat migrasi ke cloud.
        return Http::withHeaders(['Authorization' => 'Bearer owner']);
    }

    /** GET seluruh dokumen 1 collection, didekode ke array asosiatif polos + '_docId'. */
    public function listDocuments(string $collection): array
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

    public function getDocument(string $collection, string $docId): ?array
    {
        $response = $this->http()->get("{$this->baseUrl}/{$collection}/{$docId}");

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        $decoded = $this->decodeFields($response->json('fields', []));
        $decoded['_docId'] = $docId;

        return $decoded;
    }

    /** Set/replace penuh 1 dokumen (create atau overwrite total). */
    public function setDocument(string $collection, string $docId, array $data): void
    {
        $this->http()->patch("{$this->baseUrl}/{$collection}/{$docId}", [
            'fields' => $this->encodeFields($data),
        ])->throw();
    }

    public function deleteDocument(string $collection, string $docId): void
    {
        $this->http()->delete("{$this->baseUrl}/{$collection}/{$docId}");
    }

    /** Update sebagian field saja (field lain tidak tersentuh). */
    public function updateDocument(string $collection, string $docId, array $data): void
    {
        $mask = collect(array_keys($data))->map(fn ($f) => 'updateMask.fieldPaths='.urlencode($f))->implode('&');

        $this->http()->patch(
            "{$this->baseUrl}/{$collection}/{$docId}?{$mask}",
            ['fields' => $this->encodeFields($data)]
        )->throw();
    }

    public function encodeFields(array $data): array
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

    public function decodeFields(array $fields): array
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
