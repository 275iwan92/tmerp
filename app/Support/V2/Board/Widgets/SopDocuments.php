<?php

namespace App\Support\V2\Board\Widgets;

use App\Models\Sys\SecUser;
use App\Support\V2\Board\Numbers;
use App\Support\V2\Board\Widget;
use Devlib\Locale;

// Dokumen SOP yang berlaku (master SOP, jenis SOP), yang paling baru berlaku di atas.
final class SopDocuments extends Widget
{
    protected function meta(): array
    {
        return [
            'key' => 'sop_documents', 'title' => 'SOP', 'type' => 'list', 'dashboard' => 'DOCS', 'size' => 6,
            'description' => 'Standard Operating Procedures that are in force, newest first.',
            'icon' => 'fa-book-open', 'phrases' => ['sop', 'prosedur', 'standar operasional', 'standard operating procedure'],
        ];
    }

    public function build(Numbers $n, SecUser $user): array
    {
        $all = $n->documents('SOP');
        $rows = $all->take(8)->map(fn ($d) => ['title' => $d->title, 'sub' => trim($d->sop_code.' · '.($d->department?->department_name ?? t('All departments')).' · '.(Locale::d($d->effective_date) ?? ''), ' ·'),
            'value' => 'Rev '.$d->revision, 'tone' => 'neutral'])->values()->all();

        return ['caption' => $all->count() ? raw($this->say(':n documents', ['n' => $all->count()])) : null, 'source' => 'tm2mst.sop_document (doc_type SOP)',
            'data' => ['rows' => $rows, 'empty' => 'No SOP is registered yet'], 'open' => $this->open('HRM_SOP', 'SOP & Work Instruction', 'fa-solid fa-book-open')];
    }
}
