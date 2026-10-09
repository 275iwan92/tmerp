<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvRepostLog;

// Repost: jurnal koreksi bertumpuk (D18). Jurnal asal tidak pernah diubah; tiap koreksi, pembalikan, atau penilaian ulang menjadi jurnal baru dan tampil di sini
// dengan nilai sebelum / sesudah dan selisihnya.
class RepostReport extends ValuationReport
{
    public const KINDS = ['CORRECTION' => 'Correction', 'REVERSAL' => 'Reversal', 'REVALUATION' => 'Revaluation'];

    public function slug(): string
    {
        return 'inv-repost';
    }

    public function title(): string
    {
        return 'Repost';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clock-rotate-left';
    }

    public function guide(): ?string
    {
        return 'Journals corrected after they were posted. The original journal stays as it was; each correction, reversal or revaluation is a new journal stacked on top. '
            .'Before / After are the original and the corrected amount, Difference is what the new journal moved.';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_repost_log', 'tm2acc.fin_voucher'];
    }

    protected function listRows(): array
    {
        [$from, $to] = $this->period();

        return InvRepostLog::query()->whereBetween('posted_on', [$from->toDateString(), $to->toDateString()])->orderByDesc('id_repost')->get()
            ->map(fn (InvRepostLog $r) => ['id' => $r->id_repost, 'date' => $r->posted_on->toDateString(), 'kind' => self::KINDS[$r->repost_kind] ?? $r->repost_kind,
                'source' => trim(($r->source_type ?? '').' '.($r->source_no ?? '')), 'original' => (string) $r->original_voucher_no, 'correction' => (string) $r->correction_voucher_no,
                'before' => $r->amount_before, 'after' => $r->amount_after, 'delta' => $r->amount_delta, 'reason' => (string) $r->reason, 'by' => (string) $r->created_by])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'kind', 'label' => 'Type', 'type' => 'badge', 'tones' => ['Correction' => 'warn', 'Reversal' => 'danger', 'Revaluation' => 'info']],
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'original', 'label' => 'Original Journal', 'width' => '170px'],
            ['key' => 'correction', 'label' => 'Correction Journal', 'width' => '170px'],
            ['key' => 'before', 'label' => 'Before', 'type' => 'money'],
            ['key' => 'after', 'label' => 'After', 'type' => 'money'],
            ['key' => 'delta', 'label' => 'Difference', 'type' => 'money', 'sum' => true],
            ['key' => 'reason', 'label' => 'Reason'],
            ['key' => 'by', 'label' => 'Posted By', 'visible' => false],
        ];
    }

    public function stats(): array
    {
        return [['label' => 'All'], ['label' => 'Correction', 'where' => ['kind' => 'Correction'], 'tone' => 'warn'], ['label' => 'Reversal', 'where' => ['kind' => 'Reversal'], 'tone' => 'danger'],
            ['label' => 'Revaluation', 'where' => ['kind' => 'Revaluation'], 'tone' => 'info']];
    }

    public function filters(): array
    {
        return ['kind' => 'Type'];
    }
}
