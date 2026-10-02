<?php

namespace Chat\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

// Satu-satunya hal yang paket chat perlu tahu dari aplikasi pemakai: siapa saja usernya.
// Orang = ['id' => int (id auth), 'key' => string, 'name' => string, 'role' => string, 'photo' => ?string (url)].
// 'key' = nilai yang disimpan di sys_chat_messages.sender / sys_chat_participants.user_id (boleh sama dengan id).
interface Directory
{
    /** Semua orang yang boleh di-chat oleh $me (tanpa $me sendiri), sudah urut tampil. */
    public function people(Authenticatable $me): array;

    /** Satu orang menurut id auth; null = bukan user yang boleh di-chat. */
    public function person(int $id): ?array;

    public function key(Authenticatable $user): string;

    public function name(Authenticatable $user): string;
}
