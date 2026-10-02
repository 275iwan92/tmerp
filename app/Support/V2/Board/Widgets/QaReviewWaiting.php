<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sdi\CrmComplaint;
use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Komplain yang perlu jawaban mutu (QA) dan belum punya review yang di-Confirm, yang paling lama di atas.
final class QaReviewWaiting extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'qa_review_waiting', 'title' => 'Waiting for QA Review', 'type' => 'list', 'dashboard' => 'QUALITY', 'module' => 'PROD',
            'description' => 'Complaints that need a quality answer from QA and have no confirmed review yet, oldest first.',
            'icon' => 'fa-user-doctor', 'tone' => 'warn', 'phrases' => ['review qa', 'menunggu qa', 'jawaban mutu', 'qa review', 'waiting for qa'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $all = $n->qaWaiting();
        $names = $n->customerNames($all->pluck('customer_id')->all());
        $rows = $all->take(6)->map(function (CrmComplaint $c) use ($n, $names) {
            $days = (int) $c->complaint_date?->startOfDay()->diffInDays($n->now->startOfDay());

            return ['title' => trim($c->complaint_no.' · '.($names[$c->customer_id] ?? ''), ' ·'), 'sub' => t(CrmComplaint::CATEGORIES[$c->category] ?? (string) $c->category).' · '.Locale::d($c->complaint_date),
                'value' => $this->say(':n days', ['n' => $days]), 'tone' => $days >= 7 ? 'danger' : 'warn'];
        })->values()->all();

        return ['caption' => $all->count() ? raw($this->say(':n complaints', ['n' => $all->count()])) : null, 'source' => 'tm2sdi.crm_complaint · tm2prd.qc_complaint_review',
            'data' => ['rows' => $rows, 'empty' => 'No complaint waits for a QA review'], 'open' => $this->open('PROD_QC_COMPLAINT', 'Complaint Review (QA)', 'fa-solid fa-user-doctor')];
    }
}
