<?php

namespace MockupParts\Components\Prodev;

use Devlib\Components\DevlibComponent;

// ProDev "Imposition Plano Optimizer" (tmerp pages/production/prodev.blade.php, zona beku: flow & data SAMA).
// Satu part per layar: nesting manual (drag/putar/cermin), Auto Up, gap, canvas, Suggestion, mesin cetak & plong,
// optimasi & ranking plano, kalkulator PCS<->PLANO, BOM / Ink / BOP (SpecEngine), Save All, Confirm -> Product Costing,
// pratinjau & cetak Master Card (A4 landscape).
// data: jobs, machines, dieCutters, planos, initialJob, jobIssue, backUrl, lookups, gripperMm, marginMm,
//       spec (hasil SpecEngine contoh), materials (pencarian bahan BOM), costingUrl (tujuan setelah Confirm).
class Workbench extends DevlibComponent
{
    public function __construct(public array $data = []) {}

    public function tag(): string
    {
        return 'x-mp::prodev.workbench';
    }

    public function source(): string
    {
        return \MockupParts\Src::of('tm2mst.prod_part', 'tm2mst.prod_layout_group', 'tm2sdi.iqp_layout', 'tm2mst.prod_machine', 'tm2mst.mat_plano_size');
    }

    public function config(): array
    {
        return $this->data + ['jobs' => [], 'machines' => [], 'dieCutters' => [], 'planos' => [], 'initialJob' => 0, 'jobIssue' => null, 'backUrl' => '#'];
    }

    public function render()
    {
        return view('mp::prodev.workbench');
    }
}
