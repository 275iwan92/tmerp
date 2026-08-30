<?php

namespace App\Observers;

use App\Models\Hrr\AppApplication;
use App\Models\Hrr\RcrSyncLog;
use App\Services\Recruitment\FirebaseSyncAdapter;
use App\Services\Recruitment\MariaDbPortalSyncAdapter;
use Illuminate\Support\Facades\Log;
use Throwable;

// Nutup arah balik internal->portal (recruitment_tier3.md §1.2 pushStatusUpdate)
// SECARA GENERIK -- observer pada model, bukan ditempel manual di tiap layar
// internal yang bisa ubah app_application.status (Pipeline Board drag-drop,
// approval Offer, dst). Jadi status di portal SELALU ikut real begitu HR
// ubah status di mana pun, tanpa perlu sentuh kode masing-masing layar itu.
class AppApplicationObserver
{
    public function updated(AppApplication $application): void
    {
        if (! $application->wasChanged('status') || ! $application->submission_uuid) {
            return;
        }

        $log = RcrSyncLog::where('submission_uuid', $application->submission_uuid)
            ->where('result', 'OK')
            ->first();

        if (! $log) {
            return; // belum pernah sync sukses -- backend_used tak diketahui, portal belum tahu submission ini
        }

        try {
            $adapter = match ($log->backend_used) {
                'FIREBASE' => app(FirebaseSyncAdapter::class),
                default => app(MariaDbPortalSyncAdapter::class),
            };

            $adapter->pushStatusUpdate($application->submission_uuid, $application->status);
        } catch (Throwable $e) {
            // Push balik ke portal best-effort -- kegagalan (mis. emulator
            // Firebase sedang mati) TIDAK BOLEH menggagalkan aksi HR yang
            // sedang berjalan (ubah status lamaran tetap harus tersimpan).
            Log::warning('AppApplicationObserver: gagal pushStatusUpdate', [
                'submission_uuid' => $application->submission_uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
