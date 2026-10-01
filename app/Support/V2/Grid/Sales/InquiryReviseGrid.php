<?php

namespace App\Support\V2\Grid\Sales;

// Inquiry > Revise: meja Sales untuk inquiry jenis REVISE. Dipakai layar v2 inquiry-revise (daftar + form dalam satu layar),
// jadi bukan layar tersendiri di Grids::ALL.
class InquiryReviseGrid extends InquiryNewGrid
{
    public function slug(): string
    {
        return 'inquiry-'.strtolower($this->type());
    }

    public function title(): string
    {
        return 'Inquiry '.self::TYPE_LABELS[$this->type()];
    }

    protected function type(): string
    {
        return 'REVISE';
    }

    protected function rowUrl(array $r): string
    {
        return route('v2.screen', $this->slug()).'?id='.$r['id'];
    }

    // + New Record = pilih product dari Master Product dulu.
    public function newUrl(): ?string
    {
        return route('v2.screen', $this->slug()).'?pick=1';
    }

    // Product Type diganti keterangan khas dokumen turunan: scope revisi (REVISE saja) + inquiry asal.
    public function columns(): array
    {
        $extra = array_values(array_filter([
            $this->type() === 'REVISE' ? ['key' => 'scope', 'label' => 'Revision Scope'] : null,
            ['key' => 'source', 'label' => 'Source Inquiry'],
        ]));

        return collect(parent::columns())->flatMap(fn ($c) => $c['key'] === 'part_type' ? $extra : [$c])->all();
    }
}
