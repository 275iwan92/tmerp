<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Sisa tagihan customer per umur lewat jatuh tempo, per hari ini.
final class ReceivablesAging extends Widget
{
    // label => [dari hari, sampai hari]
    private const BUCKETS = ['Not due' => [null, 0], '1-30' => [1, 30], '31-60' => [31, 60], '61-90' => [61, 90], '> 90' => [91, null]];

    public function key(): string
    {
        return 'receivables_aging';
    }

    public function title(): string
    {
        return 'Receivables Aging';
    }

    public function description(): string
    {
        return 'Unpaid customer invoices grouped by days past the due date.';
    }

    public function type(): string
    {
        return 'chart';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function icon(): string
    {
        return 'fa-hourglass-half';
    }

    public function module(): ?string
    {
        return 'FINACC';
    }

    public function phrases(): array
    {
        return ['umur piutang', 'aging piutang', 'piutang per umur', 'receivables aging', 'ar aging'];
    }

    public function dashboard(): ?string
    {
        return 'AR_AP';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $rows = $n->receivables();
        $sum = fn (array $b) => (float) $rows->filter(fn ($r) => ($b[0] === null || $r->days_overdue >= $b[0]) && ($b[1] === null || $r->days_overdue <= $b[1]))->sum('outstanding');

        return ['caption' => 'Days past due, as of today', 'source' => 'tm2acc.v1_fin_ar_aging', 'open' => $this->open('FIN_AR_INVOICE', 'Customer Invoice', 'fa-solid fa-file-invoice-dollar'),
            'data' => ['chart' => 'bar', 'format' => 'money', 'height' => '240px', 'legend' => false,
                'labels' => array_map(fn (string $l) => $l === 'Not due' ? t('Not due') : $l, array_keys(self::BUCKETS)),
                'series' => [['name' => t('Outstanding'), 'data' => array_map($sum, array_values(self::BUCKETS)), 'tone' => 'warn']]]];
    }
}
