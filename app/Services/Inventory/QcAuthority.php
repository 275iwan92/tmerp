<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Sys\SecUser;

// Siapa boleh melakukan apa pada alur QC / URGENT / kurang kirim (keputusan D16 dan O-15). Satu tempat, supaya layar dan service sama.
// Kode grup yang belum ada di sec_group (PROD_SPV, QA_HEAD, WH_HEAD) sudah dikenali: begitu user membuat grupnya (Setup > Security), haknya langsung berlaku.
// Admin dan Ops Manager ke atas (level >= 4, grup OPS_MGR / DIREKSI) selalu boleh.
final class QcAuthority
{
    /** PPIC, Supervisor Produksi dan Manager Produksi boleh menandai penerimaan URGENT. */
    public const URGENT_MARK = ['PROD_PLANNING', 'PROD_SPV', 'PROD_MGR'];

    /** Kepala QC menyetujui URGENT bahan FSC / food grade (selain Ops Manager ke atas). */
    public const URGENT_APPROVE = ['QA_HEAD'];

    /** Memutuskan Release / Reject batch. */
    public const QC_DECIDE = ['QA', 'QA_HEAD'];

    /** Kepala Gudang menghitung ulang bahan yang diduga kurang kirim. */
    public const RECOUNT = ['MGR_INV', 'WH_HEAD'];

    /** PPIC mengonfirmasi permintaan kirim kekurangan. */
    public const SHORTAGE_CONFIRM = ['PROD_PLANNING', 'PROD_MGR'];

    /** Pembelian menindaklanjuti (kirim ke pemasok, catat hasil). */
    public const SHORTAGE_FOLLOW_UP = ['MGR_PUR'];

    public static function user(string|SecUser|null $who): ?SecUser
    {
        return $who instanceof SecUser || $who === null ? $who : SecUser::query()->where('login', $who)->first();
    }

    public static function canMarkUrgent(string|SecUser|null $who): bool
    {
        return self::has($who, self::URGENT_MARK);
    }

    public static function canApproveUrgent(string|SecUser|null $who): bool
    {
        return self::has($who, self::URGENT_APPROVE);
    }

    public static function canDecide(string|SecUser|null $who): bool
    {
        return self::has($who, self::QC_DECIDE);
    }

    public static function canRecount(string|SecUser|null $who): bool
    {
        return self::has($who, self::RECOUNT);
    }

    public static function canConfirmShortage(string|SecUser|null $who): bool
    {
        return self::has($who, self::SHORTAGE_CONFIRM);
    }

    public static function canFollowUp(string|SecUser|null $who): bool
    {
        $user = self::user($who);

        return $user !== null && (self::has($user, self::SHORTAGE_FOLLOW_UP) || $user->canAccessModule('PROC'));
    }

    /** @param  list<string>  $groups */
    private static function has(string|SecUser|null $who, array $groups): bool
    {
        $user = self::user($who);

        return $user !== null && ($user->isOpsManagerOrAbove() || $user->hasGroup(...$groups));
    }
}
