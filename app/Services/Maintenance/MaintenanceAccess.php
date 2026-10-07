<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Sys\SecUser;

// Siapa boleh mengubah master Maintenance: administrator atau pemegang hak approve modul MAINT (kepala maintenance).
// Teknisi dan staf produksi hanya melihat.
final class MaintenanceAccess
{
    public static function canManage(?SecUser $user): bool
    {
        return $user !== null && ($user->is_admin || (bool) ($user->accessibleModules()->get('MAINT')?->can_approve));
    }
}
