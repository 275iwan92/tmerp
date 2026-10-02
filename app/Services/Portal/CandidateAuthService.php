<?php

namespace App\Services\Portal;

use App\Exceptions\Portal\CandidateAlreadyExistsException;
use App\Support\Firestore\FirestoreRestClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// Autentikasi kandidat portal profesional -- akun tersimpan di Firestore
// (collection 'candidates', bukan tm2hrr/sec_user internal), sesuai instruksi
// user 2026-08-30 "firebase untuk database". SENGAJA tidak diimplementasi
// lewat kontrak Illuminate\Contracts\Auth\UserProvider penuh (proyek Laravel
// standar) -- provider itu didesain sekitar Eloquent/query builder, Firestore
// via REST tidak cocok API-nya tanpa banyak adapter tambahan. Session-based
// custom guard (lihat middleware EnsurePortalCandidateAuthenticated) lebih
// pragmatis & tetap aman (password di-hash, session di-regenerate saat login).
class CandidateAuthService
{
    const COLLECTION = 'candidates';

    public function __construct(protected FirestoreRestClient $client) {}

    public function findByEmail(string $email): ?array
    {
        return collect($this->client->listDocuments(self::COLLECTION))
            ->first(fn ($c) => strtolower($c['email'] ?? '') === strtolower($email));
    }

    public function findByNationalId(string $nationalId): ?array
    {
        return collect($this->client->listDocuments(self::COLLECTION))
            ->first(fn ($c) => ($c['national_id'] ?? null) === $nationalId);
    }

    public function findByUid(string $uid): ?array
    {
        return $this->client->getDocument(self::COLLECTION, $uid);
    }

    /**
     * @param  array{national_id:string,name:string,email:string,phone:string,password:string}  $data
     * @return array kandidat yang baru dibuat (termasuk '_docId' = uid)
     *
     * @throws CandidateAlreadyExistsException
     */
    public function register(array $data): array
    {
        if ($this->findByEmail($data['email'])) {
            throw new CandidateAlreadyExistsException(t('The email is already registered.'));
        }

        if ($this->findByNationalId($data['national_id'])) {
            throw new CandidateAlreadyExistsException(t('The KTP number is already registered.'));
        }

        $uid = (string) Str::uuid();

        $candidate = [
            'national_id' => $data['national_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => Hash::make($data['password']),
            'email_verified_at' => null,
            'created_at' => now()->toDateTimeString(),
        ];

        $this->client->setDocument(self::COLLECTION, $uid, $candidate);

        return [...$candidate, '_docId' => $uid];
    }

    /** @return array|null kandidat kalau kredensial cocok, null kalau tidak */
    public function attempt(string $email, string $password): ?array
    {
        $candidate = $this->findByEmail($email);

        if (! $candidate || ! Hash::check($password, $candidate['password_hash'] ?? '')) {
            return null;
        }

        return $candidate;
    }

    public function markEmailVerified(string $uid): void
    {
        $this->client->updateDocument(self::COLLECTION, $uid, [
            'email_verified_at' => now()->toDateTimeString(),
        ]);
    }
}
