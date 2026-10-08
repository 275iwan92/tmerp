<?php

namespace App\Support\V2\Grid\Inv;

use App\Models\Inventory\InvBatch;
use App\Models\Inventory\InvQcFirstArticle;

// First Article: hasil uji barang pertama di mesin untuk satu WO dan satu batch bahan (PASS melepas batch, FAIL menolaknya).
class FirstArticleListGrid extends QualityDocGrid
{
    public function slug(): string
    {
        return 'qc-first-article';
    }

    public function title(): string
    {
        return 'First Article';
    }

    public function icon(): string
    {
        return 'fa-solid fa-clipboard-check';
    }

    public function guide(): ?string
    {
        return 'First article results recorded at the machine. A pass releases the batch, a fail rejects it (the note is required) and holds the work orders that used it.';
    }

    public function newUrl(): ?string
    {
        return $this->url().'?new=1';
    }

    protected function sources(): array
    {
        return ['tm2inv.inv_qc_first_article', 'tm2inv.inv_batch'];
    }

    protected function listRows(): array
    {
        $rows = InvQcFirstArticle::query()->orderByDesc('id_first_article')->limit($this->limit)->get();
        $batches = InvBatch::query()->whereKey($rows->pluck('batch_id')->unique()->all())->get()->keyBy('id_batch');

        return $rows->map(fn (InvQcFirstArticle $r) => ['id' => $r->id_first_article, 'wo' => (string) $r->wo_no, 'batch' => $batches[$r->batch_id]->batch_no ?? '',
            'item' => (string) ($batches[$r->batch_id]->item_name ?? ''), 'result' => $r->result === 'PASS' ? 'Pass' : 'Fail', 'notes' => (string) $r->notes, 'by' => $r->checked_by, 'at' => $this->dt($r->checked_at)])->all();
    }

    public function columns(): array
    {
        return [
            ['key' => 'at', 'label' => 'Checked At', 'width' => '150px'],
            ['key' => 'wo', 'label' => 'Work Order', 'width' => '130px'],
            ['key' => 'batch', 'label' => 'Batch', 'width' => '120px'],
            ['key' => 'item', 'label' => 'Item'],
            ['key' => 'result', 'label' => 'Result', 'type' => 'badge', 'tones' => ['Pass' => 'success', 'Fail' => 'danger']],
            ['key' => 'notes', 'label' => 'Notes'],
            ['key' => 'by', 'label' => 'Checked By'],
        ];
    }
}
