<?php

namespace App\Assistant;

use App\Models\Sys\SecUser;
use Assistant\Contracts\Asker;
use Devlib\Locale;

// Penanya asisten = user security ERP (user 2026-10-02: "pakai level di user security sebagai acuan AI lokal ini").
// Level = sec_group.mgmt_level tertinggi milik user; hak modul = SecUser::canAccessModule() (izin grup + level minimum modul).
// Yang boleh membuka modul Laporan Manajemen (MGMT: Direksi, General Manager) boleh menanyakan angka SEMUA bagian --
// modul itu memang laporan lintas bagian, walau menu operasional bagian lain tidak terbuka baginya.
final class ErpAsker implements Asker
{
    // Administrator melewati semua gerbang (sama dengan canAccessModule).
    private const ADMIN_LEVEL = 99;

    private const MANAGEMENT_REPORTS = 'MGMT';

    public function __construct(public readonly SecUser $user) {}

    public function id(): string
    {
        return (string) $this->user->id_user;
    }

    public function name(): string
    {
        return (string) ($this->user->name ?: $this->user->login);
    }

    public function lang(): string
    {
        return Locale::lang();
    }

    public function level(): int
    {
        return $this->user->is_admin ? self::ADMIN_LEVEL : $this->user->maxMgmtLevel();
    }

    public function can(string $module): bool
    {
        return $this->user->canAccessModule($module) || $this->user->canAccessModule(self::MANAGEMENT_REPORTS);
    }
}
