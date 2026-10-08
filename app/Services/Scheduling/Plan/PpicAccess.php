<?php

declare(strict_types=1);

namespace App\Services\Scheduling\Plan;

use App\Models\Sys\SecUser;

/**
 * The two PPIC access flags (rows of sec_module without a menu, assigned to groups in Security): PPIC_SCHEDULING changes queues, priorities and
 * the plan (run, confirm, move, lock); PPIC_INVENTORY changes bookings, shortage follow-up and ready dates. Both read everything; one user may hold both.
 * Administrators hold both.
 */
final class PpicAccess
{
    public const SCHEDULING = 'PPIC_SCHEDULING';

    public const INVENTORY = 'PPIC_INVENTORY';

    public static function canSchedule(?SecUser $user): bool
    {
        return self::holds($user, self::SCHEDULING);
    }

    public static function canInventory(?SecUser $user): bool
    {
        return self::holds($user, self::INVENTORY);
    }

    private static function holds(?SecUser $user, string $flag): bool
    {
        return $user !== null && ($user->is_admin || (bool) ($user->accessibleModules()->get($flag)?->can_input));
    }
}
