<?php

namespace App\Support;

use App\Models\Sys\SysSetting;
use App\Models\Sys\SysTheme;
use Illuminate\Support\Facades\Auth;

// Resolusi tema aktif (queue.md #9d): sec_user.theme_code (kalau user login &
// kodenya valid) -> sys_setting.active_theme -> sys_theme.is_default=1 ->
// fallback statis (kalau tabel kosong/koneksi bermasalah, app tetap jalan).
class ActiveTheme
{
    public static function resolve(): SysTheme
    {
        $active = SysTheme::where('activate', true);

        $userCode = Auth::check() ? Auth::user()->theme_code : null;

        if ($userCode) {
            $theme = (clone $active)->find($userCode);

            if ($theme) {
                return $theme;
            }
        }

        $settingCode = SysSetting::find('active_theme')?->setting_value;

        if ($settingCode) {
            $theme = (clone $active)->find($settingCode);

            if ($theme) {
                return $theme;
            }
        }

        $default = (clone $active)->where('is_default', true)->first();

        if ($default) {
            return $default;
        }

        return new SysTheme([
            'code' => 'navy',
            'name' => 'Navy (default)',
            'primary_color' => '#2f6fed',
            'sidebar_color' => '#1f2742',
            'mode' => 'light',
        ]);
    }
}
