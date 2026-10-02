<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Dokumen WI yang berlaku (master SOP, jenis WI), yang paling baru berlaku di atas.
final class WorkInstructions extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'work_instructions', 'title' => 'Work Instructions', 'type' => 'list', 'dashboard' => 'DOCS', 'size' => 6,
            'description' => 'Work Instructions that are in force, newest first.',
            'icon' => 'fa-screwdriver-wrench', 'phrases' => ['work instruction', 'instruksi kerja', 'wi', 'petunjuk kerja'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $all = $n->documents('WI');
        $rows = $all->take(8)->map(fn ($d) => ['title' => $d->title, 'sub' => trim($d->sop_code.' · '.($d->department?->department_name ?? t('All departments')).' · '.(Locale::d($d->effective_date) ?? ''), ' ·'),
            'value' => 'Rev '.$d->revision, 'tone' => 'neutral'])->values()->all();

        return ['caption' => $all->count() ? raw($this->say(':n documents', ['n' => $all->count()])) : null, 'source' => 'tm2mst.sop_document (doc_type WI)',
            'data' => ['rows' => $rows, 'empty' => 'No Work Instruction is registered yet'], 'open' => $this->open('HRM_SOP', 'SOP & Work Instruction', 'fa-solid fa-book-open')];
    }
}
