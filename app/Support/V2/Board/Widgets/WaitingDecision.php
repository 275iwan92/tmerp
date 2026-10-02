<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use App\Support\V2\Data\ApprovalCenter;
use Devlib\Components\Dashboard;
use Devlib\Locale;

// Dokumen yang menunggu keputusan user login (antrian flow engine yang sama dengan Approval Center).
final class WaitingDecision extends Widget
{
    // layar Approval Center => kode menunya
    private const MENUS = ['co' => 'SALES_CO_APPR', 'pr' => 'PROC_PR_APPR', 'po' => 'PROC_PO_APPR', 'prt' => 'PROC_RET_APPR'];

    private const SHOW = 6;

    public function key(): string
    {
        return 'waiting_decision';
    }

    public function title(): string
    {
        return 'Waiting for Your Decision';
    }

    public function description(): string
    {
        return 'Documents in the approval queue that wait for your Approve, Reject or Hold.';
    }

    public function type(): string
    {
        return 'list';
    }

    public function icon(): string
    {
        return 'fa-stamp';
    }

    public function phrases(): array
    {
        return ['menunggu keputusan', 'menunggu approval', 'antrian approval', 'perlu approve', 'waiting for decision', 'waiting approval', 'approval queue'];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $rows = [];
        $first = null;
        foreach (self::MENUS as $screen => $menu) {
            foreach (ApprovalCenter::docs($screen) as $doc) {
                $first ??= $menu;
                $rows[] = ['title' => trim(($doc['no'] ?? '').' · '.($doc['party'] ?? ''), ' ·'),
                    'sub' => trim(t(ApprovalCenter::MODULES[ApprovalCenter::SCREENS[$screen][0]]['title']).' · '.(Locale::d($doc['date'] ?? null) ?? ''), ' ·'),
                    'value' => Dashboard::fmt((float) ($doc['amount'] ?? 0), 'money'), 'tone' => 'warn'];
            }
        }
        $count = count($rows);

        return ['caption' => $count ? raw(str_replace(':n', (string) $count, t(':n documents'))) : null, 'source' => 'tm2mst.flow_log (flow engine)',
            'data' => ['rows' => array_slice($rows, 0, self::SHOW), 'empty' => 'Nothing waits for your decision'],
            'open' => $first ? $this->open($first, 'Approval Center', 'fa-solid fa-stamp') : null];
    }
}
