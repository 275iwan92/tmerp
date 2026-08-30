<?php

namespace App\Contracts\Recruitment;

// Kontrak tunggal Plan A (Firebase)/Plan B (MariaDB tm2crp) -- internal
// (tm2hrr) TIDAK PERNAH tahu backend mana yang jalan, lihat
// docs/recruitment_tier3.md §1.1. Diperluas dari desain asli tier3 (yang cuma
// mendefinisikan sisi worker fetchPendingSubmissions/markSynced/
// pushStatusUpdate) dengan 2 method sisi PORTAL (findLatestByNationalId/
// saveSubmission) -- perluasan ini perlu karena portal "asli" (Flutter,
// tier3) belum dibangun, portal SIMULASI (Livewire, portal.blade.php) jalan
// di app Laravel yang sama dan butuh jalur tulis eksplisit ke backend aktif,
// bukan lagi langsung ke tm2hrr (lihat delta docs/recruitment_delta_plan_ab_local.md).
interface PortalBackendAdapterInterface
{
    /**
     * Sisi PORTAL -- cari submission TERAKHIR milik 1 KTP (buat prefill form
     * saat kandidat login ulang). null kalau belum pernah submit.
     */
    public function findLatestByNationalId(string $nationalId): ?array;

    /**
     * Sisi PORTAL -- simpan 1 submission baru (draft/final lamaran). Payload
     * associative array, key = nama kolom crp_submission (mirror cnd_candidate,
     * lihat CrpSubmission model). $files = ['cv' => UploadedFile|null, 'ijazah' => UploadedFile|null].
     * Return submission_uuid yang baru dibuat.
     */
    public function saveSubmission(array $payload, array $files = []): string;

    /**
     * Sisi WORKER (internal, tm2hrr) -- tarik submission yang belum di-sync.
     * Return array of array (bukan Eloquent model -- lintas backend beda tipe),
     * tiap elemen minimal punya 'submission_uuid' + seluruh field crp_submission.
     */
    public function fetchPendingSubmissions(int $limit): array;

    /**
     * Sisi WORKER -- tandai 1 submission sudah berhasil ditarik & diproses.
     */
    public function markSynced(string $submissionUuid): void;

    /**
     * Sisi WORKER -- push balik update status lamaran ke portal (utk
     * ditampilkan ke kandidat). Belum dipakai di simulasi ini (portal.blade.php
     * baca status langsung dari internal via myApplications -- lihat catatan
     * "Belum dibangun" di delta doc), disiapkan sesuai kontrak tier3 §1.2.
     */
    public function pushStatusUpdate(string $submissionUuid, string $status): void;
}
