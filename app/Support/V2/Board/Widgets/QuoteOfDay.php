<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Models\Sys\SysDailyQuote;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Carbon\CarbonImmutable;

// Kata harian (Setup > Daily Quotes): tanggal yang sama = baris yang sama tiap tahun (hari ke-n kalender tahun kabisat).
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
        return 'One line for every day of the year.';
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
        $day = CarbonImmutable::create(2024, $n->now->month, $n->now->day)->dayOfYear;
        $quote = SysDailyQuote::query()->where('day_no', $day)->where('activate', true)->first();

        return ['source' => 'tm2sys.sys_daily_quote', 'data' => ['text' => $quote?->quote_text ?? '—', 'author' => $quote?->author ?? '',
            'day' => $n->now->dayOfYear, 'days' => $n->now->isLeapYear() ? 366 : 365]];
    }
}
