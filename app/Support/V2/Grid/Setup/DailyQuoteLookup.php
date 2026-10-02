<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysDailyQuote;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;

// Setup > Daily Quotes: kata harian opening screen, satu baris per hari dalam tahun (1 = 1 Januari, 60 = 29 Februari, 366 = 31 Desember).
class DailyQuoteLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-daily-quotes';
    }

    public function title(): string
    {
        return 'Daily Quotes';
    }

    public function module(): string
    {
        return 'SETUP';
    }

    public function moduleLabel(): string
    {
        return 'Setup';
    }

    public function group(): string
    {
        return 'Settings';
    }

    public function icon(): string
    {
        return 'fa-solid fa-quote-left';
    }

    public function guide(): ?string
    {
        return 'The quote shown on the opening board, one row per day of the year. Day 1 = 1 January, day 60 = 29 February, day 366 = 31 December; '
            .'the same date shows the same row every year.';
    }

    protected function query(): Builder
    {
        return SysDailyQuote::query()->orderBy('day_no');
    }

    public function columns(): array
    {
        return [
            ['key' => 'day_no', 'label' => 'Day', 'type' => 'number', 'required' => true, 'unique' => true, 'width' => '80px'],
            ['key' => 'quote_text', 'label' => 'Quote', 'required' => true, 'width' => '760px'],
            ['key' => 'author', 'label' => 'Author', 'width' => '200px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
        ];
    }

    protected function rules(): array
    {
        return ['day_no' => ['integer', 'min:1', 'max:366'], 'quote_text' => ['string', 'max:500'], 'author' => ['nullable', 'string', 'max:100']];
    }
}
