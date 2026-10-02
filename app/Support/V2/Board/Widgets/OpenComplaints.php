<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sdi\CrmComplaint;
use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Komplain dan retur penjualan yang belum ditutup, yang paling lama terbuka di atas.
final class OpenComplaints extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'open_complaints', 'title' => 'Open Complaints', 'type' => 'list', 'dashboard' => 'SALES', 'module' => 'SALES',
            'description' => 'Customer complaints and sales returns that are not closed yet, oldest first.',
            'icon' => 'fa-face-frown', 'tone' => 'danger', 'phrases' => ['komplain terbuka', 'komplain customer', 'retur penjualan', 'open complaints', 'customer complaints'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $open = $n->openComplaints();
        $names = $n->customerNames($open->pluck('customer_id')->all());
        $rows = $open->take(6)->map(function (CrmComplaint $c) use ($n, $names) {
            $days = (int) $c->complaint_date?->startOfDay()->diffInDays($n->now->startOfDay());

            return ['title' => trim($c->complaint_no.' · '.($names[$c->customer_id] ?? ''), ' ·'),
                'sub' => t(CrmComplaint::CATEGORIES[$c->category] ?? (string) $c->category).' · '.Locale::d($c->complaint_date),
                'value' => $this->say(':n days', ['n' => $days]), 'tone' => $days >= 7 ? 'danger' : 'warn'];
        })->values()->all();

        return ['caption' => $open->count() ? raw($this->say(':n open', ['n' => $open->count()])) : null, 'source' => 'tm2sdi.crm_complaint',
            'data' => ['rows' => $rows, 'empty' => 'No complaint is open'], 'open' => $this->open('CRM_COMPLAINT', 'Complaint & Return', 'fa-solid fa-face-frown')];
    }
}
