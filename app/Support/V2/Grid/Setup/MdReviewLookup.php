<?php

/*
 * REMARK
 * Tujuan       : Layar Setup > Others > MD Review: Jo memutuskan tiap berkas .md (Keep / Condense / Discard) di samping usulan Claude.
 * Sumber hulu  : tm2sys.sys_md_review (diisi php artisan review:import dari docs/ringkas/md_review_tabel.md).
 * Input        : Pilihan Decision per berkas; tombol Process with Claude di header layar.
 * Output       : decision, decided_by, decided_at; antrean sys_review_request (scope MD) untuk php artisan review:requests.
 * Dipakai oleh : Menu SET_MD_REVIEW (v2:setup-md-review).
 * Status       : Aktif (ddt*). Tidak ada berkas yang dihapus dari layar ini.
 */

namespace App\Support\V2\Grid\Setup;

use App\Models\Sys\SysMdReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MdReviewLookup extends ReviewLookup
{
    private const PROPOSALS = ['KEEP' => 'Keep', 'CONDENSE' => 'Condense', 'UNSURE' => 'Unsure', 'DISCARD' => 'Discard'];

    public function slug(): string
    {
        return 'setup-md-review';
    }

    public function title(): string
    {
        return 'MD Review';
    }

    public function scope(): string
    {
        return 'MD';
    }

    public function icon(): string
    {
        return 'fa-solid fa-file-circle-check';
    }

    public function guide(): ?string
    {
        return 'Pick Keep, Condense or Discard for each file. Nothing is deleted from here. Process with Claude queues your decisions; Claude reads the queue at the start of a session and applies them, and you approve the result before anything is committed.';
    }

    protected function decisionLabels(): array
    {
        return array_diff_key(self::PROPOSALS, ['UNSURE' => 1]);
    }

    protected function query(): Builder
    {
        return SysMdReview::query()->orderByRaw("field(proposal, 'DISCARD', 'UNSURE', 'CONDENSE', 'KEEP')")->orderBy('file_path');
    }

    protected function row(Model $m): array
    {
        return ['file_path' => $m->file_path, 'line_count' => (int) $m->line_count, 'last_changed' => $m->last_changed, 'ref_count' => $m->ref_count,
            'proposal' => $m->proposal, 'reason' => $m->reason, 'decision' => (string) $m->decision];
    }

    public function stats(): array
    {
        return [
            ['label' => 'All'],
            ['label' => 'Not decided', 'where' => ['decision' => ['']], 'tone' => 'warn'],
            ['label' => 'Proposed discard', 'where' => ['proposal' => 'DISCARD'], 'tone' => 'danger'],
            ['label' => 'Unsure', 'where' => ['proposal' => 'UNSURE'], 'tone' => 'accent'],
            ['label' => 'Proposed condense', 'where' => ['proposal' => 'CONDENSE'], 'tone' => 'neutral'],
            ['label' => 'Proposed keep', 'where' => ['proposal' => 'KEEP'], 'tone' => 'success'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'file_path', 'label' => 'File', 'readonly' => true, 'width' => '400px'],
            ['key' => 'proposal', 'label' => 'Proposal', 'type' => 'select', 'options' => array_map('t', self::PROPOSALS), 'readonly' => true, 'width' => '110px'],
            $this->decisionColumn(),
            ['key' => 'reason', 'label' => 'Reason', 'readonly' => true, 'width' => '480px'],
            ['key' => 'line_count', 'label' => 'Lines', 'type' => 'number', 'readonly' => true, 'width' => '70px'],
            ['key' => 'last_changed', 'label' => 'Last changed', 'type' => 'date', 'readonly' => true, 'width' => '110px'],
            ['key' => 'ref_count', 'label' => 'References', 'type' => 'number', 'readonly' => true, 'width' => '90px'],
        ];
    }
}
