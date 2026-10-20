<?php

namespace App\Livewire\Shell;

use App\Models\Sys\SysChatMessage;
use App\Models\Sys\SysChatParticipant;
use App\Models\Sys\SysNotification;
use App\Models\Sys\SysUserMenu;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* REMARK
 * Tujuan       : Dropdown avatar user di layout laporan v1; My Security membuka /v2/profile?tab=security (self-service v1 dihapus 2026-10-18).
 * Sumber hulu  : sys_user_menu (tm2sys), rute v2.profile.
 * Input        : Auth::user, prop standalone.
 * Output       : Daftar item menu user dan URL halaman untuk mode standalone.
 * Dipakai oleh : layouts/report (Laporan Manajemen v1), Shell\UserPanels.
 * Status       : Aktif, menunggu laporan v1 dipindah ke v2 lalu dihapus.
 */

// Dropdown avatar/user -- diekstrak dari main-menu.blade.php (instruksi
// user 2026-09-14: "user menu di management report dan di operational
// menu harus sama", lalu screenshot dropdown lengkap "drop down ini ya
// harus muncul di management report"). Sebelumnya dropdown ini HANYA
// inline di main-menu.blade.php; sekarang komponen Livewire reusable
// dipakai KEDUA shell (main-menu.blade.php via <livewire:shell.user-menu />,
// layouts/report.blade.php via <livewire:shell.user-menu :standalone="true" />)
// supaya benar-benar SATU implementasi, bukan 2 salinan yang bisa menyimpang.
//
// $standalone=true (layouts::report): panel drawer/modal dibuka DI TEMPAT
// lewat App\Livewire\Shell\UserPanels (sama dgn main-menu); item yg di
// Operasional membuka tab MDI/halaman penuh dibuka di modal halaman --
// user TIDAK dilempar ke Menu Operasional (keluhan user 2026-09-18).
class UserMenu extends Component
{
    public bool $standalone = false;

    public function standalonePageUrl(object $item): ?string
    {
        if ($item->code === 'UM_MYSECURITY') {
            return route('v2.profile', ['tab' => 'security']);
        }

        return $item->action_type === 'menu'
            ? match ($item->action_target) {
                'SET_USER' => route('v2.screen', 'security-accounts'),
                'SET_GROUP' => route('v2.screen', 'security-groups'),
                default => null,
            }
        : null;
    }

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

        // Jabatan pegawai dulu; nama grup hak akses hanya cadangan.
        if ($title = $user->jobTitle()) {
            return $title;
        }

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
            })
            ->filter(fn ($item) => $item->action_type !== 'menu' || $this->menuTargetAllowed($item->action_target));
    }

    // Item bertipe 'menu' membuka layar lain; kalau layar itu tidak boleh dibuka
    // user ini, item-nya disembunyikan -- dulu tetap tampil tapi klik tidak
    // berbuat apa-apa (QC user 2026-09-23: "Aktivitas saya" -> SET_USER, layar
    // Security yang ber-middleware admin, mati utk semua non-admin).
    protected function menuTargetAllowed(string $code): bool
    {
        return match ($code) {
            'SET_USER', 'SET_GROUP', 'SET_PERM', 'SET_MIGRATION' => (bool) Auth::user()->is_admin,
            default => true,
        };
    }

    public function logout()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
