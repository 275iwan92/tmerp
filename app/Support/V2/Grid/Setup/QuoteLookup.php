<?php

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysQuote;
use App\Support\V2\Grid\DbLookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/* REMARK
 * Tujuan       : Satu layar Setup > Dashboard and Widgets > Quotes untuk meninjau dan mengelola kutipan: tab Per Hari (kata harian, opsional) dan tab Semua Kutipan, dengan filter Famous/Anonymous, cari, dan tulisan besar.
 * Sumber hulu  : tm2sys.sys_quotes (1000 kutipan) dan tm2sys.sys_daily_quote (366 baris per tanggal, opsional).
 * Input        : Sel diubah langsung lalu Save (rute v2.lookup.save per slug); Active dimatikan = kutipan tidak dipakai.
 * Output       : Pool kutipan Active untuk rotasi per user (QuoteRotation: pagi / setelah makan siang / sore-malam, tanpa ulang 7 hari) dan kutipan acak layar login.
 * Dipakai oleh : Menu SET_QUOTES; DailyQuoteLookup tetap terdaftar untuk Save tab Per Hari (menunya disembunyikan).
 * Status       : Aktif.
 */
class QuoteLookup extends DbLookup
{
    public function slug(): string
    {
        return 'setup-quotes';
    }

    public function title(): string
    {
        return 'Quotes';
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
        return 'Dashboard and Widgets';
    }

    public function icon(): string
    {
        return 'fa-solid fa-quote-left';
    }

    public function guide(): ?string
    {
        return 'Every quote, by famous people and anonymous. Each user gets a different random quote for the morning, after lunch and the evening, '
            .'and never the same one within 7 days. Untick Active to stop using a quote; use Search to find an author.';
    }

    public function screen(): array
    {
        return ['view' => 'v2.screens.setup-quotes'] + parent::screen();
    }

    protected function query(): Builder
    {
        return SysQuote::query()->orderByRaw("author = 'Anonim'")->orderBy('author')->orderBy('id');
    }

    protected function row(Model $m): array
    {
        return parent::row($m) + ['kind' => $m->author === 'Anonim' ? 'ANON' : 'FAMOUS'];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Famous', 'where' => ['kind' => 'FAMOUS']],
            ['label' => 'Anonymous', 'where' => ['kind' => 'ANON'], 'tone' => 'neutral'],
            ['label' => 'Inactive', 'where' => ['activate' => false], 'tone' => 'danger'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'author', 'label' => 'Author', 'required' => true, 'width' => '260px'],
            ['key' => 'activate', 'label' => 'Active', 'type' => 'check', 'width' => '70px'],
            ['key' => 'quote_text', 'label' => 'Quote', 'required' => true, 'width' => '1000px'],
        ];
    }

    protected function rules(): array
    {
        return ['quote_text' => ['string', 'max:500'], 'author' => ['string', 'max:150']];
    }
}
