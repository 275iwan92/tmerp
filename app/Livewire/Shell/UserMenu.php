<?php

namespace App\Livewire\Shell;

use App\Models\Sys\SysChatMessage;
use App\Models\Sys\SysChatParticipant;
use App\Models\Sys\SysLanguage;
use App\Models\Sys\SysNotification;
use App\Models\Sys\SysTheme;
use App\Models\Sys\SysUserMenu;
use App\Support\ActiveTheme;
use App\Support\Localization\Translator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

// Dropdown avatar/user -- diekstrak dari main-menu.blade.php (instruksi
// user 2026-09-14: "user menu di management report dan di operational
// menu harus sama", lalu screenshot dropdown lengkap "drop down ini ya
// harus muncul di management report"). Sebelumnya dropdown ini HANYA
// inline di main-menu.blade.php; sekarang komponen Livewire reusable
// dipakai KEDUA shell (main-menu.blade.php via <livewire:shell.user-menu />,
// layouts/report.blade.php via <livewire:shell.user-menu :standalone="true" />)
// supaya benar-benar SATU implementasi, bukan 2 salinan yang bisa menyimpang.
//
// $standalone=true (dipakai di layouts::report, TIDAK punya modal/drawer/
// tab MDI seperti main-menu) -- item Chat/Notifikasi/Asisten AI/Edit
// profil/Ubah password/User Configuration/Aktivitas saya dialihkan jadi
// link ke route('menu', ['open' => target]); main-menu.blade.php baca
// query 'open' itu (x-init di root shell) utk auto-buka panel yg sesuai
// begitu mendarat -- supaya tetap FUNGSIONAL, bukan tombol mati.
class UserMenu extends Component
{
    public bool $standalone = false;

    #[Computed]
    public function unreadChatCount(): int
    {
        $login = Auth::user()->login;

        return SysChatParticipant::where('user_id', $login)
            ->get()
            ->filter(function (SysChatParticipant $p) {
                $lastMsg = SysChatMessage::where('conversation_id', $p->conversation_id)->max('sent_at');

                return $lastMsg && (! $p->last_read_at || $p->last_read_at->lt($lastMsg));
            })
            ->count();
    }

    #[Computed]
    public function unreadNotifCount(): int
    {
        return SysNotification::forUser(Auth::user()->login)->where('is_read', false)->count();
    }

    public function roleLabel(): string
    {
        $user = Auth::user();

        if ($user->is_admin) {
            return 'Administrator';
        }

        $names = $user->groups()->pluck('group_name');

        return $names->isNotEmpty() ? $names->implode(', ') : 'Pengguna';
    }

    #[Computed]
    public function userMenuItems()
    {
        return $this->visibleUserMenuItems(['Pesan & Info', 'Akun'])->groupBy('section');
    }

    #[Computed]
    public function configMenuItems()
    {
        return $this->visibleUserMenuItems(['Configuration']);
    }

    protected function visibleUserMenuItems(array $sections)
    {
        $user = Auth::user();

        return SysUserMenu::query()
            ->where('activate', 1)
            ->whereIn('section', $sections)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($item) => match ($item->visibility) {
                'employee_linked' => (bool) $user->employee_id,
                'non_employee_linked' => ! $user->employee_id,
                'setup_non_admin' => $user->canSetup() && ! $user->is_admin,
                default => true,
            });
    }

    #[Computed]
    public function availableLanguages()
    {
        $flags = ['id' => '🇮🇩', 'en' => '🇬🇧'];

        return SysLanguage::orderByDesc('is_default')->orderBy('code')->get()
            ->map(fn ($l) => ['code' => $l->code, 'name' => $l->name, 'flag' => $flags[$l->code] ?? null]);
    }

    #[Computed]
    public function themeMatrix(): array
    {
        $themes = SysTheme::where('activate', true)->orderBy('sort_order')->get();

        $families = ['pastel' => 'Pastel', 'cerah' => 'Cerah', 'dark' => 'Dark', 'hitam' => 'Hitam'];
        $hues = ['blue' => 'B', 'green' => 'G', 'red' => 'R'];

        $matrix = [];
        foreach ($families as $fKey => $fLabel) {
            $cells = [];
            foreach ($hues as $hKey => $hLabel) {
                $t = $themes->first(fn ($x) => $x->family === $fKey && $x->hue === $hKey);
                $cells[] = $t ? ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color] : null;
            }
            $matrix[] = ['label' => $fLabel, 'cells' => $cells];
        }

        $classic = $themes->whereNull('family')
            ->map(fn ($t) => ['code' => $t->code, 'name' => $t->name, 'primary' => $t->primary_color, 'mode' => $t->mode])
            ->values()->all();

        return ['families' => array_values($families), 'hues' => array_values($hues), 'matrix' => $matrix, 'classic' => $classic];
    }

    public function currentThemeCode(): string
    {
        return ActiveTheme::resolve()->code;
    }

    public function setTheme(string $code): void
    {
        if (! SysTheme::where('code', $code)->where('activate', true)->exists()) {
            return;
        }

        Auth::user()->update(['theme_code' => $code]);
    }

    public function currentLangCode(): string
    {
        return Translator::lang();
    }

    public function setLang(string $code): void
    {
        Translator::setLang($code);
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
