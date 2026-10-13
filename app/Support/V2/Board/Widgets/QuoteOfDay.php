<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Services\Sys\QuoteRotation;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

/* REMARK
 * Tujuan       : Widget Quote of the Day di opening board: satu kutipan acak per user per slot (pagi, setelah makan siang, sore/malam), tanpa ulang dalam 7 hari.
 * Sumber hulu  : tm2sys.sys_quotes lewat App\Services\Sys\QuoteRotation; catatan tampil di tm2sys.sys_quote_shown.
 * Input        : User login dan waktu sekarang.
 * Output       : Teks dan pengarang kutipan, nomor hari dalam tahun.
 * Dipakai oleh : Opening board (Board / WidgetCatalog).
 * Status       : Aktif.
 */
final class QuoteOfDay extends Widget
{
    public function key(): string
    {
        return 'quote_of_day';
    }

    public function title(): string
    {
        return 'Quote of the Day';
    }

    public function description(): string
    {
        return 'A different random quote for each part of the day, never repeated within 7 days.';
    }

    public function type(): string
    {
        return 'quote';
    }

    public function icon(): string
    {
        return 'fa-quote-left';
    }

    public function size(): int
    {
        return 12;
    }

    public function phrases(): array
    {
        return ['kata harian', 'kata motivasi', 'motivasi harian', 'quote of the day', 'daily quote'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $quote = QuoteRotation::pick($user->id_user, $n->now);

        return ['source' => 'tm2sys.sys_quotes', 'data' => ['text' => $quote?->quote_text ?? '—', 'author' => $quote?->author ?? '',
            'day' => $n->now->dayOfYear, 'days' => $n->now->isLeapYear() ? 366 : 365]];
    }
}
