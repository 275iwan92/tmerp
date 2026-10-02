<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Components\Dashboard;

// Sisa tagihan customer yang sudah lewat jatuh tempo, per hari ini. Batas atasnya = target OVERDUE_RECEIVABLES bulan berjalan.
final class OverdueReceivables extends Widget
{
    public function key(): string
    {
        return 'overdue_receivables';
    }

    public function title(): string
    {
        return 'Overdue Receivables';
    }

    public function description(): string
    {
        return 'Unpaid customer invoices that are past their due date, as of today.';
    }

    public function type(): string
    {
        return 'kpi';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function icon(): string
    {
        return 'fa-file-invoice-dollar';
    }

    public function tone(): string
    {
        return 'danger';
    }

    public function size(): int
    {
        return 3;
    }

    public function module(): ?string
    {
        return 'FINACC';
    }

    public function phrases(): array
    {
        return ['piutang jatuh tempo', 'piutang lewat jatuh tempo', 'tagihan telat', 'overdue receivables', 'overdue invoices'];
    }

    public function dashboard(): ?string
    {
        return 'AR_AP';
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $late = $n->receivables()->where('days_overdue', '>', 0);
        $limit = $n->target('OVERDUE_RECEIVABLES', $n->month());
        $hint = str_replace(':n', (string) $late->count(), t(':n invoices past due'));
        if ($limit !== null) {
            $hint .= ' · '.str_replace(':v', Dashboard::fmt($limit, 'money'), t('limit :v'));
        }

        return ['source' => 'tm2acc.v1_fin_ar_aging · tm2sys.sys_kpi_target', 'data' => ['value' => $n->overdue(), 'format' => 'money', 'good_when' => 'down', 'hint' => raw($hint)]];
    }
}
