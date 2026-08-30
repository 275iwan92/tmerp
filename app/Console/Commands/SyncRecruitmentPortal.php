<?php

namespace App\Console\Commands;

use App\Services\Recruitment\PortalSyncService;
use Illuminate\Console\Command;

// PULL sync worker (docs/recruitment_tier3.md §1.2) -- internal (tm2hrr)
// menginisiasi koneksi KELUAR secara berkala menarik submission baru dari
// backend portal aktif (Plan A/B, lihat config('recruitment.backend')).
// Portal TIDAK PERNAH memanggil masuk ke jaringan internal.
class SyncRecruitmentPortal extends Command
{
    protected $signature = 'recruitment:sync-portal {--limit=}';

    protected $description = 'Tarik submission pending dari backend portal aktif (Firebase/tm2crp) ke tm2hrr';

    public function handle(PortalSyncService $service): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $stats = $service->run($limit);

        $this->info(sprintf(
            'Backend: %s | Ditarik: %d | Sync OK: %d | Dilewati (sudah sync): %d | Gagal: %d',
            config('recruitment.backend'),
            $stats['pulled'], $stats['synced'], $stats['skipped'], $stats['failed']
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
