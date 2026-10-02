<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Lamaran per status dan jumlah lowongan yang sedang dibuka. Referensi: recruitment pipeline (Odoo).
final class RecruitmentPipeline extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'recruitment_pipeline', 'title' => 'Recruitment Pipeline', 'type' => 'chart', 'dashboard' => 'HR', 'module' => 'HRM', 'size' => 6,
            'description' => 'Job applications by status, with the number of vacancies that are open now.',
            'icon' => 'fa-user-plus', 'phrases' => ['rekrutmen', 'lamaran', 'lowongan', 'recruitment', 'applicants', 'vacancies'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $r = $n->recruitment();
        $status = ['APPLIED' => 'Applied', 'IN_PROGRESS' => 'In progress', 'HIRED' => 'Hired', 'REJECTED' => 'Rejected'];

        return ['caption' => raw($this->say(':n open vacancies', ['n' => $r['vacancies']])), 'source' => 'tm2hrr.vac_vacancy + app_application',
            'data' => ['chart' => 'hbar', 'format' => 'number', 'height' => '240px', 'legend' => false, 'labels' => array_map(fn (string $l) => t($l), array_values($status)),
                'series' => [['name' => t('Applications'), 'data' => array_map(fn (string $s) => (int) ($r['applications'][$s] ?? 0), array_keys($status)), 'tone' => 'violet']]],
            'open' => $this->open('HRM_RCR_VACANCY', 'Vacancies', 'fa-solid fa-user-plus')];
    }
}
