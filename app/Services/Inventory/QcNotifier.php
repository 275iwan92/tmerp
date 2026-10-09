<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysNotification;

// Pemberitahuan QC / kurang kirim ke peran terkait lewat sys_notifications (popup lonceng yang sudah ada).
// Penerima = pengguna aktif yang tergabung di salah satu grup (bukan admin otomatis, supaya tidak ramai). Satu pesan yang sama
// (judul + isi) tidak dikirim dua kali ke pengguna yang sama, jadi perintah terjadwal aman dijalankan berulang.
final class QcNotifier
{
    /** @param  list<string>  $groups  kode grup, mis. QcAuthority::RECOUNT @return int jumlah pemberitahuan baru */
    public static function toGroups(array $groups, string $title, string $message, string $type = 'warning', ?string $link = null): int
    {
        $sent = 0;
        foreach (self::recipients($groups) as $login) {
            if (SysNotification::query()->where('user_id', $login)->where('title', $title)->where('message', $message)->exists()) {
                continue;
            }
            SysNotification::query()->create(['user_id' => $login, 'title' => mb_substr($title, 0, 150), 'message' => mb_substr($message, 0, 500), 'type' => $type, 'link' => $link, 'is_read' => false]);
            $sent++;
        }

        return $sent;
    }

    /** @param  list<string>  $groups @return list<string> login pengguna aktif di grup-grup itu */
    public static function recipients(array $groups): array
    {
        return SecUser::query()->where('activate', true)->whereHas('groups', fn ($q) => $q->whereIn('sec_group.group_code', $groups))->pluck('login')->all();
    }
}
