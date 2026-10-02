<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Master\OrgPosition;
use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Job Description jabatan user sendiri, dari Master Jabatan (diisi HR).
final class MyJobDescription extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'my_job_description', 'title' => 'My Job Description', 'type' => 'text', 'dashboard' => 'DOCS', 'size' => 6,
            'description' => 'The job description of your own position, as written by HR in Master Position.',
            'icon' => 'fa-briefcase', 'phrases' => ['job description', 'jobdesc', 'uraian tugas', 'tugas saya', 'deskripsi pekerjaan'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $position = ($id = $user->currentPositionId()) ? OrgPosition::query()->find($id) : null;
        $text = trim((string) $position?->job_description);

        return ['caption' => $position ? raw($position->position_name.($position->job_description_updated_at ? ' · '.Locale::d($position->job_description_updated_at) : '')) : null,
            'source' => 'tm2mst.org_position (job_description)', 'data' => ['text' => $text,
                'empty' => $position ? 'The job description of your position is not filled in yet' : 'Your account is not linked to a position yet']];
    }
}
