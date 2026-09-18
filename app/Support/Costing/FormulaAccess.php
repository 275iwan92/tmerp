<?php

namespace App\Support\Costing;

use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\Auth;

// Siapa yang boleh melihat breakdown rumus & HPP ProCost (Sisi A rahasia, CLAUDE.md §4).
// Daftar login = konstanta PROCOST_FORMULA_LOGINS (dipisah koma), bukan hardcode --
// keputusan user 2026-09-18: johiwan, Wiwik Ardiatno (wiwik2), Carrij Suhendra
// (belum punya akun -- tambahkan login-nya ke konstanta setelah akun dibuat).
class FormulaAccess
{
    public static function logins(): array
    {
        $raw = SysConstant::text('PROCOST_FORMULA_LOGINS', 'johiwan') ?? 'johiwan';

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function canSee(): bool
    {
        $login = Auth::user()?->login;

        return $login !== null && in_array($login, self::logins(), true);
    }
}
