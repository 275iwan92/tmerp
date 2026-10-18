<?php

/*
 * REMARK
 * Tujuan       : Siapa boleh mengubah master Maintenance dan dokumen SOP / WI.
 * Sumber hulu  : SecUser::accessibleModules, SecUser::isHrOfficer.
 * Input        : Pengguna.
 * Output       : canManage (kepala Maintenance atau admin), canManageDocuments (HR Officer atau admin).
 * Dipakai oleh : Rute v2_maintenance, layar Maintenance, DocumentListGrid.
 * Status       : Aktif. canManageDocuments ditambah 2026-10-18 (Maintenance hanya melihat dokumen).
 */

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Models\Sys\SecUser;

// Siapa boleh mengubah master Maintenance: administrator atau pemegang hak approve modul MAINT (kepala maintenance).
// Teknisi dan staf produksi hanya melihat. Dokumen SOP / Work Instruction (satu register dengan dokumen perusahaan) hanya diubah HR Officer.
final class MaintenanceAccess
{
    public static function canManage(?SecUser $user): bool
    {
        return $user !== null && ($user->is_admin || (bool) ($user->accessibleModules()->get('MAINT')?->can_approve));
    }

    public static function canManageDocuments(?SecUser $user): bool
    {
        return $user !== null && $user->isHrOfficer();
    }
}
