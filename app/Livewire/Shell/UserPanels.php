<?php

namespace App\Livewire\Shell;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysChatConversation;
use App\Models\Sys\SysChatMessage;
use App\Models\Sys\SysChatParticipant;
use App\Models\Sys\SysNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Component;

// Panel milik dropdown user (drawer Notifikasi/Chat/Asisten AI + modal Edit
// profil/Ubah password/User Configuration) -- SATU implementasi dipakai
// Menu Operasional (main-menu.blade.php) DAN Laporan Manajemen
// (layouts/report.blade.php). Buka/tutup dikendalikan flag Alpine
// notifOpen/chatOpen/aiOpen/profileOpen/passwordOpen/portalOpen milik
// x-data shell masing-masing -- shell Laporan tidak lagi melempar user ke
// Menu Operasional utk membuka panel ini (keluhan user 2026-09-18).
class UserPanels extends Component
{
    public bool $standalone = false;

    public string $profile_name = '';

    public string $profile_email = '';

    public string $profile_phone = '';

    public bool $profileSaved = false;

    public string $old_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public ?string $passwordError = null;

    public bool $passwordSaved = false;

    public ?int $activeConversationId = null;

    public string $chatInput = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->profile_name = $user->name ?? '';
        $this->profile_email = $user->email ?? '';
        $this->profile_phone = $user->phone ?? '';
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

    public function saveProfile(): void
    {
        $this->validate([
            'profile_name' => ['required', 'string', 'max:255'],
            'profile_email' => ['required', 'email', 'max:255'],
            'profile_phone' => ['nullable', 'string', 'max:64'],
        ]);

        Auth::user()->update([
            'name' => $this->profile_name,
            'email' => $this->profile_email,
            'phone' => $this->profile_phone,
        ]);

        $this->profileSaved = true;
    }

    public function savePassword(): void
    {
        $this->passwordError = null;
        $this->passwordSaved = false;

        $user = Auth::user();

        if (! Hash::check($this->old_password, (string) $user->pswd)) {
            $this->passwordError = 'Password lama salah.';

            return;
        }

        $this->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'pswd' => Hash::make($this->new_password),
            'pswd_last_updated' => now(),
        ]);

        $this->old_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
        $this->passwordSaved = true;
    }

    #[Computed]
    public function notifications()
    {
        return SysNotification::forUser(Auth::user()->login)->orderByDesc('created_at')->limit(30)->get();
    }

    public function markNotifRead(int $id)
    {
        $notif = SysNotification::findOrFail($id);
        $notif->update(['is_read' => true]);
        unset($this->notifications);

        if ($notif->link) {
            return $this->redirect($notif->link);
        }
    }

    public function markAllNotifRead(): void
    {
        SysNotification::forUser(Auth::user()->login)->where('is_read', false)->update(['is_read' => true]);
        unset($this->notifications);
    }

    #[Computed]
    public function conversations()
    {
        $login = Auth::user()->login;
        $ids = SysChatParticipant::where('user_id', $login)->pluck('conversation_id');

        return SysChatConversation::query()
            ->whereIn('id', $ids)
            ->with(['messages' => fn ($q) => $q->orderByDesc('sent_at')->limit(1)])
            ->get()
            ->map(function (SysChatConversation $c) use ($login) {
                $title = $c->title;

                if (! $c->is_group) {
                    $other = SysChatParticipant::where('conversation_id', $c->id)->where('user_id', '!=', $login)->first();
                    $title = $other ? (SecUser::where('login', $other->user_id)->value('name') ?? $other->user_id) : 'Percakapan';
                }

                return (object) [
                    'id' => $c->id,
                    'title' => $title ?: 'Percakapan',
                    'last' => $c->messages->first()?->body,
                ];
            })
            ->values();
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;

        SysChatParticipant::where('conversation_id', $id)
            ->where('user_id', Auth::user()->login)
            ->update(['last_read_at' => now()]);

        unset($this->conversations);
    }

    #[Computed]
    public function activeMessages()
    {
        if (! $this->activeConversationId) {
            return collect();
        }

        return SysChatMessage::where('conversation_id', $this->activeConversationId)->orderBy('sent_at')->get();
    }

    public function sendMessage(): void
    {
        $body = trim($this->chatInput);

        if ($body === '' || ! $this->activeConversationId) {
            return;
        }

        SysChatMessage::create([
            'conversation_id' => $this->activeConversationId,
            'sender' => Auth::user()->login,
            'body' => $body,
        ]);

        SysChatParticipant::where('conversation_id', $this->activeConversationId)
            ->where('user_id', Auth::user()->login)
            ->update(['last_read_at' => now()]);

        $this->chatInput = '';
        unset($this->activeMessages, $this->conversations);
    }
}
