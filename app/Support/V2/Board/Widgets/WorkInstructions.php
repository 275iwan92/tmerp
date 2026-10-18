<?php

/*
 * REMARK
 * Tujuan       : Widget daftar Work Instruction yang berlaku.
 * Sumber hulu  : tm2mnt.mnt_document (+ mnt_document_line), register SOP / WI tunggal sejak 2026-10-18 (sop_document dilebur).
 * Input        : Numbers::documents(WI).
 * Output       : Baris widget.
 * Dipakai oleh : Opening Board dashboard DOCS.
 * Status       : Aktif.
 */

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
        $rows = $all->take(8)->map(fn ($d) => ['title' => $d->title, 'sub' => trim($d->doc_no.' · '.($d->department?->department_name ?? t('All departments')).' · '.(Locale::d($d->effective_date) ?? ''), ' ·'),
            'value' => 'Rev '.$d->revision, 'tone' => 'neutral'])->values()->all();

        return ['caption' => $all->count() ? raw($this->say(':n documents', ['n' => $all->count()])) : null, 'source' => 'tm2mnt.mnt_document (kind WI)',
            'data' => ['rows' => $rows, 'empty' => 'No Work Instruction is registered yet'], 'open' => $this->open('HRM_SOP', 'SOP & Work Instruction', 'fa-solid fa-book-open')];
    }
}
