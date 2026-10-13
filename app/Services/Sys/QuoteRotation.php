<?php

namespace App\Services\Sys;

use App\Models\Sys\SysQuote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Pilih satu kutipan per user per slot (pagi, setelah makan siang, sore/malam) secara acak, tanpa kutipan sama dalam 7 hari.
 * Sumber hulu  : tm2sys.sys_quotes (pool Active, anonim dan tokoh) dan tm2sys.sys_quote_shown (catatan yang sudah diterima user).
 * Input        : id_user dan waktu sekarang.
 * Output       : Baris SysQuote untuk slot itu; slot yang sama di hari yang sama selalu mengembalikan kutipan yang sama (dicatat).
 * Dipakai oleh : Widget Quote of the Day (App\Support\V2\Board\Widgets\QuoteOfDay).
 * Status       : Aktif.
 */
final class QuoteRotation
{
    public const NO_REPEAT_DAYS = 7;

    // Batas jam slot (asumsi, belum disetujui Jo): sebelum 12:00 PAGI, sebelum 17:00 SIANG, sisanya MALAM.
    public static function slot(CarbonImmutable $now): string
    {
        return match (true) {
            $now->hour < 12 => 'MORNING',
            $now->hour < 17 => 'AFTERNOON',
            default => 'EVENING',
        };
    }

    public static function pick(int $userId, CarbonImmutable $now): ?SysQuote
    {
        $slot = self::slot($now);
        $date = $now->toDateString();
        $log = DB::connection('tm2sys')->table('sys_quote_shown');

        $done = (clone $log)->where(['id_user' => $userId, 'shown_on' => $date, 'slot' => $slot])->value('id_quote');
        if (! $done) {
            // Hasil dipilih deterministik dari hash(user, tanggal, slot) di antara kutipan Active yang belum diterima user dalam 7 hari terakhir.
            $recent = (clone $log)->where('id_user', $userId)->where('shown_on', '>=', $now->subDays(self::NO_REPEAT_DAYS)->toDateString())->pluck('id_quote');
            $pool = SysQuote::query()->where('activate', true)->whereNotIn('id', $recent)->orderBy('id')->pluck('id');
            if ($pool->isEmpty()) {
                return null;
            }
            $done = $pool[crc32("$userId|$date|$slot") % $pool->count()];
            (clone $log)->insertOrIgnore(['id_user' => $userId, 'id_quote' => $done, 'slot' => $slot, 'shown_on' => $date]);
            $done = (clone $log)->where(['id_user' => $userId, 'shown_on' => $date, 'slot' => $slot])->value('id_quote');
        }

        return SysQuote::query()->find($done);
    }
}
