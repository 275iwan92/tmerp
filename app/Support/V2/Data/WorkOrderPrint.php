<?php

declare(strict_types=1);

/* REMARK
 * Tujuan       : Isi cetakan Work Order untuk lapangan (formulir kerja): rincian order, jadwal, Bill of Process dengan kolom aktual KOSONG untuk diisi tangan, Bill of Material referensi, pengesahan lapangan. Pindahan layar v1 pages::production.wo-print.
 * Sumber hulu  : prd_wo, prd_wo_operation (+ mesin, crew utama), iqp_part_material, mat_raw, view tm2inv.v_rm_available; perhitungan Qty Kebutuhan / Tersedia sama dengan v1 (pencatatan digital tetap lewat layar WO).
 * Input        : id WO.
 * Output       : array props x-dl::print-sheet (title, code, info, sections, signatures, footer).
 * Dipakai oleh : rute v2.production.wo.print (layar v2 print-wo); tombol Print di layar production-wo.
 * Status       : Aktif (T9 pindahan v1, 2026-10-20).
 */

namespace App\Support\V2\Data;

use App\Models\Master\MatRaw;
use App\Models\Sdi\IqpPartMaterial;
use App\Models\Prd\PrdWo;
use Devlib\Locale;
use Illuminate\Support\Facades\DB;

final class WorkOrderPrint
{
    public readonly PrdWo $wo;

    public function __construct(int $woId)
    {
        $this->wo = PrdWo::with(['coOrder', 'product', 'operations.crew.employee'])->findOrFail($woId);
    }

    public function fileTitle(): string
    {
        return t('Work Order').' '.$this->wo->wo_no;
    }

    /** @return list<string> */
    private function info(): array
    {
        return [tf('Printed: :at', ['at' => Locale::stamp(now())]).' · '.t('Type').': '.$this->wo->wo_type.($this->wo->is_hot_job ? ' · HOT JOB' : '')];
    }

    private function parties(): array
    {
        $wo = $this->wo;

        return ['type' => 'parties', 'items' => [
            ['label' => 'Order details', 'lines' => [(string) ($wo->product?->product_name ?? '—'),
                t('CO No.').': '.($wo->coOrder?->co_no ?? '—').($wo->part_code ? ' · '.t('Part').': '.$wo->part_code : ''),
                t('Planned Qty').': '.Locale::number((float) $wo->qty).' '.t('pcs')]],
            ['label' => 'Schedule', 'lines' => [t('Due Date').': '.($wo->due_date ? Locale::dt($wo->due_date) : '—'), t('Material Needed By').': '.($wo->material_needed_by ? Locale::date($wo->material_needed_by) : '—')]],
        ]];
    }

    private function processSection(): array
    {
        $columns = [['key' => 'seq', 'label' => 'Seq', 'align' => 'center', 'width' => 9], ['key' => 'process', 'label' => 'Process', 'width' => 28], ['key' => 'machine', 'label' => 'Machine', 'width' => 25],
            ['key' => 'operator', 'label' => 'Operator', 'width' => 22], ['key' => 'rate', 'label' => 'Target/Hour', 'align' => 'center', 'width' => 16], ['key' => 'plan', 'label' => 'Planned Start', 'align' => 'center', 'width' => 17],
            ['key' => 'a_start', 'label' => 'Actual Start', 'width' => 17], ['key' => 'a_end', 'label' => 'Actual Finish', 'width' => 17], ['key' => 'good', 'label' => 'Good Qty', 'width' => 15],
            ['key' => 'reject', 'label' => 'Reject', 'width' => 13], ['key' => 'waste', 'label' => 'Waste', 'width' => 13], ['key' => 'init', 'label' => 'Initials', 'width' => 14]];
        $rows = $this->wo->operations->map(fn ($op) => [
            'seq' => (string) $op->seq_no, 'process' => ['text' => (string) ($op->process_name ?: '—'), 'bold' => true],
            'machine' => $op->resource_type === 'MACHINE' ? (string) ($op->machine?->machine_name ?? '—') : t('Manual'),
            'operator' => (string) ($op->operatorUtama()?->employee?->full_name ?? '—'),
            'rate' => $op->rate_band_value ? Locale::number((float) $op->rate_band_value) : '—', 'plan' => $op->target_start ? $op->target_start->format('d/m H:i') : '—',
            'a_start' => '', 'a_end' => '', 'good' => '', 'reject' => '', 'waste' => '', 'init' => '',
        ])->all();

        return ['no' => 1, 'title' => 'Bill of Process', 'blocks' => [['type' => 'table', 'columns' => $columns, 'rows' => $rows, 'empty' => 'No process yet.',
            'note' => t('Planned columns are filled from the plan; blank columns are filled by hand in the field.')]]];
    }

    private function materialSection(): ?array
    {
        if (! $this->wo->source_iqp_part_id) {
            return null;
        }
        $rows = [];
        foreach (IqpPartMaterial::where('part_id', $this->wo->source_iqp_part_id)->orderBy('layer_seq')->get() as $mat) {
            $raw = $mat->rawmat_id ? MatRaw::find($mat->rawmat_id) : null;
            $unit = $raw?->uom_stock ?: 'Pcs';
            $need = (float) $this->wo->qty;
            $have = $mat->rawmat_id ? (float) DB::connection('tm2inv')->table('v_rm_available')->where('material_id', $mat->rawmat_id)->sum('qty_available') : 0.0;
            $rows[] = ['name' => ['text' => (string) $mat->rawmat_name, 'bold' => true], 'need' => Locale::number($need).' '.$unit, 'have' => Locale::number($have).' '.$unit, 'taken' => ''];
        }

        return $rows ? ['no' => 2, 'title' => 'Bill of Material', 'blocks' => [['type' => 'table', 'columns' => [['key' => 'name', 'label' => 'Material', 'width' => 80], ['key' => 'need', 'label' => 'Required Qty', 'align' => 'center'],
            ['key' => 'have', 'label' => 'Available Qty (Warehouse)', 'align' => 'center'], ['key' => 'taken', 'label' => 'Qty Taken (initials)', 'align' => 'center']], 'rows' => $rows,
            'note' => t('Required and Available Qty are for reference; fill the Taken column by hand when taking from the warehouse.')]]] : null;
    }

    /** Props x-dl::print-sheet. */
    public function sheet(): array
    {
        return ['title' => 'Work Order', 'code' => $this->wo->wo_no, 'info' => $this->info(),
            'sections' => array_values(array_filter([['title' => '', 'blocks' => [$this->parties()]], $this->processSection(), $this->materialSection()])),
            'signatures' => [['role' => 'Prepared By', 'name' => 'PPIC Officer'], ['role' => 'Machine Operator', 'name' => 'Full name & initials'], ['role' => 'Checked By QC', 'name' => 'Full name & initials']],
            'footer' => $this->wo->wo_no.' · '.t('Printed on').' '.Locale::stamp(now()).' · '.t('internal work document, not a customer document')];
    }
}
