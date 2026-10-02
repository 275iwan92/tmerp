<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sdi\CrmComplaint;
use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Komplain dan retur bertanggal pada periode itu per kategori (mutu, jumlah, pengiriman, ...).
final class ComplaintsByCategory extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'complaints_by_category', 'title' => 'Complaints by Category', 'type' => 'chart', 'dashboard' => 'QUALITY', 'module' => 'PROD',
            'description' => 'Customer complaints and returns of the period by category: quality, quantity, delivery and others.',
            'icon' => 'fa-chart-pie', 'tone' => 'danger', 'phrases' => ['komplain per kategori', 'jenis komplain', 'complaints by category'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $by = $n->complaintsByCategory($n->from(), $n->to());
        $labels = array_map(fn ($c) => t(CrmComplaint::CATEGORIES[$c] ?? (string) $c), array_keys($by));

        return ['caption' => raw($n->rangeText()), 'source' => 'tm2sdi.crm_complaint', 'open' => $this->open('CRM_COMPLAINT', 'Complaint & Return', 'fa-solid fa-face-frown'),
            'data' => ['chart' => 'donut', 'format' => 'number', 'height' => '240px', 'labels' => $labels, 'series' => [['name' => t('Complaints'), 'data' => array_values($by)]]]];
    }
}
