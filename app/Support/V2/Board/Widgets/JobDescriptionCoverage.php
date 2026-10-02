<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;

// Jabatan aktif yang Job Description-nya sudah diisi, dari seluruh jabatan aktif.
final class JobDescriptionCoverage extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'jd_coverage', 'title' => 'Job Descriptions Filled', 'type' => 'kpi', 'dashboard' => 'DOCS', 'module' => 'HRM', 'size' => 6,
            'description' => 'Active positions that already have a job description, of all active positions.',
            'icon' => 'fa-list-check', 'tone' => 'teal', 'phrases' => ['jabatan tanpa job description', 'kelengkapan job description', 'job descriptions filled', 'jd coverage'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $c = $n->jobDescriptionCoverage();

        return ['source' => 'tm2mst.org_position', 'data' => ['value' => $c['filled'], 'target' => $c['positions'] > 0 ? (float) $c['positions'] : null, 'format' => 'number',
            'note' => raw($this->say(':n positions still without a job description', ['n' => max(0, $c['positions'] - $c['filled'])]))]];
    }
}
